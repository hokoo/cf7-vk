#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SOURCE_MANIFEST="${SCRIPT_DIR}/e1-version-sources.json"
PLUGIN_SLUG="$(jq -r '.plugin_slug' "${SOURCE_MANIFEST}")"
ENTRYPOINT="$(jq -r '.entrypoint' "${SOURCE_MANIFEST}")"
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
WORKDIR="${CF7VK_E1_WORKDIR:-$(mktemp -d "${TMPDIR:-/tmp}/cf7vk-e1-${RUN_ID}.XXXXXX")}"
CACHE_DIR="${CF7VK_E1_CACHE_DIR:-${XDG_CACHE_HOME:-${HOME}/.cache}/cf7-vk/e1-stability}"
RESULTS_DIR="${CF7VK_E1_RESULTS_DIR:-${WORKDIR}/results}"
LOG_DIR="${RESULTS_DIR}/logs"
STATE_DIR="${RESULTS_DIR}/state"
ROLLBACK_DIR="${RESULTS_DIR}/rollback"
ARTIFACT_DIR="${WORKDIR}/artifacts"
RUNTIME_DIR="${WORKDIR}/runtime"
COMPOSE_FILE="${WORKDIR}/docker-compose.e1.yml"
EVIDENCE_JSONL="${RESULTS_DIR}/evidence.jsonl"
SUMMARY_JSON="${RESULTS_DIR}/summary.json"
DEFAULT_CANDIDATE_VERSION="$(jq -r '.candidate.expected_version // empty' "${SOURCE_MANIFEST}")"
WP_VERSION="${CF7VK_E1_WP_VERSION:-$(jq -r '.wordpress.default_core_version' "${SOURCE_MANIFEST}")}"
WP_CLI_IMAGE="${CF7VK_E1_WP_CLI_IMAGE:-$(jq -r '.wordpress.default_cli_image' "${SOURCE_MANIFEST}")}"
CF7_VERSION="${CF7VK_E1_CF7_VERSION:-$(jq -r '.dependencies.contact_form_7.default_version' "${SOURCE_MANIFEST}")}"
FIXTURE="${CF7VK_E1_FIXTURE:-modern-heavy}"
CHARACTERIZATION="${CF7VK_E2_CHARACTERIZATION:-1}"
KEEP_WORKDIR=0
ARTIFACT_ONLY=0
FAILURES=0
SUMMARY_WRITTEN=0
CURRENT_PROJECT=""
CASES=()

usage() {
	cat <<'USAGE'
Usage: tests/stability/e1-smoke-matrix.sh [options]

Options:
  --case <name>       Run one case. Repeatable. Known names:
                      fresh, upgrade-v-0.1.0, upgrade-v-0.1.1,
                      upgrade-v-0.1.2, upgrade-v-0.1.3, upgrade-v-0.1.4
  --artifact-only     Verify/download source artifacts and candidate zip only.
  --workdir <path>    Use an explicit temporary work directory.
  --keep-workdir      Keep Docker containers/volumes for debugging.
  -h, --help          Show this help.

Environment:
  CF7VK_CANDIDATE_ZIP                 Candidate zip. Defaults to dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip
                                      when present, then scripts/build-release-zip.sh.
  CF7VK_EXPECTED_CANDIDATE_VERSION    Expected candidate header version, default from e1-version-sources.json.
  CF7VK_E1_WP_VERSION                 WordPress core version, default latest.
  CF7VK_E1_WP_CLI_IMAGE               WP-CLI Docker image, default wordpress:cli-php8.3.
  CF7VK_E1_CF7_VERSION                Contact Form 7 version, default latest.
  CF7VK_E1_FIXTURE                    modern-heavy, modern-basic, legacy-heavy, legacy-basic,
                                      damaged-modern, partial-modern, or none. Default modern-heavy.
  CF7VK_E2_CHARACTERIZATION           Run migration/lifecycle characterization after upgrades. Default 1.
  CF7VK_E1_CACHE_DIR                  Cache for downloaded source zips.
  CF7VK_E1_RESULTS_DIR                Evidence output directory.
USAGE
}

refresh_workdir_paths() {
	RESULTS_DIR="${CF7VK_E1_RESULTS_DIR:-${WORKDIR}/results}"
	LOG_DIR="${RESULTS_DIR}/logs"
	STATE_DIR="${RESULTS_DIR}/state"
	ROLLBACK_DIR="${RESULTS_DIR}/rollback"
	ARTIFACT_DIR="${WORKDIR}/artifacts"
	RUNTIME_DIR="${WORKDIR}/runtime"
	COMPOSE_FILE="${WORKDIR}/docker-compose.e1.yml"
	EVIDENCE_JSONL="${RESULTS_DIR}/evidence.jsonl"
	SUMMARY_JSON="${RESULTS_DIR}/summary.json"
}

while [ "$#" -gt 0 ]; do
	case "$1" in
		--case)
			shift
			[ "$#" -gt 0 ] || { echo "Missing value for --case" >&2; exit 2; }
			CASES+=("$1")
			;;
		--artifact-only)
			ARTIFACT_ONLY=1
			;;
		--workdir)
			shift
			[ "$#" -gt 0 ] || { echo "Missing value for --workdir" >&2; exit 2; }
			WORKDIR="$1"
			refresh_workdir_paths
			;;
		--keep-workdir)
			KEEP_WORKDIR=1
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			echo "Unknown argument: $1" >&2
			usage >&2
			exit 2
			;;
	esac
	shift
done

if [ "${#CASES[@]}" -eq 0 ]; then
	CASES=(fresh upgrade-v-0.1.0 upgrade-v-0.1.1 upgrade-v-0.1.2 upgrade-v-0.1.3 upgrade-v-0.1.4)
fi

mkdir -p "${CACHE_DIR}" "${RESULTS_DIR}" "${LOG_DIR}" "${STATE_DIR}" "${ROLLBACK_DIR}" "${ARTIFACT_DIR}" "${RUNTIME_DIR}"
chmod 0777 "${RUNTIME_DIR}"
: > "${EVIDENCE_JSONL}"

docker_compose() {
	if command -v docker-compose >/dev/null 2>&1; then
		docker-compose "$@"
	else
		docker compose "$@"
	fi
}

emit() {
	local case_id="$1"
	local step="$2"
	local status="$3"
	local message="$4"
	local extra="${5:-}"

	if [ -z "${extra}" ]; then
		extra='{}'
	fi

	jq -nc \
		--arg run_id "${RUN_ID}" \
		--arg case_id "${case_id}" \
		--arg step "${step}" \
		--arg status "${status}" \
		--arg message "${message}" \
		--argjson extra "${extra}" \
		'{
			run_id: $run_id,
			case: $case_id,
			step: $step,
			status: $status,
			message: $message,
			extra: $extra,
			captured_at_gmt: (now | todate)
		}' >> "${EVIDENCE_JSONL}"
}

fail_step() {
	local case_id="$1"
	local step="$2"
	local message="$3"
	local extra="${4:-}"

	if [ -z "${extra}" ]; then
		extra='{}'
	fi

	FAILURES=$((FAILURES + 1))
	emit "${case_id}" "${step}" "fail" "${message}" "${extra}"
}

skip_step() {
	local case_id="$1"
	local step="$2"
	local message="$3"
	local extra="${4:-}"

	if [ -z "${extra}" ]; then
		extra='{}'
	fi

	emit "${case_id}" "${step}" "skipped" "${message}" "${extra}"
}

run_logged() {
	local case_id="$1"
	local step="$2"
	local exit_code
	shift 2
	local log_file="${LOG_DIR}/${case_id}-${step}.log"

	if "$@" >"${log_file}" 2>&1; then
		emit "${case_id}" "${step}" "pass" "Command succeeded." "$(jq -nc --arg log "${log_file}" '{log:$log}')"
		return 0
	fi

	exit_code="$?"
	fail_step "${case_id}" "${step}" "Command failed." "$(jq -nc --arg log "${log_file}" --argjson exit_code "${exit_code}" '{log:$log,exit_code:$exit_code}')"
	return "${exit_code}"
}

run_logged_json() {
	local case_id="$1"
	local step="$2"
	local exit_code extra json_payload
	shift 2
	local log_file="${LOG_DIR}/${case_id}-${step}.log"

	if "$@" >"${log_file}" 2>&1; then
		json_payload="$(sed -n '/^{/,$p' "${log_file}")"
		if [ -n "${json_payload}" ] && extra="$(jq -c --arg log "${log_file}" '{log:$log,result:.}' <<< "${json_payload}" 2>/dev/null)"; then
			emit "${case_id}" "${step}" "pass" "Command succeeded and emitted JSON evidence." "${extra}"
		else
			emit "${case_id}" "${step}" "pass" "Command succeeded." "$(jq -nc --arg log "${log_file}" '{log:$log}')"
		fi
		return 0
	fi

	exit_code="$?"
	fail_step "${case_id}" "${step}" "Command failed." "$(jq -nc --arg log "${log_file}" --argjson exit_code "${exit_code}" '{log:$log,exit_code:$exit_code}')"
	return "${exit_code}"
}

project_name_for_case() {
	local case_id="$1"
	local safe
	safe="$(printf '%s' "${case_id}" | tr '[:upper:]' '[:lower:]' | tr '.-' '__' | tr -cd 'a-z0-9_')"
	printf 'cf7vke1%s%s' "$$" "${safe}"
}

dc() {
	docker_compose -f "${COMPOSE_FILE}" -p "${CURRENT_PROJECT}" "$@"
}

cleanup_project() {
	if [ -n "${CURRENT_PROJECT}" ] && [ "${KEEP_WORKDIR}" -eq 0 ]; then
		docker_compose -f "${COMPOSE_FILE}" -p "${CURRENT_PROJECT}" down -v --remove-orphans >/dev/null 2>&1 || true
	fi
}

write_summary() {
	local candidate_zip="${ARTIFACT_DIR}/${PLUGIN_SLUG}-candidate.zip"
	local candidate_version=""
	local candidate_sha256=""

	if [ -f "${candidate_zip}" ]; then
		candidate_version="$(zip_header_version "${candidate_zip}" "$(jq -r '.candidate.header_path_in_zip' "${SOURCE_MANIFEST}")" 2>/dev/null || true)"
		candidate_sha256="$(sha256sum "${candidate_zip}" 2>/dev/null | awk '{print $1}')"
	fi

	jq -s \
		--arg run_id "${RUN_ID}" \
		--arg workdir "${WORKDIR}" \
		--arg results_dir "${RESULTS_DIR}" \
		--arg rollback_dir "${ROLLBACK_DIR}" \
		--arg source_manifest "${SOURCE_MANIFEST}" \
		--argjson support_contract "$(jq -c '.support_contract // {}' "${SOURCE_MANIFEST}")" \
		--argjson support_matrix "$(jq -c '.support_matrix // []' "${SOURCE_MANIFEST}")" \
		--arg candidate_version "${candidate_version}" \
		--arg candidate_sha256 "${candidate_sha256}" \
		--arg expected_candidate_version "${CF7VK_EXPECTED_CANDIDATE_VERSION:-${DEFAULT_CANDIDATE_VERSION}}" \
		--arg wp_version "${WP_VERSION}" \
		--arg wp_cli_image "${WP_CLI_IMAGE}" \
		--arg cf7_version "${CF7_VERSION}" \
		--arg fixture "${FIXTURE}" \
		'{
			run_id: $run_id,
			workdir: $workdir,
			results_dir: $results_dir,
			rollback_dir: $rollback_dir,
			source_manifest: $source_manifest,
			support_contract: $support_contract,
			support_matrix: $support_matrix,
			environment: {
				wp_version: $wp_version,
				wp_cli_image: $wp_cli_image,
				contact_form_7_version: $cf7_version,
				fixture: $fixture,
				candidate: {
					expected_version: $expected_candidate_version,
					actual_version: $candidate_version,
					sha256: $candidate_sha256
				},
				uses_repo_docker_compose: false,
				dev_database_guard: "Harness writes a temporary Compose file and project; it does not use repository docker-compose.yml or persistent development volumes."
			},
			total_steps: length,
			passed_steps: ([.[] | select(.status == "pass")] | length),
			skipped_steps: ([.[] | select(.status == "skipped")] | length),
			failed_steps: ([.[] | select(.status == "fail")] | length),
			failures: [.[] | select(.status == "fail")],
			evidence: .
		}' "${EVIDENCE_JSONL}" > "${SUMMARY_JSON}"
	SUMMARY_WRITTEN=1
}

on_exit() {
	local status="$?"

	if [ -s "${EVIDENCE_JSONL}" ] && [ "${SUMMARY_WRITTEN}" -eq 0 ]; then
		if [ "${status}" -ne 0 ]; then
			emit "run" "exit" "fail" "Script exited before normal completion." "$(jq -nc --argjson exit_code "${status}" '{exit_code:$exit_code}')"
		fi
		write_summary || true
	fi

	cleanup_project
}

trap on_exit EXIT

require_tools() {
	local missing=()
	for tool in composer curl git jq npm rsync sha256sum tar unzip zip; do
		command -v "${tool}" >/dev/null 2>&1 || missing+=("${tool}")
	done

	if [ "${ARTIFACT_ONLY}" -eq 0 ]; then
		command -v docker >/dev/null 2>&1 || missing+=("docker")
		if ! command -v docker-compose >/dev/null 2>&1 && ! docker compose version >/dev/null 2>&1; then
			missing+=("docker-compose or docker compose")
		fi
	fi

	if [ "${#missing[@]}" -gt 0 ]; then
		fail_step "preflight" "tools" "Missing required tools: ${missing[*]}"
		exit 2
	fi

	emit "preflight" "tools" "pass" "Required tools are available."
}

zip_header_version() {
	local zip_file="$1"
	local header_path="$2"

	unzip -p "${zip_file}" "${header_path}" | awk -F': *' '/Version:/{gsub(/\r/,"",$2); print $2; exit}'
}

verify_zip_version() {
	local case_id="$1"
	local step="$2"
	local zip_file="$3"
	local header_path="$4"
	local expected="$5"
	local actual

	if ! unzip -tq "${zip_file}" >/dev/null 2>&1; then
		fail_step "${case_id}" "${step}" "Zip integrity check failed." "$(jq -nc --arg file "${zip_file}" '{file:$file}')"
		return 1
	fi

	actual="$(zip_header_version "${zip_file}" "${header_path}")"
	if [ "${actual}" != "${expected}" ]; then
		fail_step "${case_id}" "${step}" "Unexpected plugin header version." "$(jq -nc --arg file "${zip_file}" --arg expected "${expected}" --arg actual "${actual}" --arg header_path "${header_path}" '{file:$file,expected:$expected,actual:$actual,header_path:$header_path}')"
		return 1
	fi

	emit "${case_id}" "${step}" "pass" "Zip integrity and expected plugin version verified." "$(jq -nc --arg file "${zip_file}" --arg version "${actual}" --arg sha256 "$(sha256sum "${zip_file}" | awk '{print $1}')" --argjson bytes "$(wc -c < "${zip_file}")" '{file:$file,version:$version,sha256:$sha256,bytes:$bytes}')"
}

verify_git_tag_version() {
	local index="$1"
	local matrix_id tag expected actual commit tag_object

	matrix_id="$(jq -r ".legacy_versions[${index}].matrix_id" "${SOURCE_MANIFEST}")"
	tag="$(jq -r ".legacy_versions[${index}].tag" "${SOURCE_MANIFEST}")"
	expected="$(jq -r ".legacy_versions[${index}].version" "${SOURCE_MANIFEST}")"

	if ! git -C "${REPO_ROOT}" rev-parse --verify "${tag}^{commit}" >/dev/null 2>&1; then
		fail_step "artifact-${matrix_id}" "git_tag" "Local git tag is missing." "$(jq -nc --arg tag "${tag}" '{tag:$tag}')"
		return 1
	fi

	actual="$(git -C "${REPO_ROOT}" show "${tag}:plugin-dir/${ENTRYPOINT}" | awk -F': *' '/Version:/{gsub(/\r/,"",$2); print $2; exit}')"
	commit="$(git -C "${REPO_ROOT}" rev-parse "${tag}^{}")"
	tag_object="$(git -C "${REPO_ROOT}" rev-parse "${tag}")"

	if [ "${actual}" != "${expected}" ]; then
		fail_step "artifact-${matrix_id}" "git_tag" "Local git tag version mismatch." "$(jq -nc --arg tag "${tag}" --arg expected "${expected}" --arg actual "${actual}" '{tag:$tag,expected:$expected,actual:$actual}')"
		return 1
	fi

	skip_step "artifact-${matrix_id}" "public_zip" "No public installable ZIP is recorded; local git tag was verified for traceability." "$(jq -nc --arg tag "${tag}" --arg version "${actual}" --arg commit "${commit}" --arg tag_object "${tag_object}" '{tag:$tag,version:$version,commit:$commit,tag_object:$tag_object}')"
}

build_git_tag_artifact() {
	local index="$1"
	local output_zip="$2"
	local matrix_id tag version root_dir build_root source_dir stage_root stage_plugin source_plugin epoch log_file

	matrix_id="$(jq -r ".legacy_versions[${index}].matrix_id" "${SOURCE_MANIFEST}")"
	tag="$(jq -r ".legacy_versions[${index}].tag" "${SOURCE_MANIFEST}")"
	version="$(jq -r ".legacy_versions[${index}].version" "${SOURCE_MANIFEST}")"
	root_dir="$(jq -r ".legacy_versions[${index}].root_dir" "${SOURCE_MANIFEST}")"
	build_root="${WORKDIR}/tag-build-${matrix_id}"
	source_dir="${build_root}/source"
	stage_root="${build_root}/stage"
	stage_plugin="${stage_root}/${root_dir}"
	source_plugin="${source_dir}/plugin-dir"
	epoch="$(git -C "${REPO_ROOT}" log -1 --format=%ct "${tag}^{commit}")"
	log_file="${LOG_DIR}/artifact-${matrix_id}-local_tag_build.log"

	rm -rf "${build_root}"
	mkdir -p "${source_dir}" "${stage_plugin}" "$(dirname "${output_zip}")"

	(
		set -euo pipefail

		printf 'Building local git-tag artifact %s (%s)\n' "${tag}" "${version}"
		git -C "${REPO_ROOT}" archive "${tag}" | tar -x -C "${source_dir}"

		[ -f "${source_plugin}/${ENTRYPOINT}" ] || {
			printf 'Plugin entrypoint missing in tag: %s\n' "${source_plugin}/${ENTRYPOINT}" >&2
			exit 1
		}

		npm --prefix "${source_plugin}/react" ci
		CI=false npm --prefix "${source_plugin}/react" run build

		rsync -a --delete \
			--exclude '/vendor' \
			--exclude '/tests' \
			--exclude '/phpunit.xml.dist' \
			--exclude '/react/build' \
			--exclude '/react/node_modules' \
			--exclude '/react/src' \
			--exclude '/react/public' \
			--exclude '/react/scripts' \
			--exclude '/react/package.json' \
			--exclude '/react/package-lock.json' \
			--exclude '/react/README.md' \
			--exclude '/react/config-overrides.js' \
			--exclude '/react/webpack.config.js' \
			--exclude '/react/jest-unit.config.js' \
			--exclude '/phpunit.xml' \
			--exclude '/.git' \
			--exclude '/.gitignore' \
			--exclude '/.github' \
			--exclude '/.env' \
			--exclude '/.env.*' \
			"${source_plugin}/" "${stage_plugin}/"

		mkdir -p "${stage_plugin}/react/build"
		rsync -a --delete "${source_plugin}/react/build/" "${stage_plugin}/react/build/"

		composer --working-dir="${stage_plugin}" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=php
		[ -f "${stage_plugin}/vendor/autoload.php" ]
		rm -f "${stage_plugin}/composer.json" "${stage_plugin}/composer.lock"
		rm -rf "${stage_plugin}/vendor/ramsey/collection/build"

		find "${stage_plugin}/vendor" -depth -type d \( \
			-name 'local-dev' -o \
			-iname 'test' -o \
			-iname 'tests' -o \
			-iname 'doc' -o \
			-iname 'docs' -o \
			-iname 'example' -o \
			-iname 'examples' -o \
			-iname '.github' -o \
			-iname '.circleci' -o \
			-iname '.gitlab' -o \
			-iname 'vendor-bin' -o \
			-iname 'phpstan' -o \
			-iname 'psalm' -o \
			-iname 'docker' \
		\) -exec rm -rf {} +
		find "${stage_plugin}/vendor" -type f \( \
			-iname 'phpunit*' -o \
			-iname 'phpstan*' -o \
			-iname 'psalm*' -o \
			-iname 'phpcs*' -o \
			-iname 'php-wp-unit.xml' -o \
			-iname 'makefile' -o \
			-iname 'postman.json' -o \
			-iname 'captainhook.json' -o \
			-iname 'codecov.yml' -o \
			-iname 'conventional-commits.json' -o \
			-iname 'composer.json' -o \
			-iname 'composer.lock' -o \
			-iname '.php_cs*' -o \
			-iname 'dockerfile*' \
		\) -delete
		find "${stage_plugin}" -type f \( -name '*.key' -o -name '*.pem' -o -name '*.sql' -o -name '*.zip' -o -name '*.tgz' -o -name '*.tar' -o -name '*.tar.gz' -o -name '*.map' \) -delete
		find "${stage_plugin}" -depth -name '.*' -exec rm -rf {} +
		find "${stage_plugin}" -exec touch -h -d "@${epoch}" {} +

		rm -f "${output_zip}"
		(
			cd "${stage_root}"
			find "${root_dir}" -print | LC_ALL=C sort | zip -X -q "${output_zip}" -@
		)
	) >"${log_file}" 2>&1

	emit "artifact-${matrix_id}" "local_tag_build" "pass" "Built installable artifact from local git tag." "$(jq -nc --arg tag "${tag}" --arg version "${version}" --arg output_zip "${output_zip}" --arg log "${log_file}" --arg sha256 "$(sha256sum "${output_zip}" | awk '{print $1}')" --argjson bytes "$(wc -c < "${output_zip}")" '{tag:$tag,version:$version,file:$output_zip,sha256:$sha256,bytes:$bytes,log:$log}')"
}

prepare_candidate() {
	local candidate_zip="${ARTIFACT_DIR}/${PLUGIN_SLUG}-candidate.zip"
	local expected="${CF7VK_EXPECTED_CANDIDATE_VERSION:-${DEFAULT_CANDIDATE_VERSION}}"
	local dist_zip="${REPO_ROOT}/dist/${PLUGIN_SLUG}-wp-plugin.zip"
	local header_path

	header_path="$(jq -r '.candidate.header_path_in_zip' "${SOURCE_MANIFEST}")"

	if [ -n "${CF7VK_CANDIDATE_ZIP:-}" ]; then
		if [ ! -f "${CF7VK_CANDIDATE_ZIP}" ]; then
			fail_step "candidate" "source" "CF7VK_CANDIDATE_ZIP does not exist." "$(jq -nc --arg file "${CF7VK_CANDIDATE_ZIP}" '{file:$file}')"
			exit 2
		fi
		cp "${CF7VK_CANDIDATE_ZIP}" "${candidate_zip}"
		emit "candidate" "source" "pass" "Using candidate zip from CF7VK_CANDIDATE_ZIP." "$(jq -nc --arg file "${CF7VK_CANDIDATE_ZIP}" '{file:$file}')"
	elif [ -f "${dist_zip}" ]; then
		cp "${dist_zip}" "${candidate_zip}"
		emit "candidate" "source" "pass" "Using candidate zip from dist." "$(jq -nc --arg file "${dist_zip}" '{file:$file}')"
	else
		run_logged "candidate" "build" "${REPO_ROOT}/scripts/build-release-zip.sh" || exit 2
		cp "${dist_zip}" "${candidate_zip}"
		emit "candidate" "source" "pass" "Built candidate zip from local source." "$(jq -nc --arg file "${dist_zip}" '{file:$file}')"
	fi

	if [ -x "${REPO_ROOT}/scripts/validate-release-zip.sh" ]; then
		run_logged "candidate" "validate" "${REPO_ROOT}/scripts/validate-release-zip.sh" "${candidate_zip}" "${expected}" || exit 2
	fi

	verify_zip_version "candidate" "version" "${candidate_zip}" "${header_path}" "${expected}"
}

download_and_verify_sources() {
	local count
	count="$(jq '.legacy_versions | length' "${SOURCE_MANIFEST}")"

	for i in $(seq 0 $((count - 1))); do
		local matrix_id version source_type url expected_sha header_path root_dir zip_file actual_sha artifact_file
		matrix_id="$(jq -r ".legacy_versions[${i}].matrix_id" "${SOURCE_MANIFEST}")"
		version="$(jq -r ".legacy_versions[${i}].version" "${SOURCE_MANIFEST}")"
		source_type="$(jq -r ".legacy_versions[${i}].source_type" "${SOURCE_MANIFEST}")"
		url="$(jq -r ".legacy_versions[${i}].url // empty" "${SOURCE_MANIFEST}")"
		expected_sha="$(jq -r ".legacy_versions[${i}].sha256 // empty" "${SOURCE_MANIFEST}")"
		header_path="$(jq -r ".legacy_versions[${i}].header_path_in_zip" "${SOURCE_MANIFEST}")"
		root_dir="$(jq -r ".legacy_versions[${i}].root_dir" "${SOURCE_MANIFEST}")"
		zip_file="${CACHE_DIR}/${PLUGIN_SLUG}.${version}.zip"
		artifact_file="${ARTIFACT_DIR}/${PLUGIN_SLUG}.${version}.zip"

		if [ "${source_type}" = "local_git_tag" ]; then
			if [ -f "${zip_file}" ] && ! unzip -l "${zip_file}" "${root_dir}/vendor/autoload.php" >/dev/null 2>&1; then
				rm -f "${zip_file}"
			fi
			if [ ! -f "${zip_file}" ]; then
				build_git_tag_artifact "${i}" "${zip_file}" || {
					fail_step "artifact-${matrix_id}" "local_tag_build" "Could not build installable artifact from local git tag." "$(jq -nc --arg tag "$(jq -r ".legacy_versions[${i}].tag" "${SOURCE_MANIFEST}")" '{tag:$tag}')"
					exit 3
				}
			fi
			if ! unzip -l "${zip_file}" "${root_dir}/vendor/autoload.php" >/dev/null 2>&1; then
				fail_step "artifact-${matrix_id}" "local_tag_build" "Built local git tag artifact does not contain vendor/autoload.php." "$(jq -nc --arg file "${zip_file}" '{file:$file}')"
				exit 3
			fi
			verify_zip_version "artifact-${matrix_id}" "version" "${zip_file}" "${header_path}" "${version}" || exit 3
			cp "${zip_file}" "${artifact_file}"
			continue
		fi

		if [ ! -f "${zip_file}" ]; then
			if ! curl -fsSL "${url}" -o "${zip_file}"; then
				fail_step "artifact-${matrix_id}" "download" "Could not download legacy artifact." "$(jq -nc --arg url "${url}" --arg file "${zip_file}" '{url:$url,file:$file}')"
				exit 3
			fi
		fi

		actual_sha="$(sha256sum "${zip_file}" | awk '{print $1}')"
		if [ "${actual_sha}" != "${expected_sha}" ]; then
			fail_step "artifact-${matrix_id}" "checksum" "Legacy artifact checksum mismatch." "$(jq -nc --arg file "${zip_file}" --arg expected "${expected_sha}" --arg actual "${actual_sha}" '{file:$file,expected:$expected,actual:$actual}')"
			exit 3
		fi

		verify_zip_version "artifact-${matrix_id}" "version" "${zip_file}" "${header_path}" "${version}" || exit 3
		cp "${zip_file}" "${artifact_file}"
	done
}

legacy_field_for_case() {
	local matrix_id="$1"
	local field="$2"
	jq -r --arg matrix_id "${matrix_id}" --arg field "${field}" '.legacy_versions[] | select(.matrix_id == $matrix_id) | .[$field] // empty' "${SOURCE_MANIFEST}"
}

wp_run() {
	dc run --rm cli php -d memory_limit=512M /usr/local/bin/wp --allow-root "$@"
}

retry_wp() {
	local tries=30
	local delay=2
	local i
	for i in $(seq 1 "${tries}"); do
		if wp_run "$@"; then
			return 0
		fi
		sleep "${delay}"
	done

	echo "WP-CLI command did not succeed after ${tries} attempts: wp $*" >&2
	wp_run "$@"
}

write_compose_file() {
	cat > "${COMPOSE_FILE}" <<COMPOSE
services:
  db:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
    command: --default-authentication-plugin=mysql_native_password
  cli:
    image: ${WP_CLI_IMAGE}
    depends_on:
      - db
    working_dir: /var/www/html
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
      CF7VK_E1_FIXTURE: ${FIXTURE}
      WP_CLI_PHP_ARGS: -d memory_limit=512M
    volumes:
      - wp-data:/var/www/html
      - ${ARTIFACT_DIR}:/artifacts:ro
      - ${RUNTIME_DIR}:/runtime
      - ${SCRIPT_DIR}:/e1-tests:ro
volumes:
  wp-data:
COMPOSE

	emit "preflight" "compose_file" "pass" "Wrote isolated Docker Compose file." "$(jq -nc --arg file "${COMPOSE_FILE}" --arg image "${WP_CLI_IMAGE}" '{file:$file,wp_cli_image:$image}')"
}

setup_site() {
	local case_id="$1"

	run_logged "${case_id}" "db_up" dc up -d db || return 1

	if [ "${WP_VERSION}" = "latest" ]; then
		run_logged "${case_id}" "core_download" retry_wp core download --path=/var/www/html --force || return 1
	else
		run_logged "${case_id}" "core_download" retry_wp core download --path=/var/www/html --version="${WP_VERSION}" --force || return 1
	fi

	run_logged "${case_id}" "config_create" wp_run config create --path=/var/www/html --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost=db:3306 --skip-check --force || return 1
	run_logged "${case_id}" "db_wait" retry_wp db check --path=/var/www/html --skip-ssl || return 1
	run_logged "${case_id}" "core_install" wp_run core install --path=/var/www/html --url="http://${CURRENT_PROJECT}.test" --title="CF7VK E1 ${case_id}" --admin_user=admin --admin_password=admin-password --admin_email=admin@example.test || return 1

	if [ "${CF7_VERSION}" = "latest" ]; then
		run_logged "${case_id}" "cf7_install" retry_wp plugin install contact-form-7 --activate || return 1
	else
		run_logged "${case_id}" "cf7_install" retry_wp plugin install contact-form-7 --version="${CF7_VERSION}" --activate || return 1
	fi
}

write_state() {
	local case_id="$1"
	local label="$2"
	local state_file="${STATE_DIR}/${case_id}-${label}.json"
	local log_file="${LOG_DIR}/${case_id}-${label}-snapshot.log"

	if wp_run eval-file /e1-tests/wp-state-snapshot.php >"${state_file}" 2>"${log_file}"; then
		emit "${case_id}" "snapshot-${label}" "pass" "Captured WordPress state snapshot." "$(jq -nc --arg file "${state_file}" '{state_file:$file}')"
		return 0
	fi

	fail_step "${case_id}" "snapshot-${label}" "Could not capture WordPress state snapshot." "$(jq -nc --arg log "${log_file}" '{log:$log}')"
	return 1
}

assert_state_jq() {
	local case_id="$1"
	local label="$2"
	local expression="$3"
	local message="$4"
	local state_file="${STATE_DIR}/${case_id}-${label}.json"

	if jq -e "${expression}" "${state_file}" >/dev/null; then
		emit "${case_id}" "assert-${label}" "pass" "${message}" "$(jq -nc --arg state_file "${state_file}" '{state_file:$state_file}')"
		return 0
	fi

	fail_step "${case_id}" "assert-${label}" "${message}" "$(jq -nc --arg state_file "${state_file}" --arg expression "${expression}" '{state_file:$state_file,expression:$expression}')"
	return 1
}

assert_active_version() {
	local case_id="$1"
	local label="$2"
	local expected="$3"

	assert_state_jq "${case_id}" "${label}" ".plugin.active == true and .plugin.version == \"${expected}\"" "Plugin is active at expected version ${expected}."
}

assert_uninstall_cleanup() {
	local case_id="$1"
	local label="$2"

	assert_state_jq "${case_id}" "${label}" \
		'.plugin.file_exists == false
		and .plugin.active == false
		and ((.active_plugins // []) | index("message-bridge-for-contact-form-7-and-vk/cf7-vk.php") == null)
		and ((.active_plugins // []) | index("cf7-vk/cf7-vk.php") == null)
		and .post_counts.cf7vk_bot == 0
		and .post_counts.cf7vk_chat == 0
		and .post_counts.cf7vk_channel == 0
		and .relations.exists == false
		and .relations.total == 0
		and .relations.meta_total == 0
		and .tables.post_connections_cf7_vk.exists == false
		and .tables.post_connections_meta_cf7_vk.exists == false
		and .tables.cf7vk_log.exists == false
		and ([.options[]?.count] | add // 0) == 0
		and .cron.cf7vk_cleanup.total == 0
		and .cron.cf7vk_migrations.total == 0
		and .migration.version_option == null
		and .migration.state_exists == false
		and .migration.lock_exists == false' \
		"Uninstall removed plugin files, active-plugin entries, plugin-owned posts, relations, tables, options, cron, and locks."
}

candidate_version() {
	zip_header_version "${ARTIFACT_DIR}/${PLUGIN_SLUG}-candidate.zip" "$(jq -r '.candidate.header_path_in_zip' "${SOURCE_MANIFEST}")"
}

characterize_migration() {
	local case_id="$1"
	local source_version="$2"
	local target_version="$3"

	if [ "${CHARACTERIZATION}" != "1" ]; then
		skip_step "${case_id}" "migration_characterization" "Migration characterization disabled by CF7VK_E2_CHARACTERIZATION." "$(jq -nc '{env:"CF7VK_E2_CHARACTERIZATION"}')"
		return 0
	fi

	run_logged_json "${case_id}" "migration_characterization" wp_run eval-file /e1-tests/wp-characterize-migration.php "${source_version}" "${target_version}"
}

run_fresh_case() {
	local case_id="fresh"
	local version
	local rc=0
	version="$(candidate_version)"

	CURRENT_PROJECT="$(project_name_for_case "${case_id}")"
	cleanup_project

	if setup_site "${case_id}" \
		&& run_logged "${case_id}" "candidate_install" wp_run plugin install "/artifacts/${PLUGIN_SLUG}-candidate.zip" --force --activate \
		&& write_state "${case_id}" "after-activate"; then
		assert_active_version "${case_id}" "after-activate" "${version}" || true
		run_logged_json "${case_id}" "fake_vk_fetch_updates_rest" wp_run eval-file /e1-tests/wp-fake-vk-fetch-updates.php || rc=1
		run_logged "${case_id}" "deactivate" wp_run plugin deactivate "${PLUGIN_SLUG}" || rc=1
		write_state "${case_id}" "after-deactivate" || rc=1
		run_logged "${case_id}" "reactivate" wp_run plugin activate "${PLUGIN_SLUG}" || rc=1
		write_state "${case_id}" "after-reactivate" || rc=1
		run_logged "${case_id}" "uninstall" wp_run plugin uninstall "${PLUGIN_SLUG}" --deactivate || rc=1
		if write_state "${case_id}" "after-uninstall"; then
			assert_uninstall_cleanup "${case_id}" "after-uninstall" || rc=1
		else
			rc=1
		fi
	else
		rc=1
	fi

	cleanup_project
	CURRENT_PROJECT=""
	return "${rc}"
}

seed_fixture() {
	local case_id="$1"

	run_logged "${case_id}" "seed_fixture" wp_run eval-file /e1-tests/wp-seed-fixture.php || return 1
}

run_upgrade_case() {
	local matrix_id="$1"
	local case_id="upgrade-${matrix_id}"
	local source_type legacy_version legacy_plugin candidate_basename version rollback_sql rollback_artifact
	local rc=0

	source_type="$(legacy_field_for_case "${matrix_id}" "source_type")"
	legacy_version="$(legacy_field_for_case "${matrix_id}" "version")"
	legacy_plugin="$(legacy_field_for_case "${matrix_id}" "plugin_basename")"
	candidate_basename="$(jq -r '.candidate.plugin_basename' "${SOURCE_MANIFEST}")"
	version="$(candidate_version)"
	rollback_sql="/runtime/${case_id}-rollback.sql"
	rollback_artifact="${ROLLBACK_DIR}/${case_id}-rollback.sql"

	if [ -z "${legacy_version}" ]; then
		fail_step "${case_id}" "legacy_lookup" "Unknown matrix id." "$(jq -nc --arg matrix_id "${matrix_id}" '{matrix_id:$matrix_id}')"
		return 1
	fi

	CURRENT_PROJECT="$(project_name_for_case "${case_id}")"
	cleanup_project

	if setup_site "${case_id}" \
		&& run_logged "${case_id}" "legacy_install" wp_run plugin install "/artifacts/${PLUGIN_SLUG}.${legacy_version}.zip" --force --activate \
		&& write_state "${case_id}" "legacy-active"; then
		assert_active_version "${case_id}" "legacy-active" "${legacy_version}" || true
	else
		rc=1
	fi

	if [ "${rc}" -eq 0 ]; then
		seed_fixture "${case_id}" || rc=1
		write_state "${case_id}" "legacy-seeded" || rc=1
		run_logged "${case_id}" "rollback_export" wp_run db export "${rollback_sql}" --path=/var/www/html || rc=1
		if [ "${rc}" -eq 0 ]; then
			cp "${RUNTIME_DIR}/${case_id}-rollback.sql" "${rollback_artifact}"
			emit "${case_id}" "rollback_evidence" "pass" "Captured rollback DB snapshot and baseline artifact identity." "$(jq -nc \
				--arg baseline_zip "${ARTIFACT_DIR}/${PLUGIN_SLUG}.${legacy_version}.zip" \
				--arg baseline_version "${legacy_version}" \
				--arg baseline_sha256 "$(sha256sum "${ARTIFACT_DIR}/${PLUGIN_SLUG}.${legacy_version}.zip" | awk '{print $1}')" \
				--arg db_snapshot "${rollback_artifact}" \
				--arg db_snapshot_sha256 "$(sha256sum "${rollback_artifact}" | awk '{print $1}')" \
				'{baseline_zip:$baseline_zip,baseline_version:$baseline_version,baseline_sha256:$baseline_sha256,db_snapshot:$db_snapshot,db_snapshot_sha256:$db_snapshot_sha256}')"
		fi
	fi

	if [ "${rc}" -eq 0 ]; then
		run_logged "${case_id}" "candidate_upgrade" wp_run eval-file /e1-tests/wp-upgrade-candidate.php "/artifacts/${PLUGIN_SLUG}-candidate.zip" "${legacy_plugin}" "${candidate_basename}" || rc=1
		characterize_migration "${case_id}" "${legacy_version}" "${version}" || rc=1
		write_state "${case_id}" "after-upgrade" || rc=1
		assert_active_version "${case_id}" "after-upgrade" "${version}" || true
		if [ "${CHARACTERIZATION}" = "1" ]; then
			assert_state_jq "${case_id}" "after-upgrade" ".migration.status == \"completed\" and .migration.version_option == \"${version}\" and .migration.error_count == 0 and (.relations.duplicate_signatures | length == 0) and (.fingerprints.lifecycle | type == \"string\")" "Migration state completed, relation duplicates are absent, and lifecycle fingerprint was captured." || true
		fi
	fi

	if [ "${rc}" -eq 0 ]; then
		run_logged "${case_id}" "deactivate" wp_run plugin deactivate "${PLUGIN_SLUG}" || rc=1
		write_state "${case_id}" "after-deactivate" || rc=1
		run_logged "${case_id}" "reactivate" wp_run plugin activate "${PLUGIN_SLUG}" || rc=1
		write_state "${case_id}" "after-reactivate" || rc=1
		run_logged "${case_id}" "uninstall" wp_run plugin uninstall "${PLUGIN_SLUG}" --deactivate || rc=1
		if write_state "${case_id}" "after-uninstall"; then
			assert_uninstall_cleanup "${case_id}" "after-uninstall" || rc=1
		else
			rc=1
		fi
	fi

	if [ "${rc}" -eq 0 ]; then
		run_logged "${case_id}" "rollback_import" wp_run db import "${rollback_sql}" --path=/var/www/html || rc=1
		run_logged "${case_id}" "rollback_plugin_restore" wp_run plugin install "/artifacts/${PLUGIN_SLUG}.${legacy_version}.zip" --force --activate || rc=1
		write_state "${case_id}" "after-rollback" || rc=1
		assert_active_version "${case_id}" "after-rollback" "${legacy_version}" || true
	fi

	cleanup_project
	CURRENT_PROJECT=""
	return "${rc}"
}

require_tools
prepare_candidate
download_and_verify_sources

if [ "${ARTIFACT_ONLY}" -eq 1 ]; then
	emit "artifact-only" "complete" "pass" "Artifact verification completed; Docker smoke cases were skipped by request."
	write_summary
	echo "E1 artifact verification complete: ${SUMMARY_JSON}"
	exit 0
fi

write_compose_file

for case_id in "${CASES[@]}"; do
	case "${case_id}" in
		fresh)
			run_fresh_case || true
			;;
		upgrade-v-*)
			run_upgrade_case "${case_id#upgrade-}" || true
			;;
		*)
			fail_step "${case_id}" "case" "Unknown case."
			;;
	esac
done

write_summary

echo "E1 smoke matrix evidence: ${SUMMARY_JSON}"
if [ "${FAILURES}" -gt 0 ]; then
	echo "E1 smoke matrix failed with ${FAILURES} failing step(s)." >&2
	exit 1
fi

echo "E1 smoke matrix passed."

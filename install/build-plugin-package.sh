#!/bin/bash

set -euo pipefail

MODE="${1:-stable}"
OUTPUT_DIR="${2:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/build/${MODE}}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${OUTPUT_DIR}/message-bridge-for-contact-form-7-and-vk"
ZIP_FILE="${OUTPUT_DIR}/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip"
OVERLAY_FILE="${ROOT_DIR}/install/release-assets/prerelease/lib/Distribution/GitHubReleaseChannel.php"

case "${MODE}" in
	stable|prerelease)
		;;
	*)
		echo "Unsupported build mode: ${MODE}. Use 'stable' or 'prerelease'." >&2
		exit 1
		;;
esac

command -v zip >/dev/null 2>&1 || {
	echo "The 'zip' command is required to build the plugin package." >&2
	exit 1
}

export COMPOSER_ROOT_VERSION="${COMPOSER_ROOT_VERSION:-1.0.0}"
export COMPOSER_NO_INTERACTION=1

prune_vendor_dev_artifacts() {
	local vendor_dir="${1}"

	if [ ! -d "${vendor_dir}" ]; then
		return
	fi

	# Keep library repos intact, but strip files that WordPress.org and similar
	# distribution targets treat as development-only baggage.
	find "${vendor_dir}" -type d \
		\( \
			-name '.github' -o \
			-name 'tests' -o \
			-name 'test' -o \
			-name 'local-dev' -o \
			-name 'docker' \
		\) \
		-prune -exec rm -rf {} +

	find "${vendor_dir}" -type f \
		\( \
			-name '.gitignore' -o \
			-name '.gitattributes' -o \
			-name '.editorconfig' -o \
			-name '.env' -o \
			-name '.env.*' -o \
			-name 'docker-compose.yml' -o \
			-name 'docker-compose.yaml' -o \
			-name 'Dockerfile' -o \
			-name 'Dockerfile.*' -o \
			-name 'dockerfile' -o \
			-name 'dockerfile.*' -o \
			-name 'makefile' -o \
			-name 'Makefile' -o \
			-name 'phpunit.xml' -o \
			-name 'phpunit.xml.dist' -o \
			-name 'php-wp-unit.xml' -o \
			-name 'phpcs.xml' -o \
			-name 'phpcs.xml.dist' -o \
			-name 'phpstan.neon' -o \
			-name 'phpstan.neon.dist' -o \
			-name 'postman.json' -o \
			-name 'composer.lock' \
		\) \
		-delete

	find "${vendor_dir}" -depth -type d -empty -delete
}

rm -rf "${OUTPUT_DIR}"
mkdir -p "${PLUGIN_DIR}"
cp -R "${ROOT_DIR}/plugin-dir/." "${PLUGIN_DIR}/"

rm -rf \
	"${PLUGIN_DIR}/vendor" \
	"${PLUGIN_DIR}/react/node_modules" \
	"${PLUGIN_DIR}/react/build"

(
	cd "${PLUGIN_DIR}/react"
	npm ci
	CI=false npm run build
	rm -rf node_modules
	find . -mindepth 1 -maxdepth 1 -not -name 'build' -exec rm -rf {} +
)

if [ "${MODE}" = "prerelease" ]; then
	mkdir -p "${PLUGIN_DIR}/lib/Distribution"
	cp "${OVERLAY_FILE}" "${PLUGIN_DIR}/lib/Distribution/GitHubReleaseChannel.php"

	(
		cd "${PLUGIN_DIR}"
		composer require yahnis-elsts/plugin-update-checker:^5.5 --prefer-dist --no-progress
		composer install --prefer-dist --no-dev --optimize-autoloader --no-progress
	)
else
	(
		cd "${PLUGIN_DIR}"
		composer install --prefer-dist --no-dev --optimize-autoloader --no-progress
	)
fi

prune_vendor_dev_artifacts "${PLUGIN_DIR}/vendor"

find "${PLUGIN_DIR}" -type f -name '.*' -delete
find "${PLUGIN_DIR}" -depth -type d -name '.*' -exec rm -rf {} +

(
	cd "${OUTPUT_DIR}"
	zip -rq "$(basename "${ZIP_FILE}")" message-bridge-for-contact-form-7-and-vk
)

echo "Built ${MODE} package directory: ${PLUGIN_DIR}"
echo "Built ${MODE} package zip: ${ZIP_FILE}"

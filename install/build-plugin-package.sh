#!/bin/bash

set -euo pipefail

MODE="${1:-stable}"
OUTPUT_DIR="${2:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/build/${MODE}}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${OUTPUT_DIR}/cf7-vk"
ZIP_FILE="${OUTPUT_DIR}/cf7-vk-wp-plugin.zip"
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

	if [ "${MODE}" != "prerelease" ]; then
		find . -mindepth 1 -maxdepth 1 -not -name 'build' -exec rm -rf {} +
	fi
)

if [ "${MODE}" = "prerelease" ]; then
	mkdir -p "${PLUGIN_DIR}/lib/Distribution"
	cp "${OVERLAY_FILE}" "${PLUGIN_DIR}/lib/Distribution/GitHubReleaseChannel.php"

	(
		cd "${PLUGIN_DIR}"
		composer require yahnis-elsts/plugin-update-checker:^5.5 --prefer-source --no-progress
		composer install --prefer-source --no-dev --optimize-autoloader --no-progress
	)
else
	(
		cd "${PLUGIN_DIR}"
		composer install --prefer-source --no-dev --optimize-autoloader --no-progress
	)
fi

find "${PLUGIN_DIR}" -type f -name '.*' -delete
find "${PLUGIN_DIR}" -depth -type d -name '.*' -exec rm -rf {} +

(
	cd "${OUTPUT_DIR}"
	zip -rq "$(basename "${ZIP_FILE}")" cf7-vk
)

echo "Built ${MODE} package directory: ${PLUGIN_DIR}"
echo "Built ${MODE} package zip: ${ZIP_FILE}"

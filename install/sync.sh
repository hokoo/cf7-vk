#!/bin/bash
# Deprecated

. ./.env

PARAM=""

if [ -n "$1" ]; then
  PARAM=$1
fi

echo "Synchronizing plugin-dir into wordpress/wp-content/plugins/cf7-vk"

[ ! -d ./wordpress/wp-content/plugins/cf7-vk ] && mkdir -p ./wordpress/wp-content/plugins/cf7-vk
rsync -cav"${PARAM}" --delete --exclude=.idea --exclude=.git ./plugin-dir/ ./wordpress/wp-content/plugins/cf7-vk/

echo "Done"

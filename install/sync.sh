#!/bin/bash
# Deprecated

. ./.env

PARAM=""

if [ -n "$1" ]; then
  PARAM=$1
fi

echo "Synchronizing plugin-dir into wordpress/wp-content/plugins/message-bridge-for-contact-form-7-and-vk"

[ ! -d ./wordpress/wp-content/plugins/message-bridge-for-contact-form-7-and-vk ] && mkdir -p ./wordpress/wp-content/plugins/message-bridge-for-contact-form-7-and-vk
rsync -cav"${PARAM}" --delete --exclude=.idea --exclude=.git ./plugin-dir/ ./wordpress/wp-content/plugins/message-bridge-for-contact-form-7-and-vk/

echo "Done"

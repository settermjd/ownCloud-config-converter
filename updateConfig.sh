#!/bin/sh

set -o nounset
set -o errexit

## ----------------------------------------------------------------- ##
BRANCHES=( stable9 stable9.1 master )
CURRENT_DIR=$(pwd)
FILE_PATH=/admin_manual/configuration_server/
LOG_FILE=output.log
LOCKFILE=process.lock
OUTPUT_BASE_DIR=/tmp/owncloud-documentation
CONFIG_SAMPLE=config.sample.php
REPOSITORY=git@github.com:owncloud/documentation.git
REPOSITORY_BASE_URL=https://raw.githubusercontent.com/owncloud/core
## ----------------------------------------------------------------- ##

function update_branches()
{
    git clone -q $REPOSITORY $OUTPUT_BASE_DIR || { echo "Unable to clone ${REPOSITORY}"; exit 1; }
    cd "$OUTPUT_BASE_DIR"

    for BRANCH in "$BRANCHES"
    do
        echo "Starting update on $BRANCH"

        git checkout -q "$BRANCH" || { echo "Unable to checkout branch ${BRANCH}"; exit 1; }

        cd "$CURRENT_DIR"

        curl -sS -o "/tmp/${CONFIG_SAMPLE}" "${REPOSITORY_BASE_URL}/$BRANCH/config/${CONFIG_SAMPLE}"

        php convert.php config:convert \
            --input-file="/tmp/${CONFIG_SAMPLE}" \
            --output-file="${OUTPUT_BASE_DIR}/admin_manual/configuration_server/config_sample_php_parameters.rst"

        cd "${OUTPUT_BASE_DIR}"

        if [ -n "$(git status -s)" ]; then
            echo "Push $BRANCH"
            git commit -qam 'generate documentation from config.sample.php'
            git push
        fi

        rm -rf "${CONFIG_SAMPLE}"

        echo "Finished update on $BRANCH"
    done
}

function script_setup()
{
    if [[ -d "$OUTPUT_BASE_DIR" ]]
    then
        rm -rf "$OUTPUT_BASE_DIR"
    fi
}

if [ ! -e $LOCKFILE ]; then
    trap "rm -f $LOCKFILE; exit" INT TERM EXIT
    touch $LOCKFILE
    script_setup
    update_branches
    rm $LOCKFILE
    trap - INT TERM EXIT
else
   echo "Conversion script is already running"
fi




#!/bin/sh

## ----------------------------------------------------------------- ##
BRANCHES=( stable9 stable9.1 master )
CURRENT_DIR=$(pwd)
FILE_PATH=/admin_manual/configuration_server/
OUTPUT_BASE_DIR=/tmp/owncloud-documentation
REPOSITORY=git@github.com:owncloud/documentation.git
REPOSITORY_BASE_URL=https://raw.githubusercontent.com/owncloud/core
## ----------------------------------------------------------------- ##

if [[ -d $OUTPUT_BASE_DIR ]]
then
    rm -rf $OUTPUT_BASE_DIR
fi

git clone -q $REPOSITORY $OUTPUT_BASE_DIR
cd $OUTPUT_BASE_DIR

for BRANCH in $BRANCHES
do
    git checkout -q $BRANCH
    cd $CURRENT_DIR

    curl -sS -o "/tmp/config.sample.php" "${REPOSITORY_BASE_URL}/$BRANCH/config/config.sample.php"

    php convert.php config:convert \
        --input-file="/tmp/config.sample.php" \
        --output-file="${OUTPUT_BASE_DIR}${FILE_PATH}config_sample_php_parameters.rst"

    cd "${OUTPUT_BASE_DIR}"

    if [ -n "$(git status -s)" ]; then
        echo "Push $BRANCH"
        git commit -qam 'generate documentation from config.sample.php'
        git push
    fi

    rm -rf "/tmp/config.sample.php"
done

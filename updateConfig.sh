#!/bin/bash

##
## The MIT License (MIT)
##
## Copyright (c) 2014 Morris Jobke <hey@morrisjobke.de>
##
## Permission is hereby granted, free of charge, to any person obtaining a copy
## of this software and associated documentation files (the "Software"), to deal
## in the Software without restriction, including without limitation the rights
## to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
## copies of the Software, and to permit persons to whom the Software is
## furnished to do so, subject to the following conditions:
##
## The above copyright notice and this permission notice shall be included in
## all copies or substantial portions of the Software.
##
## THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
## IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
## FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
## AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
## LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
## OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
## SOFTWARE.
##

##
## This script extracts the code comments out of ownCloud's ##
## config/config.sample.php and creates
## admin_manual/configuration/server/config_sample_php_parameters.rst.
## from that information.
##
## Author: Matthew Setter <matthew@matthewsetter.com>
## Author: Morris Jobke <hey@morrisjobke.de>
## Copyright (c) 2018, ownCloud GmbH.
##

set -o nounset
set -o errexit

## ----------------------------------------------------------------- ##
BRANCHES=( master )
CURRENT_DIR=$(pwd)
FILE_PATH=/admin_manual/configuration/server
LOG_FILE=output.log
LOCKFILE=process.lock
OUTPUT_BASE_DIR=/tmp/owncloud-documentation
CONFIG_SAMPLE=config.sample.php
REPOSITORY=git@github.com:owncloud/documentation.git
REPOSITORY_BASE_URL=https://raw.githubusercontent.com/owncloud/core
## ----------------------------------------------------------------- ##

## 
## Creates a copy of config_sample_php_parameters.rst for each of the branches
## nominated in $BRANCHES.
##
## Todo:
##   - Add switch to enable/disable verbose/quiet output for clone process (and others)
##   - Check if a branch has already been cloned
##   - Get the branches list from an external file
##
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

        # Make a doc copy of the file from the original source
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

##
## Perform any script setup work.
##
function script_setup()
{
    if [[ -d "$OUTPUT_BASE_DIR" ]]
    then
        rm -rf "$OUTPUT_BASE_DIR"
    fi
}

##
## Handle subsequent invocations of the script when it is already running as
## well as removal of the lockfile if the script is abnormally terminated.
##
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




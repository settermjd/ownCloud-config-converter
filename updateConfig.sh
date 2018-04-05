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
COMMIT_MESSAGE="Regenerate config_sample_php_parameters.rst from config.sample.php in ownCloud core"
CONFIG_SAMPLE=config.sample.php
CORE_BRANCH=
CURRENT_DIR=$(pwd)
DOCS_REPOSITORY=git@github.com:owncloud/documentation.git
DOCUMENTATION_BRANCH=
FILE_PATH=admin_manual/configuration/server
LOCAL_EXPORT_ONLY=false
LOCKFILE=process.lock
OUTPUT_BASE_DIR=/tmp/owncloud-documentation
REPOSITORY_BASE_URL=https://raw.githubusercontent.com/owncloud/core
VERBOSE=false
## ----------------------------------------------------------------- ##

##
## Perform any script setup work.
##
function setup()
{
  # Remove any previous exports
  if [[ -d "$OUTPUT_BASE_DIR" ]]
  then
    rm -rf "$OUTPUT_BASE_DIR"
  fi

  if [[ -e "/tmp/${CONFIG_SAMPLE}" ]]
  then
    rm -rvf "/tmp/${CONFIG_SAMPLE}"
  fi
}

function cleanup()
{
  rm -f $LOCKFILE
}

##
## Show the script's usage message.
##
usage() { 
  echo "$(basename "$0") [-h] [-l] [-v] [-c branch name] [-d branch name] 
  
This script updates configuration/server/config_sample_php_parameters.rst in
ownCloud's administration manual by exporting config/config.sample.php in
ownCloud 'core' repository.

where:
    -c  the ownCloud core repository branch to export config.sample.php from.
    -d  the ownCloud documentation repository branch to push the generated config_sample_php_parameters.rst file to.
    -h  show this help text.
    -l  creates a local export only, The exported file won't update the existing ownCloud administration manual.
    -v  verbose

If you specify either the core or documentation branches, you have to specify both." 1>&2; 

  exit 1; 
}

function clone_documentation_repo()
{
  echo "Cloning $DOCS_REPOSITORY to $OUTPUT_BASE_DIR"
  git clone $DOCS_REPOSITORY $OUTPUT_BASE_DIR || { echo "Unable to clone ${DOCS_REPOSITORY}"; exit 1; }
  echo "Finished clone"
  cd "$OUTPUT_BASE_DIR"
}

## 
## Creates a copy of config_sample_php_parameters.rst for a single branch
##
## Todo:
##   - Add switch to enable/disable verbose/quiet output for clone process (and others)
##   - Check if a branch has already been cloned
##   - Get the branches list from an external file
##
function update_branch()
{
  BRANCH_DOCS="$1"
  BRANCH_CORE="$2"

  echo "Exporting config_sample_php_parameters.rst from ownCloud core [${BRANCH_CORE}] into documentation [${BRANCH_DOCS}]."

  # Checkout the branch that we want to start from
  git checkout -q "${BRANCH_DOCS}" || { echo "Unable to checkout branch ${BRANCH_DOCS}"; exit 1; }
  
  # Create a feature branch from that repo.
  git checkout -q --track -b config_sample_php_parameters_update

  cd "${CURRENT_DIR}"

  curl -sS -o "/tmp/${CONFIG_SAMPLE}" "${REPOSITORY_BASE_URL}/${BRANCH_CORE}/config/${CONFIG_SAMPLE}"

  # Make a doc copy of the file from the original source
  php convert.php config:convert \
    --input-file="/tmp/${CONFIG_SAMPLE}" \
    --output-file="${OUTPUT_BASE_DIR}/${FILE_PATH}/config_sample_php_parameters.rst"

  cd "${OUTPUT_BASE_DIR}"
  echo

  if [ "$LOCAL_EXPORT_ONLY" = true ]
  then
    echo "Skipping commit and push of exported config_sample_php_parameters.rst"
  else 
    git_status=$(git status -s)
    if [ -n "$git_status" ]
    then

      if [ $VERBOSE = true ]; then
        echo "Changes made: ${git_status}"
      fi

      echo "Committing changes to config_sample_php_parameters.rst"
      git commit --quiet --all --message="$COMMIT_MESSAGE"

      echo "Pushing commit to remote branch"
      git push -v origin config_sample_php_parameters_update
    fi
  fi

  echo "Finished exporting config.sample.php from core/${BRANCH_CORE} into documentation/${BRANCH_DOCS}."
  echo "Exported file stored in ${OUTPUT_BASE_DIR}/${FILE_PATH}/config_sample_php_parameters.rst"
}

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
  for BRANCH in $BRANCHES
  do
    update_branch "$BRANCH" "$BRANCH"
  done
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




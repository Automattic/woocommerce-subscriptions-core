#!/usr/bin/env bash

# Prospress/WordPress Coding Standards
# @link https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards
# @link http://pear.php.net/package/PHP_CodeSniffer/
# --ignore: Folders to ignore
# -p flag: Show progress of the run.
# -s flag: Show sniff codes in all reports.
# -v flag: Print verbose output.
# -n flag: Do not print warnings. (shortcut for --warning-severity=0)
# --standard: Use Prospress as the standard.
# --extensions: Only sniff PHP files.

if [[ ${RUN_PHPCS} == 1 ]]; then
    IGNORE="*/tmp/*,*/tests/*,*/node_modules/*,*/libraries/*,*/woo-includes/*,build/index.asset.php"

	# Prospress standards check.
	if [[ -z ${PHPCS_RANGE} ]]; then
		tmp/php-codesniffer/scripts/phpcs --ignore=$IGNORE --encoding=utf-8 -p -s -v -n --standard=Prospress --extensions=php .
		exit
	fi

	# WC standards check.
	composer install

	if [[ ${PHPCS_RANGE} == "commits" ]]; then
      # \ escaped as \\\\ inside backticks (see https://mywiki.wooledge.org/BashFAQ/082).
      CHANGED_FILES=`git diff --name-only --diff-filter=ACMR $TRAVIS_COMMIT_RANGE | grep \\\\.php | awk '{print}' ORS=' '`

      if [ "$CHANGED_FILES" != "" ]; then
        ./includes/libraries/bin/phpcs -p -v $CHANGED_FILES
      fi
    else
      ./includes/libraries/bin/phpcs -p -v
    fi

fi

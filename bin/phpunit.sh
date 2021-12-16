#!/bin/bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

if [ -f "phpunit.phar" ]; then php phpunit.phar --version; else ./vendor/bin/phpunit --version; fi;

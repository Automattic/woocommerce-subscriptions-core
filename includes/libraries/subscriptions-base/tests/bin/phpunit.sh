#!/usr/bin/env bash
if [[ ${RUN_PHPCS} == 1 ]]; then
	exit
fi

if [[ ${RUN_CODE_COVERAGE} == 1 ]]; then
    phpdbg -qrr `which phpunit` -c phpunit.xml.dist --coverage-clover clover.xml;
else
    phpunit -c phpunit.xml.dist;
fi

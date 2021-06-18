#!/usr/bin/env bash

if [[ ${RUN_PHPCS} == 1 ]]; then
    # Search for PHP syntax errors.
    find . \( -path ./tmp -o -path ./tests \) -prune -o \( -name '*.php' \) -exec php -lf {} \;
fi

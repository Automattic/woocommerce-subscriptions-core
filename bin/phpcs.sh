#!/usr/bin/env bash

ROOTDIR="$(dirname "$(dirname "$0")")"
echo $ROOTDIR;
$ROOTDIR/vendor/bin/phpcs --report=json $@ | $ROOTDIR/vendor/bin/sarb remove phpcs.baseline
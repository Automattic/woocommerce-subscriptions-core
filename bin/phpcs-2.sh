#!/usr/bin/env bash

ROOTDIR="$(dirname "$(dirname "$0")")"
echo $ROOTDIR

# Run PHP CodeSniffer and capture the output
PHPCS_OUTPUT=$($ROOTDIR/vendor/bin/phpcs --report=json "$@")

# Check if the output is valid JSON
if echo "$PHPCS_OUTPUT" | jq empty >/dev/null 2>&1; then
  # If valid JSON, pass it to sarb
  echo "$PHPCS_OUTPUT" | $ROOTDIR/vendor/bin/sarb remove phpcs.baseline
else
  # If not valid JSON, print an error message and the invalid output
  echo "Failed to parse analysis results. Not valid JSON."
  echo "$PHPCS_OUTPUT"
  exit 1
fi

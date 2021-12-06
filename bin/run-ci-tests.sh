#!/bin/bash

# set strict mode for bash
set -euo pipefail
IFS=$'\n\t'

# Install dependencies and remove sarb as it creates problems on php 7.0
composer self-update 2.0.6 \
  && composer remove --dev "dave-liddament/sarb" \
  && composer install --no-progress
sudo systemctl start mysql.service
bash bin/install-wp-tests.sh woocommerce_test root root localhost $WP_VERSION $WC_VERSION false
echo 'Running the tests...'
bash bin/phpunit.sh

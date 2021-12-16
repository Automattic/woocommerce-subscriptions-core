#!/usr/bin/env bash

set -e

echo "Installing the test environment..."

docker-compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-subscriptions-core/bin/install-wp-tests.sh \
	woocommerce_subscriptions_core_tests wordpress $MYSQL_ROOT_PASSWORD

echo "Running the tests..."

docker-compose exec -u www-data wordpress \
	php -d xdebug.remote_autostart=on \
	/var/www/html/wp-content/plugins/woocommerce-subscriptions-core/vendor/bin/phpunit \
	--configuration /var/www/html/wp-content/plugins/woocommerce-subscriptions-core/phpunit.xml.dist \
	$*

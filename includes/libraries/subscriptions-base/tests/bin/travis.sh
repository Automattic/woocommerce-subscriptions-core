#!/usr/bin/env bash
# usage: travis.sh before|after

if [[ $1 == "before" ]]; then

	if  [[ ( $TRAVIS_PHP_VERSION = "5.3" ) || ( $TRAVIS_PHP_VERSION = "5.4" ) || ( $TRAVIS_PHP_VERSION = "5.5" ) ]]; then
		# Install phpunit 4
		wget -c https://phar.phpunit.de/phpunit-4.phar
		chmod +x phpunit-4.phar
		mv phpunit-4.phar `which phpunit`
	elif [[ ( $TRAVIS_PHP_VERSION = "5.6" ) || ( $WP_VERSION = "4.7" ) ]]; then
		# Install phpunit 5 for PHP 5.6 version or WP 4.7.
		wget -c https://phar.phpunit.de/phpunit-5.phar
		chmod +x phpunit-5.phar
		mv phpunit-5.phar `which phpunit`
	else
		# Install phpunit 6.
		wget -c https://phar.phpunit.de/phpunit-6.phar
		chmod +x phpunit-6.phar
		mv phpunit-6.phar `which phpunit`
	fi

	# Delete xdebug.
	phpenv config-rm xdebug.ini

	# Determine what WC version to use.
	if [[ "$WC_VERSION" = 'latest' ]]; then
		VERSION="$(curl --silent http://plugins.svn.wordpress.org/woocommerce/trunk/readme.txt | grep 'Stable tag:' | sed -E 's/.*: (.*)/\1/')"
		export WC_LATEST="${VERSION}"
	else
		VERSION="${WC_VERSION}"
	fi

	# place a copy of woocommerce where the unit tests etc. expect it to be
	git clone --depth 1 --branch="${VERSION}" git@github.com:woocommerce/woocommerce.git '../woocommerce'
fi


if [[ $1 == 'after' ]]; then
	if [[ ${RUN_CODE_COVERAGE} == 1 ]]; then
		bash <(curl -s https://codecov.io/bash)
	fi
fi

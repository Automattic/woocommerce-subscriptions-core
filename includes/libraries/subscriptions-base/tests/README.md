# WooCommerce Subscriptions Tests

This guide provides instructions on setting up and running the WooCommerce Subscriptions test suite.

Instead of attemping to write instructions that work for all local development environments, for example [Chasis](https://github.com/Chassis/Chassis), [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV), [MAMP](https://www.mamp.info/), [Pressmatic](https://pressmatic.io/) or any other tool.

Instead, this guide is written exclusively for setting up and running the test suite locally with [Valet](https://laravel.com/docs/5.2/valet).

## Initial Setup

Before running the test suite, you need to run a one-time setup. This process requires all of the following:

1. Setup a local WordPress site with Valet and required plugins
1. Install PHPUnit
1. Check other dependencies
1. Setup the WooCommerce Subscriptions test suite

### Step 1: Setup Valet

To continue with this guide, you will need to have a local WordPress installation setup and running with [Valet](https://laravel.com/docs/5.2/valet).

If do not have a site running through Valet, set one up using one of the the following options:

1. Install [Prospress Valet](https://github.com/prospress/prospress-valet/), if you have access to this repository, and run `pv` to provision a site with all the required plugins
1. Install [ValetPress](https://github.com/AaronRutley/valetpress) and run `vp create` to provision a site, then:
	1. Clone the [WooCommerce](https://github.com/woocommerce/woocommerce/) development repository (you can not use the distributed plugin, you must install the development version)
	1. Clone the [WooCommerce Subscriptions](https://github.com/woocommerce/woocommerce/) development repository

To confirm your have a local site running correctly, visit the site's URL in your browser.

### Step 2: Install PHPUnit

After installing Valet, you need to install [PHPUnit](http://phpunit.de/) to run tests.

Two options to install PHPUnit:

1. Install via [Homebrew](http://brew.sh/) with the command `brew install phpunit`
1. Install by following the [PHPUnit installation guide](https://phpunit.de/getting-started.html)

Homebrew is the preferred option as it is simpler to both install initially and update PHPUnit in the future.

### Step 3: Check Dependencies

One final step before setting up the test suite: your system needs to have all required dependencies.

Firstly, make sure you have the development version of the WooCommerce plugin installed in the same parent directory as your WooCommerce Subscriptions extension (i.e. `/wp-content/plugins/`). You will need WooCommerce 2.3 or newer from the official github repo as it includes unit testing framework and helper methods relied upon by these tests.

If you have the correct version of WooCommerce installed, you will see the following directly: `/wp-content/plugins/woocommerce/tests/`.

In addition to WooCommerce, the setup and running the test suite requires some executables to be installed on your system and accessible from the command line.

To ensure your system has the executable dependencies, run the following commands:

1. `php --version`
1. `phpunit --version`
1. `mysqladmin --version`

If any of these are not available, they can be installed via [Homebrew](http://brew.sh/).


### Step 4: Setup Test Suite

Now that dependencies are all setup, to setup the actual test suite:

1. Go to the WooCommerce Subscriptions plugin root directory in terminal, i.e. `cd /wp-content/plugins/woocommerce-subscriptions/`
1. Check out the `trunk` branch (really, this can be run from any branch, but `trunk` is safest)
1. Run the `install.sh` script by typing: `tests/bin/install.sh <db-name> <db-user> <db-password> [db-host]`

If you see the following message, the installation completed successfully:

    $ *** Test suite installation complete. Happy testing. ***

#### Step 4.1: Script Parameters

The install script accepts the following parameters:

* `<db-name>`: the name of a new database specifically for running tests
* `<db-user>`: your MySQL's local user account, most likely `root`
* `<db-password>`: the password for your MySQL's local user account, most likely `root` or blank
* `[db-host]`: (_optional_) the host to set on the database, like `127.0.0.1`. If you're unsure about this, set it to `127.0.0.1`.

#### Step 4.2: Sample usage

The following will create a `wcs_tests` database to run :

    $ tests/bin/install.sh wcs_tests_yolo root root 127.0.0.1

#### Step 4.3: Install Script Details

This install script will:

1. Create a new `tmp` directory within the WooCommerce Subscriptions plugin root directory, e.g. `/wp-content/plugins/woocommerce-subscriptions/tmp/`
1. Download WordPress into the `tmp` directory
1. Download the [WordPress Unit Test library](https://develop.svn.wordpress.org/trunk/) into the `tmp` directory
1. Download [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) into the `tmp` directory
1. Download [WordPress Coding Standards](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) and [Prospress Coding Standards](https://github.com/Prospress/prospress-coding-standards) Codesniffer components into the `tmp` directory
1. Create a new database with the `<db-name>` specified in the script parameters.

Additionally a copy of Codeception will be downloaded to the plugin root dir and the local configuration files for it i.e. `codeception.yml` and `test/acceptance.suite.yml` will be copied to their correct locations. Both of these files are ignored in the git configuation so that you can have a different configuration when testing locally to that used with our automated testing through Travis.

**Important**: If a database exists with the same name as the `<db-name>` specified in the script parameters, an error will be displayed. The error will look something like:

    $ mysqladmin: CREATE DATABASE failed; error: 'Can't create database '<db-name>'; database exists'

All data will be removed from this database after each test run. To avoid accidentally overwriting existing data, the script requires a new database.

If you see this error, the rest of the installation script has completed correct and you simply need to manually createa  new database with a command like:

    $ mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS" --host=127.0.0.1

## Running Unit Tests

To run the unit test suite, change to the Subscriptions plugin root directory and type:

    $ phpunit

The tests will execute and you'll be presented with a summary of the results.

You can also run specific tests by providing the path and filename to the test class, e.g.

    $ phpunit tests/unit-tests/api/wcs-api-subscriptions.php

## Running Coding Standards Checks

To run the syntax checks, change to the Subscriptions plugin root directory and type:

	tmp/php-codesniffer/scripts/phpcs -p -s -v -n . --standard=Prospress --extensions=php --ignore=*/tmp/*,*/tests/*,*/node_modules/*,*/libraries/*,*/woo-includes/*,*/build/*

This will test the plugin's code against the WordPress-Extra coding standards rule set with some additional Prospress tweaks and present a summary.

You can also run phpcs tests against specific files by using a command like:

    $ tmp/php-codesniffer/scripts/phpcs -p -s -v -n includes/class-wc-subscriptions-switcher.php --standard=Prospress
    
Similarly, you can use the "code beautifier" (phpcbf) that comes bundled with PHP_CodeSniffer by replacing `phpcs` with `phpcbf` in the commands above like:

    $ tmp/php-codesniffer/scripts/phpcbf -p -s -v -n includes/class-wc-subscriptions-switcher.php --standard=Prospress
    
**Note:** you will want to double check the automated fixes - mainly for off whitespace edits.

You can choose the following other rule sets to test against by adjusting the standard option in the above command.

* WordPress
* WordPress-Core
* WordPress-Extra
* WordPress-VIP

**Updating Rule Sets:** The rule sets are occassionally updated. To update your local rule sets, delete the `tmp` directory and run the `install.sh` script again.

## Test for PHP Syntax Errors

To test for PHP syntax errors, change to the Subscriptions plugin root directory and type:

	find . \( -path ./tmp -o -path ./tests \) -prune -o \( -name '*.php' \) -exec php -lf {} \;
	
This will find all the `.php` files excluding the `tmp` and `tests` directories and run them through the PHP CLI.

## Acceptance Testing with Codeception

For local testing with Codeception we need to update the local configuration (`codeception.yml` and `test/acceptance.suite.yml`) to point to the desired WordPress install.

You are welcome to use a local installation or you can make use of one of the sites on http://gimme.subscription.beer.

Once you have updated the configuration in `codeception.yml` and `test/acceptance.suite.yml` simply run the tests by typing:

	$ php codecept.phar run [--no-rebuild]
	
The `--no-rebuild` option is optional and simply disables the automated rebuild triggered if your configuration is updated. This is used to avoid issues like: http://phptest.club/t/gitignore-acceptancetester-functionaltester-unittester/244

**Important**: Codeception requires PHP 5.4+. 

## Writing Tests

* Each test file should roughly correspond to an associated source file, e.g. the `wcs_test_time_functions.php` test file covers code in the `wcs-time-functions.php` file
* Each test method should cover a single method or function with one or more assertions
* A single method or function can have multiple associated test methods if it's a large or complex method
* In addition to covering each line of a method/function, make sure to test common input and edge cases.
* Prefer `assertsEquals()` where possible as it tests both type & equality
* Remember that only methods prefixed with `test` will be run by PHPUnit so use helper methods liberally to keep test methods small and reduce code duplication.
* Filters persist between test cases so be sure to remove them in your test method or in the `tearDown()` method.

## Continuous Integration

The unit tests and checks on coding standards are automatically run with [Travis-CI](https://travis-ci.org) for each pull request.

## Code Coverage

Code coverage is available on [Codecov](https://codecov.io/) which receives updated data after each Travis build.

## Using Grunt

An alternative approach to running the unit tests and checks on coding standards is to use [Grunt](http://gruntjs.com/).

To use this approach:

1) Install [Node.js & NPM](https://nodejs.org/) and [Grunt](http://gruntjs.com/getting-started) by following the standard installation instructions or using [Homebrew](http://brew.sh/).

If you've installed NPM correctly, this should display the version:

    $ npm --version

If you've install Grunt correctly, this should display the version:

	$ grunt --version
	
2) Change to the Subscriptions plugin root directory.

Install the Subscriptions Grunt dependencies by running the `npm` installer with the following command:

	$ npm install --dev
	
3) Run the tests with

	$ grunt test --force
	
The `--force` tells grunt to continue next tasks even if one fails.

## Troubleshooting

1) When you run the install.sh script, if you see `tests/bin/install.sh: line 157: mysqladmin: command not found`:

* make sure your local MySQL server is running with the command `mysql.server start`
* make sure MySQL Admin is installed

If the error still persists, just continue and follow the steps in 2 below.

2) When running `phpunit`, if you come across an error such as `Error establishing a database connection` you might need to modify your `wp-tests-config.php` file.

Changes known to fix this issue are as follows:
  - Try using `127.0.0.1` as the database host when running `install.sh`
  - If using MAMP you might want to try set DB_HOST to `define( 'DB_HOST', 'localhost:/Applications/MAMP/tmp/mysql/mysql.sock' );` in your `wp-tests-config.php` file
  - Ensure ABSPATH is set to `/path/to/your/plugin/tmp/wordpress/`. For instance, mine is set to `/Users/Matt/Sites/Subs2.0/wp-content/plugins/woocommerce-subscriptions/tmp/wordpress/`
  - Make sure the Database specified in `wp-tests-config.php` exists. If not, create it and try run `phpunit` once again.

3) When running `phpunit`, if you receive an error in terminal like the one below, you likely have a version of WooCommerce in your `/wp-content/plugins/` directory prior to 2.3 (or WooCommerce is not installed).

```
Warning: require_once(/site_root/wp-content/plugins/woocommerce/tests/framework/helpers/class-wc-helper-product.php): failed to open stream: No such file or directory in /wp-content/plugins/woocommerce-subscriptions/tests/bootstrap.php on line 139
```


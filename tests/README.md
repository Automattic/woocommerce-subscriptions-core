# subscriptions-core Unit Tests

This guide follows the [WooCommerce guide to unit tests](https://github.com/woocommerce/woocommerce/tree/master/tests).

## Initial Setup for running tests locally

1. From the plugin directory, run `composer install` if you have not already:

```
$ composer install
```

2. Install WordPress and the WP Unit Test lib using the `bin/install-wp-tests.sh` script. From the plugin root directory type:

```
$ bin/install-wp-tests.sh <db-name> <db-user> <db-password> [db-host]
```

Tip: try using `127.0.0.1` for the DB host if the default `localhost` isn't working.

3. Run the tests from the plugin root directory using

```
$ ./vendor/bin/phpunit
```

### Tips

**Using Local by Flywheel**

If you have MySQL installed via a socket (like with Local), your install command will look something like this:

```
bin/install-wp-tests.sh <db-name> <db-user> <db-password> "localhost:/Users/{username}/Library/ApplicationSupport/Local/run/Qm1DpkUyd/mysql/mysqld.sock"
```

You can find the socket location in your Local database settings. 

<img width="500" alt="Screenshot 2024-04-12 at 10 29 07 am" src="https://github.com/Automattic/woocommerce-subscriptions-core/assets/8490476/fbd62f4e-de0f-4c20-b44c-c10365a1343f">

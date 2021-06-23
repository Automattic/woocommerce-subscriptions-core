# [WooCommerce Subscriptions](http://www.woocommerce.com/products/woocommerce-subscriptions/)

[![Build Status](https://api.travis-ci.com/woocommerce/woocommerce-subscriptions.svg?token=7qcKG8toQcpjnZpuJrFT&branch=trunk)](https://api.travis-ci.com/woocommerce/woocommerce-subscriptions) [![codecov.io](http://codecov.io/github/woocommerce/woocommerce-subscriptions/coverage.svg?token=SZMiHxYlfh&branch=trunk)](http://codecov.io/github/woocommerce/woocommerce-subscriptions?branch=trunk)

## Repositories

* The `/woocommerce/woocommerce-subscriptions/` repository is treated as a _development_ repository: this includes development assets, like unit tests and configuration files. Commit history for this repository includes all commits for all changes to the code base, not just for new versions.

## Deployment

A "_deployment_" in our sense means:
 * validating the version in the header and `WC_Subscriptions::$version` variable match
 * generating a `.pot` file for all translatable strings in the development repository
 * tagging a new version
 * cloning a copy of the `/woocommerce/woocommerce-subscriptions/` repo into a temporary directory
 * removing all development related assets, like this file, unit tests and configuration files
 * the changes will be pushed to a branch with the name `release/{version}` so that a PR can be issued on `/woocommerce/woocommerce-subscriptions/`

## Branches

* [`trunk`](https://github.com/woocommerce/woocommerce-subscriptions/tree/trunk) includes all code for the current version and any new pull requests merged that will be released with the next version. It can be considered stable for staging and development sites but not for production.
* `issue_{id}` branches are used for creating patches for a specific issue reported on the development repository and can not be considered stable.

## Additional resources

* [Testing readme](https://github.com/woocommerce/woocommerce-subscriptions/blob/trunk/tests/README.md)

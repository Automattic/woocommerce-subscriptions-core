#!/bin/bash

# Exit if any command fails.
set -e

WP_CONTAINER=${1-woocommerce_subscriptions_core_wordpress}
SITE_URL=${WP_URL-"localhost"}
WP_ADMIN=${WP_ADMIN-"admin"}
WP_ADMIN_PASSWORD=${WP_ADMIN_PASSWORD-"admin"}
WP_ADMIN_EMAIL=${WP_ADMIN_EMAIL-"admin@example.com"}

cli()
{
	docker-compose exec -u www-data wordpress "$@"
}

cli wp plugin is-active woocommerce-subscriptions-core
if [[ $? -eq 0 ]]; then
	set -e
	echo
	echo "WooCommerce Subscriptions Core is installed and active"
	echo "SUCCESS setting up docker!"
	exit 0
fi

echo
echo "Setting up environment..."
echo

echo "Setting up WordPress..."
cli wp core install \
	--path=/var/www/html \
	--url=$SITE_URL \
	--title="WooCommerce Subscriptions Core" \
	--admin_name=$WP_ADMIN \
	--admin_password=$WP_ADMIN_PASSWORD \
	--admin_email=$WP_ADMIN_EMAIL \
	--skip-email

echo "Updating WordPress to the latest version..."
cli wp core update

echo "Updating the WordPress database..."
cli wp core update-db

echo "Enabling WordPress debug flags"
cli config set WP_DEBUG true --raw
cli config set WP_DEBUG_DISPLAY true --raw
cli config set WP_DEBUG_LOG true --raw
cli config set SCRIPT_DEBUG true --raw

echo "Updating permalink structure"
cli wp rewrite structure '/%postname%/'

echo "Installing and activating WooCommerce..."
cli wp plugin install woocommerce --activate

echo "Installing and activating Storefront theme..."
cli wp theme install storefront --activate

echo "Adding basic WooCommerce settings..."
cli wp option set woocommerce_store_address "60 29th Street"
cli wp option set woocommerce_store_address_2 "#343"
cli wp option set woocommerce_store_city "San Francisco"
cli wp option set woocommerce_default_country "US:CA"
cli wp option set woocommerce_store_postcode "94110"
cli wp option set woocommerce_currency "USD"
cli wp option set woocommerce_product_type "both"
cli wp option set woocommerce_allow_tracking "no"

echo "Importing WooCommerce shop pages..."
cli wp wc --user=admin tool run install_pages

echo "Activating the WooCommerce Subscriptions Core plugin..."
cli wp plugin activate woocommerce-subscriptions-core

echo "SUCCESS setting up docker!"


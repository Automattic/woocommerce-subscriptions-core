#!/usr/bin/env bash

cd ./tests/e2e/env/

echo "Booting up the WordPress Environment"
wp-env start 

echo "Updating permalink structure"
wp-env run cli "wp rewrite structure '/%postname%/'"

echo "Activating storefront theme..."
wp-env run cli "wp theme activate storefront"

echo "Adding basic WooCommerce settings..."
wp-env run cli "wp option set woocommerce_store_address '60 29th Street'"
wp-env run cli "wp option set woocommerce_store_address_2 '#343'"
wp-env run cli "wp option set woocommerce_store_city 'San Francisco'"
wp-env run cli "wp option set woocommerce_default_country 'US:CA'"
wp-env run cli "wp option set woocommerce_store_postcode '94110'"
wp-env run cli "wp option set woocommerce_currency 'USD'"
wp-env run cli "wp option set woocommerce_product_type 'both'"
wp-env run cli "wp option set woocommerce_allow_tracking 'no'"

echo "Importing WooCommerce shop pages..."
wp-env run cli "wp wc --user=admin tool run install_pages"

echo "Installing and activating the WordPress Importer plugin"
wp-env run cli "wp plugin install wordpress-importer --activate"

echo "Importing the WooCommerce sample data..."
wp-env run cli "wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip"

echo "Create a test customer..."
wp-env run cli "wp user create customer customer@example.com --user_pass=password --role=customer"

/**
 * External dependencies
 */
import { useMemo } from '@wordpress/element';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { PickupControl } from './pickup-control';

/**
 * This component is responsible for rending recurring shippings.
 * It has to be the highest level item directly inside the SlotFill
 * to receive properties passed from Cart and Checkout.
 *
 * extensions is data registered into `/cart` endpoint.
 *
 * @param {Object} props                       Passed props from SlotFill to this component.
 * @param {Object} props.extensions            Data registered into `/cart` endpoint.
 * @param {Object} props.components		       Passed from the parent, which is the child of a Slot/Fill in WC Blocks.
 * @param {Function} props.renderPickupLocation
 */
export const SubscriptionsPickupRecurringPackages = ( {
	extensions,
	components,
	renderPickupLocation = () => null,
} ) => {
	const { subscriptions = [] } = extensions;

	// Flatten all packages from recurring carts.
	const packages = useMemo(
		() =>
			Object.values( subscriptions )
				.map( ( recurringCart ) => recurringCart.shipping_rates )
				.filter( Boolean )
				.flat(),
		[ subscriptions ]
	);

	const collectableRateIds = getSetting( 'collectableMethodIds', [] );
	return packages.map( ( { package_id: packageId, ...packageData } ) => {
		packageData.shipping_rates = packageData.shipping_rates.filter(
			( { method_id: methodId } ) =>
				collectableRateIds.includes( methodId )
		);
		return (
			<PickupControl
				key={ packageId }
				components={ components }
				packageData={ packageData }
				renderPickupLocation={ renderPickupLocation }
			/>
		);
	} );
};

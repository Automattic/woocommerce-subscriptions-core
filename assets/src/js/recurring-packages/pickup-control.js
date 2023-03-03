/**
 * External dependencies
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './pickup-control.scss';

/**
 * This component is responsible for rending pickup options for recurring subscriptions.
 *
 * @param {Object} props                        Passed props from SlotFill to this component.
 * @param {Object} props.components			    Passed from the parent, which is the child of a Slot/Fill in WC Blocks.
 * @param {Function} props.renderPickupLocation A function passed from WC Blocks which renders the pickup location's address.
 * @param {Object} props.packageData			Contains the pickup rates for this package.
 */
export const PickupControl = ( {
	components,
	packageData,
	renderPickupLocation = () => null,
} ) => {
	const { LocalPickupSelect } = components;

	const [ selectedOption, setSelectedOption ] = useState(
		packageData.shipping_rates[ 0 ].rate_id
	);
	const onSelectRate = () => {};
	return (
		<div className="wc-subscriptions-pickup-control">
			<LocalPickupSelect
				title={ packageData.name }
				pickupLocations={ packageData.shipping_rates }
				onSelectRate={ onSelectRate }
				selectedOption={ selectedOption }
				renderPickupLocation={ renderPickupLocation }
				setSelectedOption={ setSelectedOption }
			/>
		</div>
	);
};

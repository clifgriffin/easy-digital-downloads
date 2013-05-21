<?php
/**
 * Tax Functions
 *
 * These are functions used for checking if taxes are enabled, calculating taxes, etc.
 * Functions for retrieving tax amounts and such for individual payments are in
 * includes/payment-functions.php and includes/cart-functions.php
 *
 * @package     EDD
 * @subpackage  Functions/Taxes
 * @copyright   Copyright (c) 2013, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3.3
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Checks if taxes are enabled by using the option set from the EDD Settings.
 * The value returned can be filtered.
 *
 * @since 1.3.3
 * @global $edd_options
 * @return bool Whether or not taxes are enabled
 */
function edd_use_taxes() {
	global $edd_options;

	return apply_filters( 'edd_use_taxes', isset( $edd_options['enable_taxes'] ) );
}


/**
 * Show taxes on individual prices?
 *
 * @since 1.4
 * @global $edd_options
 * @return bool Whether or not to show taxes on prices
 */
function edd_taxes_on_prices() {
	global $edd_options;
	return apply_filters( 'edd_taxes_on_prices', isset( $edd_options['taxes_on_prices'] ) );
}

/**
 * Checks if the user has enabled the option to calculate taxes after discounts
 * have been entered
 *
 * @since 1.4.1
 * @global $edd_options
 * @return bool Whether or not taxes are calculated after discount
 */
function edd_taxes_after_discounts() {
	global $edd_options;
	return apply_filters( 'edd_taxes_after_discounts', isset( $edd_options['taxes_after_discounts'] ) );
}

function edd_get_tax_rates() {

	$rates = get_option( 'edd_tax_rates', array() );
	return apply_filters( 'edd_get_tax_rates', $rates );
}


/**
 * Get taxation rate
 *
 * @since 1.3.3
 * @global $edd_options
 * @return float $trate Taxation rate
 */
function edd_get_tax_rate( $country = false, $state = false ) {
	global $edd_options;

	$rate = isset( $edd_options['tax_rate'] ) ? (float) $edd_options['tax_rate'] : 0;

	if( empty( $country ) )
		$country = isset( $_POST['country'] ) ? $_POST['country'] : false;

	if( empty( $state ) )
		$state = isset( $_POST['state'] ) ? $_POST['state'] : false;

	if( ! empty( $country ) && ! empty( $state ) ) {
		$tax_rates   = edd_get_tax_rates();

		// Locate the tax rate for this country / state, if it exists
		foreach( $tax_rates as $key => $tax_rate ) {

			if( $country != $tax_rate['country'] )
				continue;
			if( $state   != $tax_rate['state'] )
				continue;

			$state_rate = $tax_rate['rate'];
			if( ! empty( $state_rate ) ) {
				$rate = number_format( $state_rate, 2 );
			}
		}
	}

	if( $rate > 1 ) {
		// Convert to a number we can use
		$rate = $rate / 100;
	}
	return apply_filters( 'edd_tax_rate', $rate, $country, $state );
}

/**
 * Calculate the taxed amount
 *
 * @since 1.3.3
 * @param $amount float The original amount to calculate a tax cost
 * @return float $tax Taxed amount
 */
function edd_calculate_tax( $amount, $sum = true ) {
	global $edd_options;

	// Not using taxes
	if ( ! edd_use_taxes() ) return $amount;

	$rate = edd_get_tax_rate();
	$tax = 0.00;
	$prices_include_tax = edd_prices_include_tax();

	if ( $prices_include_tax ) {
		$tax = $amount - ( $amount / ( $rate + 1 ) );
	} else {
		$tax = $amount * $rate;
	}

	if ( $sum ) {

		if ( $prices_include_tax ) {
			$tax = $amount - $tax;
		} else {
			$tax = $amount + $tax;
		}

	}
	return apply_filters( 'edd_taxed_amount', round( $tax, 2 ), $rate );
}

/**
 * Stores the tax info in the payment meta
 *
 * @since 1.3.3
 * @param $year int The year to retrieve taxes for, i.e. 2012
 * @uses edd_get_sales_tax_for_year()
 * @return void
*/
function edd_sales_tax_for_year( $year = null ) {
	echo edd_currency_filter( edd_format_amount( edd_get_sales_tax_for_year( $year ) ) );
}

/**
 * Gets the sales tax for the current year
 *
 * @since 1.3.3
 * @param $year int The year to retrieve taxes for, i.e. 2012
 * @uses edd_get_payment_tax()
 * @return float $tax Sales tax
 */
function edd_get_sales_tax_for_year( $year = null ) {
	if ( empty( $year ) )
		return 0;

	// Start at zero
	$tax = 0;

	$args = array(
		'post_type' 		=> 'edd_payment',
		'posts_per_page' 	=> -1,
		'year' 				=> $year,
		'meta_key' 			=> '_edd_payment_mode',
		'meta_value' 		=> edd_is_test_mode() ? 'test' : 'live',
		'fields'			=> 'ids'
	);

	$payments = get_posts( $args );

	if( $payments ) :

		foreach( $payments as $payment ) :
			$tax += edd_get_payment_tax( $payment );
		endforeach;

	endif;

	return apply_filters( 'edd_get_sales_tax_for_year', $tax, $year );
}


/**
 * Checks whether the user has enabled display of taxes on the checkout
 *
 * @since 1.5
 * @global $edd_options
 * @return bool $include_tax
 */
function edd_prices_show_tax_on_checkout() {
	global $edd_options;

	return isset( $edd_options['checkout_include_tax'] ) && $edd_options['checkout_include_tax'] == 'yes';
}

/**
 * Check if the individual product prices include tax
 *
 * @since 1.5
 * @global $edd_options
 * @return bool $include_tax
*/
function edd_prices_include_tax() {
	global $edd_options;

	return isset( $edd_options['prices_include_tax'] ) && $edd_options['prices_include_tax'] == 'yes';
}

/**
 * Is the cart taxed?
 *
 * @since 1.5
 * @return bool
 */
function edd_is_cart_taxed() {
	return edd_use_taxes() && ( ( edd_local_tax_opted_in() && edd_local_taxes_only() ) || ! edd_local_taxes_only() );
}
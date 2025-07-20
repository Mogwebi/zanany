<?php
/**
 * Play Attributes Functions
 *
 * @package   play-block
 * @copyright Copyright (c) 2022, Flatfull
 * @license   GPL2+
 * @since     9.2
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Add a 'attribute'.
 *
 * @param array $data
 *
 * @return int|false ID of newly created 'attribute', false on error.
 */
function play_add_attribute( $data = array() ) {

	if ( empty( $data[ 'attribute_label' ] ) || empty( $data[ 'attribute_name' ] ) ) {
		return false;
	}

	do_action( 'play_pre_add_attribute', $data );

	// Instantiate a query object
	$attr_query = new Play_Block_Attribute_Query();

	$data = apply_filters( 'play_add_attribute', $data );

	$retval = $attr_query->add_item( $data );

	do_action( 'play_post_add_attribute', $retval, $data );

	return $retval;
}

function play_get_attribute( $object_id = 0 ) {
	$attr_query = new Play_Block_Attribute_Query();
	return $attr_query->get_item( $object_id );
}

/**
 * Delete a 'attribute'.
 *
 * @param int $object_id
 *
 * @return int|false `1` if the 'attribute' was deleted successfully, false on error.
 */
function play_delete_attribute( $object_id = 0 ) {
	$attr_query = new Play_Block_Attribute_Query();

	do_action( 'play_pre_delete_attribute', $object_id );

	$retval = $attr_query->delete_item( $object_id );

	do_action( 'play_post_delete_attribute', $retval, $object_id );

	return $retval;
}

/**
 * Update a 'attribute' row.
 *
 * @param int   $object_id
 * @param array $data
 *
 * @return int|false Number of rows updated if successful, false otherwise.
 */
function play_update_attribute( $object_id = 0, $data = array() ) {

	do_action( 'play_pre_update_attribute', $object_id, $data );

	$attr_query = new Play_Block_Attribute_Query();

	$data = apply_filters( 'play_update_attribute', $data, $object_id );

	$retval = $attr_query->update_item( $object_id, $data );

	do_action( 'play_post_update_attribute', $retval, $object_id, $data );

	return $retval;
}

function play_get_attributes( $args = array() ) {

	// Parse args
	$r = wp_parse_args( $args, array(
		'number' => 0
	) );

	// Instantiate a query object
	$attr_query = new Play_Block_Attribute_Query();

	// Return order coupons
	return $attr_query->query( $r );
}

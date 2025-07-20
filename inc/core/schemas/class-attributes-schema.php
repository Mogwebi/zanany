<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Play_Block_Attributes_Schema extends \BerlinDB\Database\Schema {

	public $columns = array(

		// id
		array(
			'name'       => 'id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'extra'      => 'auto_increment',
			'primary'    => true,
			'sortable'   => true
		),

		// attribute_name
		array(
			'name'       => 'attribute_name',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => '',
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// attribute_label
		array(
			'name'       => 'attribute_label',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => '',
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// attribute_public
		array(
			'name'       => 'attribute_public',
			'type'       => 'tinyint(1)',
			'default'    => 1
		),

		// attribute_hierarchical
		array(
			'name'       => 'attribute_hierarchical',
			'type'       => 'tinyint(1)',
			'default'    => 0
		),
		
		// post_type
		array(
			'name'       => 'post_type',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => '',
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

	);

}

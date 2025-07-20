<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Play_Block_Attributes_Table extends \BerlinDB\Database\Table {

	/**
	 * Table name, without the global table prefix.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $name = 'play_block_attributes';

	/**
	 * Database version key (saved in _options or _sitemeta)
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected $db_version_key = 'play_block_attributes_version';

	/**
	 * Optional description.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $description = 'Play Block Attributes';

	/**
	 * Database version.
	 *
	 * @since 1.0.0
	 * @var   mixed
	 */
	protected $version = '1.0.0';

	/**
	 * Key => value array of versions => methods.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	protected $upgrades = array();

	/**
	 * Setup this database table.
	 *
	 * @since 1.0.0
	 */
	protected function set_schema() {
		$this->schema = "id bigint(20) UNSIGNED NOT NULL auto_increment,
			attribute_name varchar(20) NOT NULL,
			attribute_label varchar(20) NULL,
			attribute_hierarchical int(1) NOT NULL DEFAULT 0,
			attribute_public int(1) NOT NULL DEFAULT 1,
			post_type varchar(20) NOT NULL,
			PRIMARY KEY (id),
			KEY `attribute_name` (attribute_name)";
	}
}

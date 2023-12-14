<?php

namespace TeraPixelNewsGenerator\EventRegistryNewsApi;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;
use Stackonet\WP\Framework\Supports\Validate;

class NewsSource extends DatabaseModel {
	/**
	 * Default data
	 *
	 * @var array
	 */
	protected static $default_data = [
		'title'        => '',
		'uri'          => '',
		'data_type'    => '',
		'copy_image'   => 1,
		'in_whitelist' => 1,
		'in_blacklist' => 0,
	];
	/**
	 * The table name
	 *
	 * @var string
	 */
	protected $table = 'news_sources';

	/**
	 * Create table
	 */
	public static function create_table() {
		global $wpdb;
		$self    = new static();
		$table   = $self->get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` varchar(250) NULL DEFAULT NULL,
                `uri` varchar(250) NULL DEFAULT NULL,
                `data_type` varchar(50) NULL DEFAULT NULL,
				`copy_image` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
				`in_whitelist` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
				`in_blacklist` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id),
    			UNIQUE `uri` (`uri`)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
	}

	public static function upgrade() {
		$version = get_option( 'news_sources_data_upgrade_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			$sources = Setting::get_news_sources();
			foreach ( $sources as $source ) {
				static::create_if_not_exists( $source );
			}

			update_option( 'news_sources_data_upgrade_version', '1.0.0' );
		}
	}

	/**
	 * Create a new record if not exists
	 *
	 * @param  array  $data
	 *
	 * @return NewsSource
	 */
	public static function create_if_not_exists( array $data ): NewsSource {
		$data = wp_parse_args( $data, static::$default_data );
		$item = static::find_by_uri( $data['uri'] );
		if ( $item ) {
			return $item;
		}
		$id   = static::create( $data );
		$item = static::find_by_id( $id );

		return new static( $item );
	}

	/**
	 * Find by uri
	 *
	 * @param  string  $uri  The URI
	 *
	 * @return false|static
	 */
	public static function find_by_uri( string $uri ) {
		$alt_uri = str_replace( [ 'http://', 'https://' ], '', $uri );
		$uris    = [
			$alt_uri,
			sprintf( 'http://%s', $alt_uri ),
			sprintf( 'https://%s', $alt_uri )
		];
		global $wpdb;
		$self  = new static();
		$table = $self->get_table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM $table WHERE uri = %s", $uri );
		foreach ( $uris as $_uri ) {
			$sql .= $wpdb->prepare( " OR uri = %s", $_uri );
		}
		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( is_array( $row ) ) {
			return new static( $row );
		}

		return false;
	}

	/**
	 * Create from news sync settings
	 *
	 * @return array
	 */
	public static function create_from_news_sync_settings(): array {
		$sources     = [];
		$raw_sources = Setting::get_news_sources();
		foreach ( $raw_sources as $source ) {
			$sources[] = static::create_if_not_exists( $source );
		}

		return $sources;
	}

	public function to_array(): array {
		$data                 = parent::to_array();
		$data['copy_image']   = $this->should_copy_image();
		$data['in_blacklist'] = Validate::checked( $data['in_blacklist'] );
		$data['in_whitelist'] = Validate::checked( $data['in_whitelist'] );

		return $data;
	}

	/**
	 * Should copy image
	 *
	 * @return bool
	 */
	public function should_copy_image(): bool {
		return Validate::checked( $this->get_prop( 'copy_image' ) );
	}
}

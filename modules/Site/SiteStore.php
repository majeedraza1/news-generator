<?php

namespace StackonetNewsGenerator\Modules\Site;

use Stackonet\WP\Framework\Abstracts\DataStoreBase;

class SiteStore extends DataStoreBase {
	protected $table = 'openai_news_sites';

	/**
	 * Find by URL
	 *
	 * @param string $site_url Site url.
	 *
	 * @return array|false
	 */
	public static function find_by_url( string $site_url ) {
		global $wpdb;
		$site_store = new static();
		$table      = $site_store->get_table_name();
		$sql        = $wpdb->prepare(
			"SELECT * FROM $table WHERE site_url = %s OR site_url LIKE %s",
			$site_url,
			rtrim( $site_url, '/' ) . '%'
		);
		$site_info  = $wpdb->get_row( $sql, ARRAY_A );

		return is_array( $site_info ) ? $site_info : false;
	}

	/**
	 * Send general data to sites
	 *
	 * @return void
	 */
	public static function send_general_data_to_sites() {
		$sites = ( new static )->find_multiple( [ 'per_page' => 100 ] );
		foreach ( $sites as $site ) {
			( new Site( $site ) )->send_general_data();
		}
	}

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
                `site_url` VARCHAR(255) NULL DEFAULT NULL,
                `auth_credentials` VARCHAR(255) NULL DEFAULT NULL,
                `auth_username` VARCHAR(50) NULL DEFAULT NULL,
                `auth_password` VARCHAR(255) NULL DEFAULT NULL,
                `auth_type` VARCHAR(50) NULL DEFAULT NULL,
                `auth_post_params` VARCHAR(50) NULL DEFAULT NULL,
                `auth_get_args` VARCHAR(50) NULL DEFAULT NULL,
				`last_sync_datetime` datetime NULL DEFAULT NULL,
				`sync_settings` LONGTEXT NULL DEFAULT NULL,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id),
    			INDEX `site_url` (`site_url`)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
	}
}

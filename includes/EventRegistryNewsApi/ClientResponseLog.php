<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;
use Stackonet\WP\Framework\Supports\Sanitize;

/**
 * ClientResponseLog class
 */
class ClientResponseLog extends DatabaseModel {
	/**
	 * Database table
	 *
	 * @var string
	 */
	protected $table = 'event_registry_news_logs';

	/**
	 * Get sync settings options
	 *
	 * @var array
	 */
	protected static $sync_settings = array();

	/**
	 * Get sync settings
	 *
	 * @return array
	 */
	public static function get_sync_settings(): array {
		if ( empty( static::$sync_settings ) ) {
			static::$sync_settings = SyncSettingsStore::get_settings_as_select_options();
		}

		return static::$sync_settings;
	}

	/**
	 * Array representation of the class
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data        = parent::to_array();
		$json_fields = array( 'news_articles', 'existing_records_ids', 'new_records_ids' );
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $json_fields, true ) ) {
				$data[ $key ] = json_decode( $value, true );
			}
		}

		if ( ! empty( $data['sync_setting_id'] ) ) {
			$sync_settings              = static::get_sync_settings();
			$data['sync_setting_title'] = $sync_settings[ $data['sync_setting_id'] ] ?? '';
		}

		return $data;
	}

	/**
	 * Get log
	 *
	 * @param  array  $data  The data to store.
	 *
	 * @return int
	 */
	public static function add_log( array $data ): int {
		return static::create(
			array(
				'news_articles'        => wp_json_encode( Sanitize::deep( $data['news_articles'] ) ),
				'existing_records_ids' => wp_json_encode( array_map( 'intval', $data['existing_records_ids'] ) ),
				'new_records_ids'      => wp_json_encode( array_map( 'intval', $data['new_records_ids'] ) ),
				'total_items'          => count( $data['news_articles'] ),
				'sync_setting_id'      => sanitize_text_field( $data['sync_setting_id'] ),
			)
		);
	}

	/**
	 * Delete old logs
	 *
	 * @param  int  $day  Number of days.
	 *
	 * @return bool
	 */
	public static function delete_old_logs( int $day = 3 ): bool {
		global $wpdb;
		$table = static::get_table_name();
		$time  = time() - ( max( 1, $day ) * DAY_IN_SECONDS );

		return (bool) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE created_at <= %s",
				gmdate( 'Y-m-d H:i:s', $time )
			)
		);
	}

	/**
	 * Create database table
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		$self    = new static();
		$table   = $self->get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `existing_records_ids` text NULL DEFAULT NULL COMMENT 'Existing article ids.',
                `new_records_ids` text NULL DEFAULT NULL COMMENT 'New articles ids.',
                `news_articles` longtext NULL DEFAULT NULL COMMENT 'Article body',
				`total_items` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    			`sync_setting_id` varchar(50) NULL DEFAULT NULL,
    			`created_at` DATETIME NULL DEFAULT NULL,
    			`updated_at` DATETIME NULL DEFAULT NULL,
				PRIMARY KEY (id)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
	}
}

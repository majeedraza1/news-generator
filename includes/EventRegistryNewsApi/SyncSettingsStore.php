<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;
use Stackonet\WP\Framework\Supports\Validate;

/**
 * SyncSettingsStore class
 */
class SyncSettingsStore extends DatabaseModel {
	/**
	 * The table name
	 *
	 * @var string
	 */
	protected $table = 'event_registry_sync_settings';

	/**
	 * To array
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data                           = parent::to_array();
		$data['title']                  = $this->get_title();
		$data['option_id']              = $this->get_uuid();
		$data['copy_news_image']        = $this->should_copy_image();
		$data['enable_category_check']  = $this->should_check_category();
		$data['enable_live_news']       = $this->is_live_news_enabled();
		$data['enable_news_filtering']  = $this->is_news_filtering_enabled();
		$data['rewrite_title_and_body'] = $this->rewrite_title_and_body();
		$data['rewrite_metadata']       = $this->rewrite_metadata();

		return $data;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function get_title(): string {
		$title = (string) $this->get_prop( 'title' );
		if ( empty( $title ) ) {
			return sprintf( 'Setting %s', $this->get_id() );
		}

		return $title;
	}

	/**
	 * Get setting UUID
	 *
	 * @return string
	 */
	public function get_uuid(): string {
		return (string) $this->get_prop( 'option_id' );
	}

	/**
	 * If it should rewrite news title and body
	 *
	 * @return bool
	 */
	public function rewrite_title_and_body(): bool {
		return Validate::checked( $this->get_prop( 'rewrite_title_and_body', true ) );
	}

	/**
	 * If it should rewrite news title and body
	 *
	 * @return bool
	 */
	public function rewrite_metadata(): bool {
		return Validate::checked( $this->get_prop( 'rewrite_metadata', true ) );
	}

	/**
	 * If it should use actual news
	 *
	 * @return bool
	 */
	public function use_actual_news(): bool {
		return ( false === $this->rewrite_title_and_body() && false === $this->rewrite_metadata() );
	}

	/**
	 * If it should filter news
	 *
	 * @return bool
	 */
	public function is_news_filtering_enabled(): bool {
		return Validate::checked( $this->get_prop( 'enable_news_filtering' ) );
	}

	/**
	 * If it is a live news
	 *
	 * @return bool
	 */
	public function is_live_news_enabled(): bool {
		return Validate::checked( $this->get_prop( 'enable_live_news' ) );
	}

	/**
	 * If it should copy news from source
	 *
	 * @return bool
	 */
	public function should_copy_image(): bool {
		return Validate::checked( $this->get_prop( 'copy_news_image' ) );
	}

	/**
	 * Should check category
	 *
	 * @return bool
	 */
	public function should_check_category(): bool {
		return Validate::checked( $this->get_prop( 'enable_category_check' ) );
	}

	/**
	 * Get settings
	 *
	 * @param  int  $per_page  Number of items to return.
	 *
	 * @return array|SyncSettingsStore[]
	 */
	public static function get_settings( int $per_page = 100 ): array {
		return static::find_multiple(
			array(
				'per_page' => $per_page,
				'order_by' => array(
					array(
						'field' => 'id',
						'order' => 'ASC',
					),
				),
			)
		);
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public static function get_settings_as_array(): array {
		$data = array();
		foreach ( static::get_settings() as $setting ) {
			$data[] = $setting->to_array();
		}

		return $data;
	}

	/**
	 * Get settings as select options
	 *
	 * @return array
	 */
	public static function get_settings_as_select_options(): array {
		$data = array();
		foreach ( static::get_settings() as $setting ) {
			$data[ $setting->get_uuid() ] = $setting->get_title();
		}

		return $data;
	}

	/**
	 * Create if not exists
	 *
	 * @param  array  $data  List of data to create.
	 *
	 * @return self|false
	 */
	public static function create_or_update( array $data ) {
		$item = static::find_by_uuid( $data['option_id'] ?? '' );
		if ( $item ) {
			foreach ( $data as $key => $value ) {
				$item->set_prop( $key, $value );
			}
			if ( count( $item->get_changes() ) ) {
				$item->apply_changes();
				$item->update();
			}

			return $item;
		}
		$class = new static();
		if ( ! wp_is_uuid( $data['option_id'] ) ) {
			$data['option_id'] = wp_generate_uuid4();
		}
		$id = $class->create( $data );
		if ( $id ) {
			return $class->find_single( $id );
		}

		return false;
	}


	/**
	 * Find single item by primary key
	 *
	 * @param  string  $option_id  The uuid.
	 *
	 * @return false|static
	 */
	public static function find_by_uuid( string $option_id ) {
		if ( wp_is_uuid( $option_id ) ) {
			$item = static::get_query_builder()->where( 'option_id', $option_id )->first();
			if ( $item ) {
				return new static( $item );
			}
		}

		return false;
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
                `option_id` char(36) NULL DEFAULT NULL COMMENT '36 characters version 4 uuid',
                `title` VARCHAR(255) NULL DEFAULT NULL,
                `fields` text NULL DEFAULT NULL,
                `categories` text NULL DEFAULT NULL,
                `categoryUri` text NULL DEFAULT NULL,
                `locations` text NULL DEFAULT NULL,
                `locationUri` text NULL DEFAULT NULL,
                `concepts` text NULL DEFAULT NULL,
                `conceptUri` text NULL DEFAULT NULL,
                `sources` text NULL DEFAULT NULL,
                `sourceUri` text NULL DEFAULT NULL,
                `keyword` VARCHAR(255) NULL DEFAULT NULL,
                `keywordLoc` VARCHAR(255) NULL DEFAULT NULL,
                `lang` text NULL DEFAULT NULL,
                `primary_category` VARCHAR(50) NULL DEFAULT NULL,
                `copy_news_image` TINYINT(1) NOT NULL DEFAULT 0,
                `rewrite_title_and_body` TINYINT(1) NOT NULL DEFAULT 1,
                `enable_category_check` TINYINT(1) NOT NULL DEFAULT 0,
                `enable_live_news` TINYINT(1) NOT NULL DEFAULT 0,
                `enable_news_filtering` TINYINT(1) NOT NULL DEFAULT 0,
                `news_filtering_instruction` text NULL DEFAULT NULL,
    			`created_at` DATETIME NULL DEFAULT NULL,
    			`updated_at` DATETIME NULL DEFAULT NULL,
				PRIMARY KEY (id),
    			UNIQUE `option_id` (`option_id`)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
		if ( version_compare( $version, '1.1.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `rewrite_metadata` TINYINT(1) NOT NULL DEFAULT 1 AFTER `rewrite_title_and_body`" );

			update_option( $table . '_version', '1.1.0' );
		}

		self::copy_settings();
	}

	/**
	 * Copy settings
	 *
	 * @return void
	 */
	public static function copy_settings() {
		$settings = get_option( SyncSettings::OPTION_NAME );
		if ( false !== $settings ) {
			$settings = SyncSettings::get_settings( false );
			if ( count( $settings ) ) {
				foreach ( $settings as $setting ) {
					self::create_or_update( $setting );
				}
			}
			delete_option( SyncSettings::OPTION_NAME );
		}
	}
}

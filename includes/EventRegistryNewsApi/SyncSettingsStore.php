<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;
use Stackonet\WP\Framework\Supports\Sanitize;
use Stackonet\WP\Framework\Supports\Validate;
use StackonetNewsGenerator\Modules\Site\SiteStore;

/**
 * SyncSettingsStore class
 */
class SyncSettingsStore extends DatabaseModel {
	/**
	 * Option name
	 *
	 * @deprecated
	 */
	const OPTION_NAME = '_news_sync_settings';

	/**
	 * Keyword location
	 */
	const KEYWORD_LOCATION = array(
		'title',
		'body',
		'title-or-body',
		'title-and-body',
	);

	/**
	 * Client fields
	 */
	const CLIENT_FIELDS = array(
		'locationUri',
		'categoryUri',
		'conceptUri',
		'sourceUri',
		'keyword',
		'keywordLoc',
		'lang',
	);

	const SERVICE_PROVIDERS = array( 'newsapi.ai', 'naver.com' );
	const DEFAULT_SERVICE_PROVIDER = 'newsapi.ai';

	/**
	 * The table name
	 *
	 * @var string
	 */
	protected $table = 'event_registry_sync_settings';

	/**
	 * All site lists
	 *
	 * @var SiteStore[]
	 */
	protected static $sites = array();

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
		$data['use_actual_news']        = $this->use_actual_news();
		$data['rewrite_title_and_body'] = ! $this->use_actual_news();
		$data['rewrite_metadata']       = ! $this->use_actual_news();
		$data['to_sites']               = $this->to_sites();
		$data['query_info']             = $this->get_client_query_info();
		$data['synced_at']              = $this->get_sync_datetime();
		$data['service_provider']       = $this->get_service_provider();

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
	public function get_option_id(): string {
		return (string) $this->get_prop( 'option_id' );
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
	 * Get sync datetime
	 *
	 * @return string
	 */
	public function get_sync_datetime(): string {
		$synced_at = $this->get_prop( 'synced_at' );
		if ( ! empty( $synced_at ) ) {
			return mysql_to_rfc3339( $synced_at );
		}

		return '';
	}

	/**
	 * Update sync datetime
	 *
	 * @return void
	 */
	public function update_sync_datetime() {
		$this->set_prop( 'synced_at', gmdate( 'Y-m-d H:i:s', time() ) );
		$this->update();
	}

	/**
	 * Get primary category slug
	 *
	 * @return string
	 */
	public function get_primary_category(): string {
		return $this->get_prop( 'primary_category' );
	}

	/**
	 * Get primary concept
	 *
	 * @return string
	 */
	public function get_primary_concept(): string {
		$concepts_uris = $this->get_prop( 'conceptUri' );
		if ( is_array( $concepts_uris ) && count( $concepts_uris ) ) {
			return $concepts_uris[0];
		}

		return '';
	}

	/**
	 * Get concept basename
	 *
	 * @return string
	 */
	public function get_primary_concept_basename(): string {
		$placeholders = array( 'http://en.wikipedia.org/wiki/', 'https://en.wikipedia.org/wiki/' );

		return str_replace( $placeholders, '', $this->get_primary_concept() );
	}

	/**
	 * List of sites where it will be sent
	 *
	 * @return array
	 */
	public function to_sites(): array {
		if ( empty( static::$sites ) ) {
			static::$sites = SiteStore::find_multiple();
		}
		$to_sites = array();
		foreach ( static::$sites as $site ) {
			if ( $site instanceof SiteStore ) {
				if ( in_array( $this->get_primary_category(), $site->get_sync_categories(), true ) ) {
					$to_sites[ $site->get_id() ] = $site->get_site_url();
				}
				if ( in_array( $this->get_primary_concept_basename(), $site->get_sync_concepts(), true ) ) {
					$to_sites[ $site->get_id() ] = $site->get_site_url();
				}
			}
		}

		return array_values( $to_sites );
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
	 * If it should use actual news
	 *
	 * @return bool
	 */
	public function use_actual_news(): bool {
		return Validate::checked( $this->get_prop( 'use_actual_news' ) );
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
	 * Get news filtering instruction
	 *
	 * @return string
	 */
	public function get_news_filtering_instruction(): string {
		$instruction = $this->get_prop( 'news_filtering_instruction' );

		return (string) $instruction;
	}

	/**
	 * Get keyword
	 *
	 * @return string
	 */
	public function get_keyword(): string {
		return (string) $this->get_prop( 'keyword' );
	}

	/**
	 * If it has keyword
	 *
	 * @return bool
	 */
	public function has_keyword(): bool {
		return ! empty( $this->get_keyword() );
	}

	/**
	 * Get service provider
	 *
	 * @return string
	 */
	public function get_service_provider(): string {
		$provider = $this->get_prop( 'service_provider' );
		if ( in_array( $provider, static::SERVICE_PROVIDERS, true ) ) {
			return $provider;
		}

		return static::DEFAULT_SERVICE_PROVIDER;
	}

	/**
	 * If the service provider is naver.com
	 *
	 * @return bool
	 */
	public function is_service_provider_naver(): bool {
		return 'naver.com' === $this->get_service_provider();
	}

	/**
	 * Get query arguments
	 *
	 * @return array
	 */
	public function get_client_query_args(): array {
		$options = $this->get_data();

		return array(
			'locationUri'      => $options['locationUri'] ?? '',
			'categoryUri'      => $options['categoryUri'] ?? '',
			'conceptUri'       => $options['conceptUri'] ?? '',
			'sourceUri'        => $options['sourceUri'] ?? '',
			'lang'             => $options['lang'] ?? '',
			'primary_category' => $options['primary_category'] ?? '',
			'keyword'          => $options['keyword'] ?? '',
			'keywordLoc'       => $options['keywordLoc'] ?? '',
			'articlesPage'     => 1,
		);
	}

	/**
	 * Get NewsAPI HTTP query info
	 *
	 * @param  array  $setting
	 *
	 * @return array
	 */
	public function get_client_query_info(): array {
		$client = new Client();
		$client->add_headers( 'Content-Type', 'application/json' );
		$sanitized_args = $client->get_articles_sanitized_args( $this->get_data(), true );
		list( $url, $args ) = $client->get_url_and_arguments(
			'GET',
			'/article/getArticles',
			$sanitized_args
		);
		$args = array_merge( array( 'url' => $url ), $args );
		list( $url2, $args2 ) = $client->get_url_and_arguments(
			'POST',
			'/article/getArticles',
			$sanitized_args
		);
		$args2 = array_merge( array( 'url' => $url2 ), $args2 );

		return array(
			'get'  => $args,
			'post' => $args2,
		);
	}

	/**
	 * Get settings
	 *
	 * @param  int  $per_page  Number of items to return.
	 *
	 * @return array|SyncSettingsStore[]
	 */
	public static function get_settings_as_model( int $per_page = 100, string $status = 'publish' ): array {
		return static::find_multiple(
			array(
				'status'   => $status,
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
		foreach ( static::get_settings_as_model() as $setting ) {
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
		foreach ( static::get_settings_as_model() as $setting ) {
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
		if ( $item instanceof static ) {
			$data['id'] = $item->get_id();
			static::update( $data );

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
		if ( version_compare( $version, '1.2.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `synced_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`" );

			update_option( $table . '_version', '1.2.0' );
		}
		if ( version_compare( $version, '1.4.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `use_actual_news` TINYINT(1) NOT NULL DEFAULT 0 AFTER `copy_news_image`" );
			$wpdb->query( "ALTER TABLE `$table` DROP `rewrite_title_and_body`;" );
			$wpdb->query( "ALTER TABLE `$table` DROP `rewrite_metadata`;" );

			update_option( $table . '_version', '1.4.0' );
		}

		if ( version_compare( $version, '1.4.1', '<' ) ) {
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `total_new_items` INT(4) UNSIGNED NOT NULL DEFAULT 0 AFTER `synced_at`" );
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `total_omitted_items` INT(4) UNSIGNED NOT NULL DEFAULT 0 AFTER `synced_at`" );
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `total_existing_items` INT(4) UNSIGNED NOT NULL DEFAULT 0 AFTER `synced_at`" );
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `total_found_items` INT(4) UNSIGNED NOT NULL DEFAULT 0 AFTER `synced_at`" );

			update_option( $table . '_version', '1.4.1' );
		}

		if ( version_compare( $version, '1.5.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'publish' AFTER `news_filtering_instruction`" );

			update_option( $table . '_version', '1.5.0' );
		}

		if ( version_compare( $version, '1.6.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `service_provider` VARCHAR(50) NOT NULL DEFAULT 'newsapi.ai' AFTER `status`" );

			update_option( $table . '_version', '1.6.0' );
		}

		static::copy_settings();
	}

	/**
	 * Set total new items
	 *
	 * @param  int  $total_items  Total items.
	 *
	 * @return void
	 */
	public function set_total_new_items( int $total_items ) {
		$this->set_prop( 'total_new_items', $total_items );
	}

	/**
	 * Set total existing items
	 *
	 * @param  int  $total_existing_items  Total items.
	 *
	 * @return void
	 */
	public function set_total_existing_items( int $total_existing_items ) {
		$this->set_prop( 'total_existing_items', $total_existing_items );
	}

	/**
	 * Set total found items
	 *
	 * @param  int  $total_found_items  Total items.
	 *
	 * @return void
	 */
	public function set_total_found_items( int $total_found_items ) {
		$this->set_prop( 'total_found_items', $total_found_items );
	}

	/**
	 * Set total found items
	 *
	 * @param  int  $total_omitted_items  Total items.
	 *
	 * @return void
	 */
	public function set_total_omitted_items( int $total_omitted_items ) {
		$this->set_prop( 'total_omitted_items', $total_omitted_items );
	}

	/**
	 * Copy settings
	 *
	 * @return void
	 */
	public static function copy_settings() {
		$settings = get_option( static::OPTION_NAME );
		if ( false !== $settings ) {
			if ( count( $settings ) ) {
				foreach ( $settings as $setting ) {
					self::create_or_update( $setting );
				}
			}
			delete_option( static::OPTION_NAME );
		}
	}

	/**
	 * Update multiple records
	 *
	 * @param  array[]  $data  List of settings.
	 *
	 * @return array
	 */
	public static function update_multiple( array $data ): array {
		$sanitized_options = static::sanitize_multiple( $data );
		foreach ( $sanitized_options as $sanitized_option ) {
			static::create_or_update( $sanitized_option );
		}

		return $sanitized_options;
	}

	/**
	 * Sanitize multiple value
	 *
	 * @param  array  $options
	 *
	 * @return array
	 */
	public static function sanitize_multiple( array $options ): array {
		$settings = array();
		foreach ( $options as $sync_item ) {
			if ( empty( $sync_item['primary_category'] ) ) {
				continue;
			}

			$settings[] = static::sanitize( $sync_item );
		}

		return $settings;
	}

	/**
	 * Sanitize settings
	 *
	 * @param  array  $value
	 *
	 * @return array
	 */
	public static function sanitize( array $value ): array {
		$sync_item       = wp_parse_args( $value, static::get_defaults() );
		$syncable_fields = wp_list_pluck( static::news_sync_fields(), 'value' );

		/**
		 * To fix bug sourceUri is not saving
		 */
		$sources    = Sanitize::deep( $sync_item['sources'] );
		$source_uri = Sanitize::deep( $sync_item['sourceUri'] );
		if ( empty( $source_uri ) && ! empty( $sources ) && is_array( $sources ) ) {
			$source_uri = wp_list_pluck( $sources, 'uri' );
		}

		$keyword_loc = in_array( $sync_item['keywordLoc'], static::KEYWORD_LOCATION, true ) ?
			$sync_item['keywordLoc'] : '';

		$id = wp_is_uuid( $sync_item['option_id'] ) ? $sync_item['option_id'] : wp_generate_uuid4();

		$fields = array();
		if ( is_array( $sync_item['fields'] ) ) {
			foreach ( $sync_item['fields'] as $field ) {
				if ( in_array( $field, $syncable_fields, true ) ) {
					$fields[] = $field;
				}
			}
		}

		$status = in_array( $sync_item['status'], array( 'publish', 'draft' ), true ) ?
			$sync_item['status'] : 'publish';

		$service_provider = in_array( $sync_item['service_provider'], static::SERVICE_PROVIDERS, true ) ?
			$sync_item['service_provider'] : static::DEFAULT_SERVICE_PROVIDER;
		if ( 'naver.com' === $service_provider ) {
			$fields[] = 'keyword';
		}

		$settings = array(
			'id'                         => isset( $sync_item['id'] ) ? intval( $sync_item['id'] ) : 0,
			'option_id'                  => $id,
			'title'                      => Sanitize::text( $sync_item['title'] ),
			'service_provider'           => $service_provider,
			'fields'                     => $fields,
			'categoryUri'                => Sanitize::deep( $sync_item['categoryUri'] ),
			'locationUri'                => Sanitize::deep( $sync_item['locationUri'] ),
			'conceptUri'                 => Sanitize::deep( $sync_item['conceptUri'] ),
			'sourceUri'                  => $source_uri,
			'lang'                       => Sanitize::deep( $sync_item['lang'] ),
			'categories'                 => Sanitize::deep( $sync_item['categories'] ),
			'locations'                  => Sanitize::deep( $sync_item['locations'] ),
			'concepts'                   => Sanitize::deep( $sync_item['concepts'] ),
			'sources'                    => $sources,
			'primary_category'           => Sanitize::text( $sync_item['primary_category'] ),
			'keyword'                    => Sanitize::text( $sync_item['keyword'] ),
			'status'                     => $status,
			'keywordLoc'                 => $keyword_loc,
			'copy_news_image'            => Sanitize::checked( $sync_item['copy_news_image'] ),
			'enable_news_filtering'      => Sanitize::checked( $sync_item['enable_news_filtering'] ),
			'enable_live_news'           => Sanitize::checked( $sync_item['enable_live_news'] ),
			'use_actual_news'            => Sanitize::checked( $sync_item['use_actual_news'] ),
			'news_filtering_instruction' => Sanitize::text( $sync_item['news_filtering_instruction'] ),
		);

		foreach ( static::reset_fields_to_default() as $main_field => $fields_to_reset ) {
			if ( in_array( $main_field, $fields, true ) ) {
				continue;
			}
			foreach ( $fields_to_reset as $setting_key => $setting_value ) {
				$settings[ $setting_key ] = $setting_value;
			}
		}

		return $settings;
	}

	/**
	 * Get default values
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		$fields = wp_list_pluck( static::news_sync_fields(), 'value' );

		return array(
			'option_id'                  => '',
			'title'                      => '',
			'fields'                     => $fields,
			'categories'                 => array(),
			'locations'                  => array(),
			'concepts'                   => array(),
			'sources'                    => array(),
			'keyword'                    => '',
			'keywordLoc'                 => '',
			'categoryUri'                => '',
			'locationUri'                => '',
			'conceptUri'                 => '',
			'sourceUri'                  => '',
			'lang'                       => '',
			'primary_category'           => '',
			'copy_news_image'            => true,
			'enable_category_check'      => true,
			'enable_live_news'           => false,
			'enable_news_filtering'      => false,
			'use_actual_news'            => false,
			'news_filtering_instruction' => '',
		);
	}

	/**
	 * Fields to reset default
	 *
	 * @return array
	 */
	public static function reset_fields_to_default(): array {
		return array(
			'keyword'               => array(
				'keyword'    => '',
				'keywordLoc' => '',
			),
			'locationUri'           => array(
				'locations'   => array(),
				'locationUri' => '',
			),
			'categoryUri'           => array(
				'categories'  => array(),
				'categoryUri' => '',
			),
			'conceptUri'            => array(
				'concepts'   => array(),
				'conceptUri' => '',
			),
			'sourceUri'             => array(
				'sources'   => array(),
				'sourceUri' => '',
			),
			'lang'                  => array(
				'lang' => '',
			),
			'enable_news_filtering' => array(
				'news_filtering_instruction' => '',
			),
		);
	}

	/**
	 * The field to sync
	 *
	 * @return array[]
	 */
	public static function news_sync_fields(): array {
		return array(
			array(
				'value' => 'keyword',
				'label' => 'Keyword',
			),
			array(
				'value' => 'locationUri',
				'label' => 'Location',
			),
			array(
				'value' => 'categoryUri',
				'label' => 'Category',
			),
			array(
				'value' => 'conceptUri',
				'label' => 'Concept',
			),
			array(
				'value' => 'sourceUri',
				'label' => 'Source',
			),
			array(
				'value' => 'lang',
				'label' => 'Language',
			),
			array(
				'value' => 'enable_news_filtering',
				'label' => 'Filtering',
			),
		);
	}
}

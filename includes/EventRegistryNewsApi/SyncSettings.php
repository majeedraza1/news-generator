<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

use Stackonet\WP\Framework\Abstracts\Data;
use Stackonet\WP\Framework\Supports\Sanitize;
use Stackonet\WP\Framework\Supports\Validate;
use StackonetNewsGenerator\OpenAIApi\Setting as OpenAIApiSetting;

class SyncSettings extends Data {
	const OPTION_NAME = '_news_sync_settings';
	const KEYWORD_LOCATION = array(
		'title',
		'body',
		'title-or-body',
		'title-and-body',
	);
	const CLIENT_FIELDS = array(
		'locationUri',
		'categoryUri',
		'conceptUri',
		'sourceUri',
		'keyword',
		'keywordLoc',
		'lang',
	);

	/**
	 * Get setting
	 *
	 * @param  string  $id  Setting unique id.
	 *
	 * @return false|array
	 */
	public static function get_setting( string $id ) {
		$settings = static::get_settings();
		foreach ( $settings as $setting ) {
			if ( $setting['option_id'] === $id ) {
				return $setting;
			}
		}

		return false;
	}

	/**
	 * Get settings
	 *
	 * @return array
	 */
	public static function get_settings( bool $show_query_info = true ): array {
		$options     = static::get_option();
		$data        = array();
		$uuids       = array();
		$should_save = false;
		foreach ( $options as $option ) {
			$option = wp_parse_args( $option, static::get_defaults() );
			if ( empty( $option['option_id'] ) || in_array( $option['option_id'], $uuids, true ) ) {
				$option['option_id'] = wp_generate_uuid4();
				$should_save         = true;
			}
			$uuids[] = $option['option_id'];

			if ( $show_query_info ) {
				$option['last_sync']  = self::get_sync_time( $option );
				$option['query_info'] = static::get_news_api_http_query_info( $option );
			}
			$data[] = $option;
		}

		if ( $should_save ) {
			static::update_option( $data );
		}

		return $data;
	}

	/**
	 * Get option
	 *
	 * @return array
	 */
	private static function get_option(): array {
		$options = get_option( static::OPTION_NAME );
		if ( is_array( $options ) ) {
			return $options;
		}

		$options = SyncSettingsStore::get_settings_as_array();
		if ( count( $options ) ) {
			return $options;
		}

		// Backward compatibility.
		$options = (array) get_option( '_event_registry_news_api_settings' );
		$options = isset( $options['news_sync'] ) && is_array( $options['news_sync'] ) ? $options['news_sync'] : array();

		static::update_option( $options );

		return $options;
	}

	public static function update_option( array $options ): array {
		$sanitized_options = static::sanitize_multiple( $options );
		foreach ( $sanitized_options as $sanitized_option ) {
			SyncSettingsStore::create_or_update( $sanitized_option );
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
		$sync_item = wp_parse_args( $value, static::get_defaults() );
		$fields    = wp_list_pluck( static::news_sync_fields(), 'value' );

		/**
		 * To fix bug sourceUri is not saving
		 */
		$sources   = Sanitize::deep( $sync_item['sources'] );
		$sourceUri = Sanitize::deep( $sync_item['sourceUri'] );
		if ( empty( $sourceUri ) && ! empty( $sources ) && is_array( $sources ) ) {
			$sourceUri = wp_list_pluck( $sources, 'uri' );
		}

		$keywordLoc = in_array( $sync_item['keywordLoc'], static::KEYWORD_LOCATION, true ) ?
			$sync_item['keywordLoc'] : '';

		$id = wp_is_uuid( $sync_item['option_id'] ) ? $sync_item['option_id'] : wp_generate_uuid4();

		$settings = array(
			'option_id'                  => $id,
			'title'                      => Sanitize::text( $sync_item['title'] ),
			'fields'                     => array(),
			'categoryUri'                => Sanitize::deep( $sync_item['categoryUri'] ),
			'locationUri'                => Sanitize::deep( $sync_item['locationUri'] ),
			'conceptUri'                 => Sanitize::deep( $sync_item['conceptUri'] ),
			'sourceUri'                  => $sourceUri,
			'lang'                       => Sanitize::deep( $sync_item['lang'] ),
			'categories'                 => Sanitize::deep( $sync_item['categories'] ),
			'locations'                  => Sanitize::deep( $sync_item['locations'] ),
			'concepts'                   => Sanitize::deep( $sync_item['concepts'] ),
			'sources'                    => $sources,
			'primary_category'           => Sanitize::text( $sync_item['primary_category'] ),
			'keyword'                    => Sanitize::text( $sync_item['keyword'] ),
			'keywordLoc'                 => $keywordLoc,
			'copy_news_image'            => Sanitize::checked( $sync_item['copy_news_image'] ),
			'enable_news_filtering'      => Sanitize::checked( $sync_item['enable_news_filtering'] ),
			'enable_live_news'           => Sanitize::checked( $sync_item['enable_live_news'] ),
			'rewrite_title_and_body'     => Sanitize::checked( $sync_item['rewrite_title_and_body'] ),
			'rewrite_metadata'           => Sanitize::checked( $sync_item['rewrite_metadata'] ),
			'news_filtering_instruction' => Sanitize::text( $sync_item['news_filtering_instruction'] ),
		);

		foreach ( $settings as $key => $setting ) {
			if ( in_array( $key, $fields, true ) & ! empty( $setting ) ) {
				$settings['fields'][] = $key;
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
			'rewrite_title_and_body'     => true,
			'rewrite_metadata'           => true,
			'news_filtering_instruction' => OpenAIApiSetting::get_news_filtering_instruction(),
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

	/**
	 * Get sync time
	 *
	 * @param  array  $options
	 *
	 * @return false|int|string
	 */
	public static function get_sync_time( array $options ) {
		$value = get_transient( self::sync_time_option_name( $options ) );

		return ! empty( $value ) ? mysql_to_rfc3339( $value ) : '';
	}

	/**
	 * Get sync time option name
	 *
	 * @param  array  $options
	 *
	 * @return string
	 */
	public static function sync_time_option_name( array $options ): string {
		$values = array();
		foreach ( $options as $key => $value ) {
			if ( in_array( $key, static::CLIENT_FIELDS, true ) && ! empty( $value ) ) {
				$values[ $key ] = $value;
			}
		}

		return 'news_sync_datetime_' . md5( wp_json_encode( $values ) );
	}

	/**
	 * Get NewsAPI HTTP query info
	 *
	 * @param  array  $setting
	 *
	 * @return array
	 */
	public static function get_news_api_http_query_info( array $setting ): array {
		$client = new Client();
		$client->add_headers( 'Content-Type', 'application/json' );
		$sanitized_args = $client->get_articles_sanitized_args( $setting, true );
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
	 * Update sync time
	 *
	 * @param  array  $options
	 *
	 * @return void
	 */
	public static function update_sync_time( array $options ) {
		set_transient(
			self::sync_time_option_name( $options ),
			gmdate( 'Y-m-d H:i:s', time() ),
			WEEK_IN_SECONDS
		);
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
	 * Re-Write full news
	 *
	 * @return bool
	 */
	public function rewrite_full_news(): bool {
		return ( $this->rewrite_title_and_body() && $this->rewrite_metadata() );
	}

	public function is_news_filtering_enabled(): bool {
		return Validate::checked( $this->get_prop( 'enable_news_filtering' ) );
	}

	public function is_live_news_enabled(): bool {
		return Validate::checked( $this->get_prop( 'enable_live_news' ) );
	}

	public function should_copy_image(): bool {
		return Validate::checked( $this->get_prop( 'copy_news_image' ) );
	}

	public function should_check_category(): bool {
		return Validate::checked( $this->get_prop( 'enable_category_check' ) );
	}

	public function get_option_id() {
		return $this->get_prop( 'option_id' );
	}

	public function get_primary_category() {
		return $this->get_prop( 'primary_category' );
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
}

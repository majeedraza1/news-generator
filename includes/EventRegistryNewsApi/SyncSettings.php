<?php

namespace TeraPixelNewsGenerator\EventRegistryNewsApi;

use TeraPixelNewsGenerator\OpenAIApi\Setting as OpenAIApiSetting;
use Stackonet\WP\Framework\Abstracts\Data;
use Stackonet\WP\Framework\Supports\Sanitize;
use Stackonet\WP\Framework\Supports\Validate;

class SyncSettings extends Data {
	const OPTION_NAME = '_news_sync_settings';
	const KEYWORD_LOCATION = [
		'title',
		'body',
		'title-or-body',
		'title-and-body',
	];
	const CLIENT_FIELDS = [
		'locationUri',
		'categoryUri',
		'conceptUri',
		'sourceUri',
		'keyword',
		'keywordLoc',
		'lang',
	];

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
		$data        = [];
		$uuids       = [];
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

	private static function get_option(): array {
		$options = get_option( static::OPTION_NAME );
		if ( is_array( $options ) ) {
			return $options;
		}

		// Backward compatibility
		$options = (array) get_option( '_event_registry_news_api_settings' );
		$options = isset( $options['news_sync'] ) && is_array( $options['news_sync'] ) ? $options['news_sync'] : [];

		static::update_option( $options );

		return $options;
	}

	public static function update_option( array $options ): array {
		$sanitized_options = static::sanitize_multiple( $options );
		update_option( static::OPTION_NAME, $sanitized_options );

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
		$settings = [];
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

		$settings = [
			'option_id'                  => $id,
			'fields'                     => [],
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
			'news_filtering_instruction' => Sanitize::text( $sync_item['news_filtering_instruction'] ),
		];

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

		return [
			'option_id'                  => '',
			'fields'                     => $fields,
			'categories'                 => [],
			'locations'                  => [],
			'concepts'                   => [],
			'sources'                    => [],
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
			'news_filtering_instruction' => OpenAIApiSetting::get_news_filtering_instruction(),
		];
	}

	/**
	 * The field to sync
	 *
	 * @return array[]
	 */
	public static function news_sync_fields(): array {
		return [
			[
				'value' => 'keyword',
				'label' => 'Keyword',
			],
			[
				'value' => 'locationUri',
				'label' => 'Location',
			],
			[
				'value' => 'categoryUri',
				'label' => 'Category',
			],
			[
				'value' => 'conceptUri',
				'label' => 'Concept',
			],
			[
				'value' => 'sourceUri',
				'label' => 'Source',
			],
			[
				'value' => 'lang',
				'label' => 'Language',
			],
			[
				'value' => 'enable_news_filtering',
				'label' => 'Filtering',
			],
		];
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
		$values = [];
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
		$args = array_merge( [ 'url' => $url ], $args );
		list( $url2, $args2 ) = $client->get_url_and_arguments(
			'POST',
			'/article/getArticles',
			$sanitized_args
		);
		$args2 = array_merge( [ 'url' => $url2 ], $args2 );

		return [
			'get'  => $args,
			'post' => $args2,
		];
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

		return [
			'locationUri'      => $options['locationUri'] ?? '',
			'categoryUri'      => $options['categoryUri'] ?? '',
			'conceptUri'       => $options['conceptUri'] ?? '',
			'sourceUri'        => $options['sourceUri'] ?? '',
			'lang'             => $options['lang'] ?? '',
			'primary_category' => $options['primary_category'] ?? '',
			'keyword'          => $options['keyword'] ?? '',
			'keywordLoc'       => $options['keywordLoc'] ?? '',
			'articlesPage'     => 1,
		];
	}
}

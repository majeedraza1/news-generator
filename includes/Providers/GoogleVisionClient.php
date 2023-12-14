<?php

namespace TeraPixelNewsGenerator\Providers;

use Exception;
use Stackonet\WP\Framework\Supports\Logger;
use Stackonet\WP\Framework\Supports\RestClient;
use WP_Error;

/**
 * GoogleVisionClient class
 */
class GoogleVisionClient extends RestClient {
	/**
	 * Get setting
	 *
	 * @param  string  $key  The setting key.
	 * @param  mixed  $default  The default value.
	 *
	 * @return false|mixed
	 * @throws Exception Throw exception if constant not found.
	 */
	public static function get_setting( string $key, $default = false ) {
		$settings = static::get_settings();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Get settings
	 *
	 * @return array The settings.
	 * @throws Exception Throw exception if constant not found.
	 */
	public static function get_settings(): array {
		if ( defined( 'GOOGLE_VISION_SETTINGS' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$settings = unserialize( GOOGLE_VISION_SETTINGS );

			return array_merge( static::defaults(), $settings );
		}

		$options = get_option( '_google_vision_settings' );
		if ( is_array( $options ) ) {
			return wp_parse_args( $options, static::defaults() );
		}

		throw new Exception( 'Settings are not available. Define GOOGLE_VISION_SETTINGS constant in wp-config.php file.' );
	}

	/**
	 * Update google vision secret key
	 *
	 * @param  mixed  $secret_key  Secret key.
	 *
	 * @return string
	 */
	public static function update_google_vision_secret_key( $secret_key ): string {
		$options               = get_option( '_google_vision_settings' );
		$options               = is_array( $options ) ? $options : [];
		$options['secret_key'] = sanitize_text_field( $secret_key );

		update_option( '_google_vision_settings', $options );

		return $options['secret_key'];
	}

	public static function get_google_vision_secret_key(): string {
		$options = get_option( '_google_vision_settings' );
		$options = is_array( $options ) ? $options : [];

		return $options['secret_key'] ?? '';
	}

	/**
	 * Get default settings
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'secret_key' => '',
		);
	}

	/**
	 * Class constructor
	 *
	 * @throws Exception
	 */
	public function __construct() {
		$key = static::get_setting( 'secret_key' );
		if ( ! empty( $key ) ) {
			$this->set_global_parameter( 'key', $key );
		}
		$this->add_headers( 'Content-Type', 'application/json; charset=utf-8' );
		if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
			$this->add_headers( 'Referer', 'http://yousaidit.test' );
		} else {
			$this->add_headers( 'Referer', site_url() );
		}
		parent::__construct( 'https://vision.googleapis.com/v1' );
	}

	/**
	 * Detect text from image
	 *
	 * @param  string  $base64_image_string  Base64 image string.
	 *
	 * @return array|WP_Error
	 */
	public function detect_text( string $base64_image_string ) {
		$cache_key = 'detect_text_' . md5( $base64_image_string );
		$response  = get_transient( $cache_key );
		if ( is_array( $response ) ) {
			$response['source'] = 'cache';

			return $response;
		}
		$data = array(
			'requests' => array(
				array(
					'features' => array( array( 'type' => 'TEXT_DETECTION' ) ),
					'image'    => array( 'content' => $base64_image_string ),
				),
			),
		);

		$response = $this->post( 'images:annotate', wp_json_encode( $data ) );
		if ( ! is_wp_error( $response ) ) {
			$response = $response['responses'][0] ?? array();
			set_transient( $cache_key, $response, HOUR_IN_SECONDS );
			$response['source'] = 'api';
		}

		return $response;
	}

	public static function has_text_on_image( string $image_url_or_path ) {
		try {
			$im       = new \Imagick( $image_url_or_path );
			$response = ( new static() )->detect_text( base64_encode( $im->getImageBlob() ) );
			if ( is_array( $response ) && isset( $response['textAnnotations'] ) ) {
				return is_array( $response['textAnnotations'] ) && count( $response['textAnnotations'] ) > 0;
			}
		} catch ( \ImagickException $e ) {
			Logger::log( $e->getMessage() );
		}

		return false;
	}
}

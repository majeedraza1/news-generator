<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

/**
 * SiteSetting class
 * Hold site configuration
 */
class SiteSetting {
	/**
	 * Site settings array
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Default value
	 *
	 * @var string[]
	 */
	protected static $default = array(
		'titleSelector' => '',
		'bodySelector'  => '',
	);

	/**
	 * Class constructor
	 *
	 * @param  array  $settings  Setting array.
	 */
	public function __construct( array $settings = array() ) {
		$this->settings = array_merge( static::$default, $settings );
	}

	/**
	 * Get settings
	 *
	 * @param  string  $key  Setting key.
	 * @param  mixed  $defaults  Default value.
	 *
	 * @return mixed
	 */
	public function get_setting( string $key, $defaults = '' ) {
		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}

		return $defaults;
	}

	/**
	 * Get body selector
	 *
	 * @return string
	 */
	public function get_body_selector(): string {
		return (string) $this->get_setting( 'bodySelector' );
	}

	/**
	 * Get body selector
	 *
	 * @return string
	 */
	public function get_title_selector(): string {
		return (string) $this->get_setting( 'titleSelector' );
	}
}

<?php

namespace StackonetNewsGenerator;

use StackonetNewsGenerator\EventRegistryNewsApi\Category;
use StackonetNewsGenerator\EventRegistryNewsApi\Country;
use StackonetNewsGenerator\EventRegistryNewsApi\Language;
use StackonetNewsGenerator\OpenAIApi\Setting;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Assets class
 */
class Assets {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Plugin name slug
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'wp_loaded', array( self::$instance, 'register' ) );

			add_action( 'admin_head', array( self::$instance, 'localize_data' ), 9 );
			add_action( 'wp_head', array( self::$instance, 'localize_data' ), 9 );
		}

		return self::$instance;
	}

	/**
	 * Global localize data both for admin and frontend
	 */
	public static function localize_data() {
		$user              = wp_get_current_user();
		$is_user_logged_in = $user->exists();

		$data = array(
			'homeUrl'          => home_url(),
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'restRoot'         => esc_url_raw( rest_url( 'terapixel-news-generator/v1' ) ),
			'isUserLoggedIn'   => $is_user_logged_in,
			'privacyPolicyUrl' => get_privacy_policy_url(),
		);

		if ( ! $is_user_logged_in ) {
			$data['lostPasswordUrl'] = wp_lostpassword_url();
		}

		if ( $is_user_logged_in ) {
			$data['restNonce'] = wp_create_nonce( 'wp_rest' );

			$data['user'] = array(
				'name'      => $user->display_name,
				'avatarUrl' => get_avatar_url( $user->user_email ),
			);

			$data['logoutUrl'] = wp_logout_url( get_the_permalink() );
		}

		if ( is_admin() ) {
			$data['countries']    = Country::countries();
			$data['categories']   = Category::get_categories();
			$data['languages']    = Language::languages();
			$data['instructions'] = Setting::get_instructions();
		}

		echo '<script>window.StackonetNewsGenerator = ' . wp_json_encode( $data ) . '</script>' . PHP_EOL;
	}

	/**
	 * Register our app scripts and styles
	 *
	 * @return void
	 */
	public function register() {
		$this->plugin_name = Plugin::init()->get_directory_name();
		$this->version     = Plugin::init()->get_plugin_version();

		if ( $this->is_script_debug_enabled() ) {
			$this->version = $this->version . '-' . time();
		}

		$this->register_scripts( $this->get_scripts() );
		$this->register_styles( $this->get_styles() );
	}

	/**
	 * Check if script debugging is enabled
	 *
	 * @return bool
	 */
	private function is_script_debug_enabled(): bool {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	}

	/**
	 * Register scripts
	 *
	 * @param array $scripts
	 *
	 * @return void
	 */
	private function register_scripts( array $scripts ) {
		foreach ( $scripts as $handle => $script ) {
			$deps      = $script['deps'] ?? false;
			$in_footer = $script['in_footer'] ?? true;
			$version   = $script['version'] ?? $this->version;
			wp_register_script( $handle, $script['src'], $deps, $version, $in_footer );
		}
	}

	/**
	 * Get all registered scripts
	 *
	 * @return array
	 */
	public function get_scripts(): array {
		return array(
			"{$this->plugin_name}-admin" => array(
				'src' => static::get_assets_url( 'js/admin.js' ),
			),
		);
	}

	/**
	 * Get assets URL
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public static function get_assets_url( $path = '' ): string {
		$url = Plugin::init()->get_plugin_url( 'assets' );

		if ( static::is_ssl() && 0 === stripos( $url, 'http://' ) ) {
			$url = str_replace( 'http://', 'https://', $url );
		}

		if ( ! empty( $path ) ) {
			return rtrim( $url, '/' ) . '/' . ltrim( $path, '/' );
		}

		return $url;
	}

	/**
	 * Checks to see if the site has SSL enabled or not.
	 *
	 * @return bool
	 */
	public static function is_ssl(): bool {
		if ( is_ssl() ) {
			return true;
		} elseif ( 0 === stripos( get_option( 'siteurl' ), 'https://' ) ) {
			return true;
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Register styles
	 *
	 * @param array $styles
	 *
	 * @return void
	 */
	public function register_styles( array $styles ) {
		foreach ( $styles as $handle => $style ) {
			$deps = $style['deps'] ?? false;
			wp_register_style( $handle, $style['src'], $deps, $this->version );
		}
	}

	/**
	 * Get registered styles
	 *
	 * @return array
	 */
	public function get_styles(): array {
		return array(
			"{$this->plugin_name}-admin" => array(
				'src' => static::get_assets_url( 'css/admin.css' ),
			),
		);
	}
}

<?php

namespace TeraPixelNewsGenerator\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class Admin {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'admin_menu', [ self::$instance, 'add_menu' ] );
		}

		return self::$instance;
	}

	/**
	 * Add top level menu
	 */
	public static function add_menu() {
		global $submenu;
		$capability = 'manage_options';
		$slug       = 'news-api';

		$hook = add_menu_page(
			__( 'News', 'terapixel-news-generator' ),
			__( 'News', 'terapixel-news-generator' ),
			$capability,
			$slug,
			[ self::$instance, 'menu_page_callback' ],
			'dashicons-update',
			6
		);

		$menus = [
			[
				'title' => __( 'News', 'terapixel-news-generator' ),
				'slug'  => '#/',
			],
			[
				'title' => __( 'News (newsapi.ai)', 'terapixel-news-generator' ),
				'slug'  => '#/news',
			],
			[
				'title' => __( 'Tweets', 'terapixel-news-generator' ),
				'slug'  => '#/tweets',
			],
			[
				'title' => __( 'Keywords', 'terapixel-news-generator' ),
				'slug'  => '#/keywords',
			],
			[
				'title' => __( 'News Tags', 'terapixel-news-generator' ),
				'slug'  => '#/tags',
			],
			[
				'title' => __( 'News Sources', 'terapixel-news-generator' ),
				'slug'  => '#/sources',
			],
			[
				'title' => __( 'Sites', 'terapixel-news-generator' ),
				'slug'  => '#/sites',
			],
			[
				'title' => __( 'External Links', 'terapixel-news-generator' ),
				'slug'  => '#/external-links',
			],
			[
				'title' => __( 'Manual Sync', 'terapixel-news-generator' ),
				'slug'  => '#/sync',
			],
			[
				'title' => __( 'Settings', 'terapixel-news-generator' ),
				'slug'  => '#/settings',
			],
			[
				'title' => __( 'Logs', 'terapixel-news-generator' ),
				'slug'  => '#/logs',
			],
		];

		if ( current_user_can( $capability ) ) {
			foreach ( $menus as $menu ) {
				$submenu[ $slug ][] = [ $menu['title'], $capability, 'admin.php?page=' . $slug . $menu['slug'] ];
			}
		}

		add_action( 'load-' . $hook, [ self::$instance, 'init_hooks' ] );
	}

	/**
	 * Menu page callback
	 */
	public static function menu_page_callback() {
		echo '<div class="wrap"><div id="nusify-news-api-admin"></div></div>';
	}

	/**
	 * Load required styles and scripts
	 */
	public static function init_hooks() {
		wp_enqueue_media();
		wp_enqueue_style( 'terapixel-news-generator-admin' );
		wp_enqueue_script( 'terapixel-news-generator-admin' );
	}
}

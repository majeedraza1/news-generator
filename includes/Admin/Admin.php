<?php

namespace StackonetNewsGenerator\Admin;

// If this file is called directly, abort.
use StackonetNewsGenerator\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class
 */
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
			__( 'News', 'stackonet-news-generator' ),
			__( 'News', 'stackonet-news-generator' ),
			$capability,
			$slug,
			[ self::$instance, 'menu_page_callback' ],
			'dashicons-update',
			6
		);

		$menus = [
			[
				'title' => __( 'News', 'stackonet-news-generator' ),
				'slug'  => '#/',
			],
			[
				'title' => __( 'News (newsapi.ai)', 'stackonet-news-generator' ),
				'slug'  => '#/news',
			],
			[
				'title' => __( 'Tweets', 'stackonet-news-generator' ),
				'slug'  => '#/tweets',
			],
			[
				'title' => __( 'Keywords', 'stackonet-news-generator' ),
				'slug'  => '#/keywords',
			],
			[
				'title' => __( 'News Tags', 'stackonet-news-generator' ),
				'slug'  => '#/tags',
			],
			[
				'title' => __( 'News Sources', 'stackonet-news-generator' ),
				'slug'  => '#/sources',
			],
			[
				'title' => __( 'Sites', 'stackonet-news-generator' ),
				'slug'  => '#/sites',
			],
			[
				'title' => __( 'External Links', 'stackonet-news-generator' ),
				'slug'  => '#/external-links',
			],
			[
				'title' => __( 'Manual Sync', 'stackonet-news-generator' ),
				'slug'  => '#/sync',
			],
			[
				'title' => __( 'Settings', 'stackonet-news-generator' ),
				'slug'  => '#/settings',
			],
			[
				'title' => __( 'Logs', 'stackonet-news-generator' ),
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
		wp_enqueue_style( Plugin::init()->get_directory_name() . '-admin' );
		wp_enqueue_script( Plugin::init()->get_directory_name() . '-admin' );
	}
}

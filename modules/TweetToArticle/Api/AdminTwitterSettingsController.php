<?php

namespace TeraPixelNewsGenerator\Modules\TweetToArticle\Api;

use TeraPixelNewsGenerator\Modules\TweetToArticle\Settings;
use TeraPixelNewsGenerator\REST\ApiController;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * AdminTwitterSettingsController class
 */
class AdminTwitterSettingsController extends ApiController {
	use ApiPermissionChecker;

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'rest_api_init', array( self::$instance, 'register_routes' ) );
		}

		return self::$instance;
	}

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/twitter-settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'is_editor' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'is_editor' ],
				],
			]
		);
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$settings = [
			'sync_interval'                    => Settings::get_sync_interval(),
			'batch_type'                       => Settings::get_batch_type(),
			'supported_languages'              => Settings::get_supported_languages(),
			'instruction_for_important_tweets' => Settings::get_instruction_for_important_tweets(),
			'instruction_for_tweet_to_article' => Settings::get_instruction_for_tweet_to_article(),
		];

		return $this->respondOK(
			[
				'settings'            => $settings,
				'batch_types'         => Settings::get_batch_types(),
				'supported_languages' => Settings::twitter_supported_languages(),
			]
		);
	}

	/**
	 * Creates one item from the collection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function create_item( $request ) {
		$batch_type = $request->get_param( 'batch_type' );
		Settings::set_batch_type( $batch_type );

		$supported_languages = $request->get_param( 'supported_languages' );
		Settings::set_supported_languages( $supported_languages );

		$sync_interval = $request->get_param( 'sync_interval' );
		Settings::set_sync_interval( intval( $sync_interval ) );

		$instruction = $request->get_param( 'instruction_for_important_tweets' );
		Settings::set_instruction_for_important_tweets( $instruction );

		$instruction = $request->get_param( 'instruction_for_tweet_to_article' );
		Settings::set_instruction_for_tweet_to_article( $instruction );

		return $this->get_items( $request );
	}
}

<?php

namespace TeraPixelNewsGenerator\Modules\TweetToArticle\Api;

use TeraPixelNewsGenerator\Modules\TweetToArticle\Models\TwitterTweets;
use TeraPixelNewsGenerator\Modules\TweetToArticle\Models\TwitterUsername;
use TeraPixelNewsGenerator\REST\ApiController;
use TeraPixelNewsGenerator\Supports\TwitterApi;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * TwitterUsernameController
 */
class AdminTwitterUsernameController extends ApiController {
	use ApiPermissionChecker;

	/**
	 * @var self
	 */
	private static $instance;

	/**
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
			'/twitter-usernames',
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
		register_rest_route(
			$this->namespace,
			'/twitter-usernames/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'is_editor' ],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/twitter-usernames/(?P<id>\d+)/sync',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'sync_item' ],
					'permission_callback' => [ $this, 'is_editor' ],
				],
			]
		);
	}

	/**
	 * Get collection of items
	 *
	 * @param WP_REST_Request $request The request details.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		return $this->respondOK(
			[
				'items' => ( new TwitterUsername() )->get_options(),
			]
		);
	}

	/**
	 * Create a new item.
	 *
	 * @param WP_REST_Request $request The request details.
	 *
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		$username = $request->get_param( 'username' );
		if ( empty( $username ) ) {
			return $this->respondUnprocessableEntity( 'username_empty', 'Username cannot be empty.' );
		}
		$user = TwitterUsername::find_by_username( $username );
		if ( is_array( $user ) ) {
			return $this->respondUnprocessableEntity( 'username_already_exists', 'Username already exists.' );
		}

		$user = TwitterApi::get_user( $username );
		if ( ! is_array( $user ) ) {
			return $this->respondUnprocessableEntity();
		}

		$data = ( new TwitterUsername() )->create( $user );

		return $this->respondCreated( $data );
	}

	public function delete_item( $request ) {
		$id = $request->get_param( 'id' );
		( new TwitterUsername() )->delete( $id );

		return $this->respondOK();
	}

	/**
	 * Sync item
	 *
	 * @param WP_REST_Request $request The request details.
	 *
	 * @return WP_REST_Response
	 */
	public function sync_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$user = ( new TwitterUsername() )->get_option( $id );
		if ( empty( $user ) ) {
			return $this->respondNotFound();
		}
		$tweets   = TwitterApi::get_user_tweets( $user['username'] );
		$batch_id = wp_generate_uuid4();
		if ( is_array( $tweets ) && isset( $tweets['data'] ) ) {
			TwitterTweets::batch_create_if_not_exists( array_reverse( $tweets['data'] ), $user['username'], $batch_id );
		}

		return $this->respondOK( $tweets );
	}
}

<?php

namespace StackonetNewsGenerator\Modules\TweetToArticle\Api;

use StackonetNewsGenerator\Modules\TweetToArticle\Models\TwitterTweets;
use StackonetNewsGenerator\REST\ApiController;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * AdminTwitterTweetsController class
 */
class AdminTwitterTweetsController extends ApiController {
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
			'/twitter-tweets',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'is_editor' ],
					'args'                => $this->get_collection_params(),
				],
			]
		);
	}

	public function get_collection_params(): array {
		return [
			'page'     => [
				'description'       => 'Current page of the collection.',
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'per_page' => [
				'description'       => 'Maximum number of items to be returned in result set.',
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'username' => [
				'description'       => 'Limit results to a user.',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'sort'     => [
				'description'       => 'Sorting order. Example: title+DESC,author+ASC',
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_sorting_data' ],
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$items      = TwitterTweets::find_multiple(
			[
				'page'     => $page,
				'per_page' => $per_page,
			]
		);
		$counts     = TwitterTweets::count_records();
		$pagination = static::get_pagination_data( $counts['all'], $per_page, $page );

		return $this->respondOK(
			[
				'items'      => $items,
				'pagination' => $pagination,
			]
		);
	}
}

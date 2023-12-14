<?php

namespace TeraPixelNewsGenerator\REST;

use TeraPixelNewsGenerator\EventRegistryNewsApi\NewsSource;
use Stackonet\WP\Framework\Abstracts\Data;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class AdminNewsSourceController extends ApiController {
	use ApiPermissionChecker;

	/**
	 * @var self
	 */
	private static $instance;
	protected $rest_base = 'admin/news-sources';

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

	public function register_routes() {
		$rest_base = isset( $this->rest_base ) ? trim( $this->rest_base, '/' ) : '';
		$args      = [];
		$args[]    = [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_items' ],
			'args'                => $this->get_collection_params(),
			'permission_callback' => [ $this, 'get_items_permissions_check' ],
		];
		$args[]    = [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'create_item' ],
			'permission_callback' => [ $this, 'create_item_permissions_check' ],
			'args'                => [
				'uri' => [
					'description'       => 'News source uri.',
					'type'              => 'string',
					'sanitize_callback' => 'esc_url',
					'validate_callback' => 'rest_validate_request_arg',
				],
			],
		];

		register_rest_route( $this->namespace, $rest_base, $args );

		$args2 = [
			'args' => [
				'id' => [
					'description'       => 'Item unique id.',
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
					'minimum'           => 1,
				],
			],
		];

		$args2[] = [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_item' ],
			'permission_callback' => [ $this, 'get_item_permissions_check' ],
		];

		$args2[] = [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'update_item' ],
			'permission_callback' => [ $this, 'update_item_permissions_check' ],
		];

		$args2[] = [
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'delete_item' ],
			'permission_callback' => [ $this, 'delete_item_permissions_check' ],
		];

		register_rest_route( $this->namespace, $rest_base . '/(?P<id>\d+)', $args2 );

		register_rest_route(
			$this->namespace,
			$rest_base . '/batch',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'batch_operation' ],
					'args'                => [
						'action'  => [
							'type'              => 'string',
							'description'       => 'The batch operation to run for the request.',
							'enum'              => [ 'copy', 'delete' ],
							'validate_callback' => 'rest_validate_request_arg',
						],
						'payload' => [
							'type'              => [ 'array', 'object' ],
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
					'permission_callback' => [ $this, 'batch_operation_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$status   = $request->get_param( 'status' );

		$items      = $this->get_store()->find_multiple( $request->get_params() );
		$counts     = $this->get_store()->count_records( $request->get_params() );
		$count      = $counts[ $status ] ?? $counts['all'];
		$pagination = static::get_pagination_data( $count, $per_page, $page );

		return $this->respondOK(
			[
				'items'      => $items,
				'pagination' => $pagination,
			]
		);
	}

	public function get_store(): NewsSource {
		return new NewsSource();
	}

	/**
	 * Creates one item from the collection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		$item = NewsSource::create_if_not_exists( $request->get_params() );

		return $this->respondCreated( $item->to_array() );
	}


	/**
	 * Updates one item from the collection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function update_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = $this->get_store()->find_single( $id );
		if ( ! ( is_array( $item ) || $item instanceof NewsSource ) ) {
			return $this->respondNotFound( null, 'No item found.' );
		}

		$this->get_store()->update( $request->get_params() );
		$item = $this->get_store()->find_single( $id );

		return $this->respondOK( $item );
	}

	/**
	 * Deletes one item from the collection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = $this->get_store()->find_single( $id );
		if ( ! ( is_array( $item ) || $item instanceof Data ) ) {
			return $this->respondNotFound( null, 'No item found.' );
		}

		$this->get_store()->delete( $id );

		return $this->respondOK( $item );
	}

	/**
	 * Batch operation
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function batch_operation( $request ) {
		$action  = $request->get_param( 'action' );
		$payload = $request->get_param( 'payload' );
		if ( 'copy' === $action ) {
			NewsSource::create_from_news_sync_settings();
		} else {
			$this->get_store()->batch( $action, $payload );
		}

		return $this->respondAccepted();
	}
}

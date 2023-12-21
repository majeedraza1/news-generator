<?php

namespace StackonetNewsGenerator\OpenAIApi\Rest;

use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use StackonetNewsGenerator\REST\ApiController;
use Stackonet\WP\Framework\Traits\ApiCrudOperations;
use WP_REST_Request;
use WP_REST_Server;

/**
 * ApiResponseLogController class
 */
class ApiResponseLogController extends ApiController {
	use ApiCrudOperations;

	/**
	 * @var self
	 */
	private static $instance;
	protected $rest_base = 'openai-logs';

	/**
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'rest_api_init', array( self::$instance, '_register_routes' ) );
		}

		return self::$instance;
	}

	public function get_store() {
		return new ApiResponseLog();
	}

	public function _register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'args'                => $this->get_collection_params(),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/news-logs/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item_logs' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
	}


	/**
	 * Retrieves a collection of items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$status   = $request->get_param( 'status' );
		$status   = in_array( $status, array( 'error', 'success' ), true ) ? $status : '';
		$search   = $request->get_param( 'search' );
		$group    = $request->get_param( 'group' );

		$groups_count = ApiResponseLog::get_groups_count();

		if ( ! empty( $search ) ) {
			$items = ApiResponseLog::search( $search );
		} else {
			$query = ApiResponseLog::get_query_builder();
			if ( $status ) {
				$query->where( 'response_type', $status );
			}
			if ( $group ) {
				$query->where( 'group', $group );
			}

			$query->limit( $per_page );
			$query->page( $page );
			$query->order_by( 'id', 'DESC' );
			$items = $query->get();
		}

		$counts     = $this->get_store()->count_records( $request->get_params() );
		$count      = $counts[ $status ] ?? $counts['all'];
		$pagination = static::get_pagination_data( $count, $per_page, $page );

		$data = array();
		foreach ( $items as $item ) {
			$data[] = new ApiResponseLog( $item );
		}

		return $this->respondOK(
			array(
				'items'        => $data,
				'groups_count' => $groups_count,
				'pagination'   => $pagination,
			)
		);
	}

	public function get_item_logs( WP_REST_Request $request ) {
		$article_id = (int) $request->get_param( 'id' );
		$article    = ( new ArticleStore() )->find_single( $article_id );
		if ( ! $article ) {
			return $this->respondNotFound();
		}

		$logs = ApiResponseLog::get_logs( $article_id );

		return $this->respondOK(
			array(
				'items' => $logs,
			)
		);
	}
}

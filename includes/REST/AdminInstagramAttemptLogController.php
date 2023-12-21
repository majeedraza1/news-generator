<?php

namespace StackonetNewsGenerator\REST;

use StackonetNewsGenerator\OpenAIApi\Models\InstagramAttemptLog;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * AdminInstagramAttemptLogController class
 */
class AdminInstagramAttemptLogController extends ApiController {
	use ApiPermissionChecker;

	/**
	 * The instance of the class
	 *
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
			'/admin/instagram-log',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_items' ),
					'args'     => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param  WP_REST_Request  $request  Full details about the request.
	 *
	 * @return WP_REST_Response Response object on success.
	 */
	public function get_items( $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$log_for  = $request->get_param( 'log_for' );
		$log_for  = in_array( $log_for, InstagramAttemptLog::LOG_FOR, true ) ?
			$log_for : InstagramAttemptLog::LOG_FOR_INSTAGRAM;

		$query = InstagramAttemptLog::get_query_builder();
		$query->where( 'log_for', $log_for );
		$query->order_by( 'id', 'DESC' );
		$query->limit( $per_page );
		$query->page( $page );
		$items = $query->get();
		$data  = array();
		foreach ( $items as $item ) {
			$data[] = new InstagramAttemptLog( $item );
		}

		$count      = InstagramAttemptLog::count_records();
		$pagination = static::get_pagination_data( $count['all'], $per_page, $page );

		return $this->respondOK(
			array(
				'items'      => $data,
				'pagination' => $pagination,
				'sql'        => $query->get_query_sql(),
			)
		);
	}
}

<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

use Stackonet\WP\Framework\Traits\ApiCrudOperations;
use StackonetNewsGenerator\REST\ApiController;

/**
 * AdminNewsCrawlerLogController class
 */
class AdminNewsCrawlerLogController extends ApiController {
	use ApiCrudOperations;

	/**
	 * Get rest base
	 *
	 * @var string
	 */
	protected $rest_base = 'news-crawler-logs';

	/**
	 * Store related to controller
	 *
	 * @return NewsCrawlerLog
	 */
	public function get_store() {
		return new NewsCrawlerLog();
	}

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
	 * Retrieves a collection of items.
	 *
	 * @param  WP_REST_Request  $request  Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$status   = (string) $request->get_param( 'status' );

		$items      = NewsCrawlerLog::find_multiple( $request->get_params() );
		$counts     = NewsCrawlerLog::count_records( $request->get_params() );
		$count      = $counts[ $status ] ?? $counts['all'];
		$pagination = static::get_pagination_data( $count, $per_page, $page );

		$statuses = [];
		if ( method_exists( $this->get_store(), 'get_statuses_count' ) ) {
			$statuses = $this->get_store()->get_statuses_count( $status );
		}

		return $this->respondOK(
			[
				'items'      => $items,
				'pagination' => $pagination,
				'statuses'   => $statuses,
			]
		);

	}
}

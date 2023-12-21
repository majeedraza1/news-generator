<?php

namespace StackonetNewsGenerator\Modules\Site\REST;

use Stackonet\WP\Framework\Traits\ApiCrudOperations;
use StackonetNewsGenerator\Modules\Site\Models\NewsToSiteLog;
use StackonetNewsGenerator\REST\ApiController;

/**
 * AdminNewsToSiteLogController class
 */
class AdminNewsToSiteLogController extends ApiController {
	use ApiCrudOperations;

	/**
	 * @var self
	 */
	private static $instance;

	protected $rest_base = 'admin/news-to-site-logs';

	public function get_store() {
		return new NewsToSiteLog();
	}

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
}

<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi\Rest;

use Stackonet\WP\Framework\Traits\ApiCrudOperations;
use StackonetNewsGenerator\EventRegistryNewsApi\ClientResponseLog;
use StackonetNewsGenerator\REST\ApiController;

/**
 * AdminNewsApiLogController class
 */
class AdminNewsApiLogController extends ApiController {
	use ApiCrudOperations;

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Rest base
	 *
	 * @var string
	 */
	protected $rest_base = 'newsapi-logs';

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
	 * Get store class
	 *
	 * @return ClientResponseLog
	 */
	public function get_store() {
		return new ClientResponseLog();
	}
}

<?php

namespace TeraPixelNewsGenerator\Modules\ExternalLink;

use TeraPixelNewsGenerator\Modules\ExternalLink\Models\ExternalLink;
use TeraPixelNewsGenerator\REST\ApiController;
use Stackonet\WP\Framework\Traits\ApiCrudOperations;

/**
 * AdminRestController class
 */
class AdminRestController extends ApiController {
	use ApiCrudOperations;

	/**
	 * The instance of the class
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Rest base
	 *
	 * @var string
	 */
	protected $rest_base = 'external-links';

	/**
	 * The store class
	 *
	 * @return ExternalLink
	 */
	public function get_store(): ExternalLink {
		return new ExternalLink();
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

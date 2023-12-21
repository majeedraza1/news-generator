<?php

namespace StackonetNewsGenerator\Modules\Keyword\Rest;

use Stackonet\WP\Framework\Traits\ApiCrudOperations;
use StackonetNewsGenerator\Modules\Keyword\Models\Keyword;
use StackonetNewsGenerator\REST\ApiController;

/**
 * Class AdminKeywordController
 *
 * This class is responsible for handling keyword-related actions in the admin area.
 * It extends the ApiController class to inherit common functionality.
 *
 * @package AdminControllers
 */
class AdminKeywordController extends ApiController {
	use ApiCrudOperations;

	/**
	 * @var self
	 */
	private static $instance;

	/**
	 * Rest base
	 *
	 * @var string
	 */
	protected $rest_base = 'admin/keywords';

	/**
	 * @return Keyword
	 */
	public function get_store() {
		return new Keyword();
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

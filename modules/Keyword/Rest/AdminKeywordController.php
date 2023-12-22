<?php

namespace StackonetNewsGenerator\Modules\Keyword\Rest;

use Stackonet\WP\Framework\Traits\ApiCrudOperations;
use StackonetNewsGenerator\Modules\Keyword\BackgroundKeywordToNews;
use StackonetNewsGenerator\Modules\Keyword\Models\Keyword;
use StackonetNewsGenerator\REST\ApiController;
use WP_REST_Server;

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
			add_action( 'rest_api_init', array( self::$instance, '_register_routes' ) );
		}

		return self::$instance;
	}

	public function _register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_items' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			)
		);
	}

	/**
	 * Sync keywords
	 *
	 * @return \WP_REST_Response
	 */
	public function sync_items() {
		BackgroundKeywordToNews::sync();

		return $this->respondAccepted();
	}
}

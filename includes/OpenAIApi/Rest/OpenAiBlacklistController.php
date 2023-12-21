<?php

namespace StackonetNewsGenerator\OpenAIApi\Rest;

use StackonetNewsGenerator\OpenAIApi\Models\BlackListWords;
use StackonetNewsGenerator\REST\ApiController;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use WP_REST_Server;

class OpenAiBlacklistController extends ApiController {
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
			'/openai/blacklist-words',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'is_editor' ]
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'is_editor' ]
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/openai/blacklist-words/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'is_editor' ]
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'is_editor' ]
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'is_editor' ]
				],
			]
		);
	}

	public function get_items( $request ) {
		return $this->respondOK( [
			'items' => ( new BlackListWords() )->get_options(),
		] );
	}

	public function create_item( $request ) {
		$phrase = $request->get_param( 'phrase' );
		$record = ( new BlackListWords() )->create( [ 'phrase' => $phrase ] );

		return $this->respondCreated( $record );
	}

	public function get_item( $request ) {
		$id   = $request->get_param( 'id' );
		$item = ( new BlackListWords() )->get_option( $id );

		return $this->respondOK( $item );
	}

	public function update_item( $request ) {
		$id     = $request->get_param( 'id' );
		$phrase = $request->get_param( 'phrase' );
		$record = ( new BlackListWords() )->update( [
			'id'     => (int) $id,
			'phrase' => $phrase
		] );

		return $this->respondOK( $record );
	}

	public function delete_item( $request ) {
		$id = $request->get_param( 'id' );
		( new BlackListWords() )->delete( (int) $id );

		return $this->respondOK();
	}
}

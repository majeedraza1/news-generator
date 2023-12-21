<?php

namespace StackonetNewsGenerator\Modules\Site\REST;

use StackonetNewsGenerator\Modules\Site\Site;
use StackonetNewsGenerator\Modules\Site\SiteStore;
use StackonetNewsGenerator\REST\ApiController;
use WP_REST_Request;
use WP_REST_Server;

/**
 * SiteController class
 */
class SiteController extends ApiController {
	/**
	 * @var SiteController
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
		register_rest_route( $this->namespace, '/send-request', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'send_request' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'site_url' => [
					'description'       => 'The site from where its requesting.',
					'type'              => 'string',
					'sanitize_callback' => 'esc_url',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'request'  => [
					'description'       => 'The request it\'s going to make',
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
			]
		] );
	}

	public function send_request( WP_REST_Request $request ) {
		$site_url = $request->get_param( 'site_url' );
		$_request = $request->get_param( 'request' );

		if ( empty( $site_url ) || empty( $_request ) ) {
			return $this->respondUnprocessableEntity();
		}

		$site_store = new SiteStore();
		$site_info  = $site_store->find_by_url( $site_url );

		if ( ! $site_info ) {
			return $this->respondNotFound();
		}

		$site = new Site( $site_info );
		if ( 'update_sync_settings' == $_request ) {
			$response = $site->get_sync_settings();
			if ( is_wp_error( $response ) ) {
				return $this->respondWithWpError( $response );
			}
			if ( is_array( $response ) && isset( $response['data']['sync_settings'] ) ) {
				$site_store->update( [
					'id'            => $site_info['id'],
					'sync_settings' => $response['data']['sync_settings'],
				] );
			}
		}

		return $this->respondOK();
	}
}

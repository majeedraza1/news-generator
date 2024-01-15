<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi\Rest;

use Stackonet\WP\Framework\Traits\ApiCrudOperations;
use StackonetNewsGenerator\EventRegistryNewsApi\Category;
use StackonetNewsGenerator\EventRegistryNewsApi\Country;
use StackonetNewsGenerator\EventRegistryNewsApi\Language;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettings;
use StackonetNewsGenerator\REST\ApiController;
use WP_REST_Request;
use WP_REST_Response;

/**
 * AdminSyncSettingController class
 */
class AdminSyncSettingController extends ApiController {
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
	protected $rest_base = 'newsapi-sync-settings';

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
	 * @return SyncSettings
	 */
	public function get_store() {
		return new SyncSettings();
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param  WP_REST_Request  $request  Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$items      = SyncSettings::find_multiple( array( 'per_page' => 100 ) );
		$pagination = static::get_pagination_data( count( $items ), 100, 1 );

		return $this->respondOK(
			array(
				'settings'         => $items,
				'pagination'       => $pagination,
				'countries'        => Country::countries_for_select_options(),
				'categories'       => Category::categories_for_select_options(),
				'languages'        => Language::languages_for_select_options(),
				'news_sync_fields' => SyncSettings::news_sync_fields(),
			)
		);
	}

	/**
	 * Creates one item from the collection.
	 *
	 * @param  WP_REST_Request  $request  Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		$settings = SyncSettings::sanitize( $request->get_params() );
		$response = SyncSettings::create_or_update( $settings );
		if ( $response instanceof SyncSettings ) {
			return $this->respondCreated(
				array(
					'setting'       => $response,
					'sanitize_data' => $settings,
				)
			);
		}

		return $this->respondInternalServerError();
	}
}

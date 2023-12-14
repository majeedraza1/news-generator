<?php

namespace TeraPixelNewsGenerator\Modules\Site\REST;

use TeraPixelNewsGenerator\BackgroundProcess\CopyNewsImage;
use TeraPixelNewsGenerator\BackgroundProcess\ProcessNewsTag;
use TeraPixelNewsGenerator\EventRegistryNewsApi\ArticleStore;
use TeraPixelNewsGenerator\EventRegistryNewsApi\Category;
use TeraPixelNewsGenerator\Modules\Site\Site;
use TeraPixelNewsGenerator\Modules\Site\SiteStore;
use TeraPixelNewsGenerator\OpenAIApi\News;
use TeraPixelNewsGenerator\OpenAIApi\Stores\NewsStore;
use TeraPixelNewsGenerator\REST\ApiController;
use Stackonet\WP\Framework\Supports\Sanitize;
use Stackonet\WP\Framework\Supports\Validate;
use Stackonet\WP\Framework\Traits\ApiCrudOperations;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class AdminSiteController extends ApiController {
	use ApiCrudOperations;

	/**
	 * @var self
	 */
	private static $instance;
	protected $rest_base = 'admin/news-sites';

	/**
	 * Get store class
	 *
	 * @return SiteStore
	 */
	public function get_store(): SiteStore {
		return new SiteStore();
	}

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function _register_routes() {
		$args = [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'send_to_sites' ],
			'permission_callback' => [ $this, 'create_item_permissions_check' ],
			'args'                => [
				'news_ids' => [
					'description'       => 'List of news ids.',
					'type'              => 'array',
					'sanitize_callback' => [ Sanitize::class, 'deep' ],
					'validate_callback' => 'rest_validate_request_arg',
				],
				'force'    => [
					'description'       => 'Should send instantly.',
					'type'              => 'bool',
					'sanitize_callback' => [ Sanitize::class, 'checked' ],
					'validate_callback' => 'rest_validate_request_arg',
				],
			],
		];
		register_rest_route( $this->namespace, $this->rest_base . '/send-news-to-sites', $args );
		register_rest_route( $this->namespace, $this->rest_base . '/send-general-data-to-sites', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'send_data_to_sites' ],
			'permission_callback' => [ $this, 'create_item_permissions_check' ],
		] );
		register_rest_route( $this->namespace, $this->rest_base . '/copy-image', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'copy_image' ],
			'permission_callback' => [ $this, 'create_item_permissions_check' ],
			'args'                => [
				'id' => [
					'type'              => 'integer',
					'sanitize_callback' => [ Sanitize::class, 'int' ],
					'validate_callback' => 'rest_validate_request_arg',
				]
			]
		] );
		register_rest_route( $this->namespace, $this->rest_base . '/sync-tag', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sync_tags' ],
			'permission_callback' => [ $this, 'create_item_permissions_check' ],
			'args'                => [
				'id' => [
					'type'              => 'integer',
					'sanitize_callback' => [ Sanitize::class, 'int' ],
					'validate_callback' => 'rest_validate_request_arg',
				]
			]
		] );
		register_rest_route( $this->namespace, $this->rest_base . '/sync-categories', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sync_categories' ],
			'permission_callback' => [ $this, 'create_item_permissions_check' ],
			'args'                => [
				'id' => [
					'type'              => 'integer',
					'sanitize_callback' => [ Sanitize::class, 'int' ],
					'validate_callback' => 'rest_validate_request_arg',
				]
			]
		] );
	}

	public function send_to_sites( WP_REST_Request $request ): WP_REST_Response {
		$news_ids = $request->get_param( 'news_ids' );
		$news_ids = array_map( 'intval', $news_ids );
		$force    = Validate::checked( $request->get_param( 'force' ) );
		/** @var News[] $news */
		$news = ( new NewsStore() )->find_multiple( [ 'id__in' => $news_ids ] );
		if ( ! $news ) {
			return $this->respondNotFound();
		}
		foreach ( $news as $_news ) {
			$_news->send_to_sites( $force );
		}

		if ( ! $force ) {
			return $this->respondAccepted();
		}

		return $this->respondOK();
	}

	public function send_data_to_sites( WP_REST_Request $request ): WP_REST_Response {
		$id           = (int) $request->get_param( 'id' );
		$site_store   = new SiteStore();
		$site_setting = $site_store->find_single( $id );
		if ( ! $site_setting ) {
			return $this->respondNotFound();
		}

		$response = ( new Site( $site_setting ) )->send_general_data();
		if ( is_wp_error( $response ) ) {
			return $this->respondWithWpError( $response );
		}
		$response = ( new Site( $site_setting ) )->get_sync_settings();
		if ( ! is_wp_error( $response ) ) {
			if ( is_array( $response ) && isset( $response['data']['sync_settings'] ) ) {
				$site_store->update( [
					'id'            => $site_setting['id'],
					'sync_settings' => $response['data']['sync_settings'],
				] );
			}
		}

		$store_info = $site_store->find_single( $id );

		return $this->respondOK( $store_info );
	}

	public function copy_image( WP_REST_Request $request ): WP_REST_Response {
		$openai_news_id = (int) $request->get_param( 'id' );
		$news           = ( new NewsStore() )->find_single( $openai_news_id );
		if ( ! $news ) {
			return $this->respondNotFound( null, 'No news item found for that id #' . $openai_news_id );
		}

		$source_id = intval( $news['source_id'] );
		$article   = ( new ArticleStore() )->find_single( $source_id );
		if ( ! $article ) {
			return $this->respondNotFound( null, 'Source news is not found' );
		}

		if ( ! Validate::url( $article['image'] ) ) {
			return $this->respondNotFound( null, 'No image url found' );
		}

		$attachment_id = CopyNewsImage::copy_image_as_webp( $article['image'], $news['title'] );
		if ( is_wp_error( $attachment_id ) ) {
			return $this->respondWithWpError( $attachment_id );
		}

		CopyNewsImage::add_attachment_info(
			intval( $attachment_id ),
			intval( $news['source_id'] ),
			intval( $news['id'] )
		);

		return $this->respondOK( [ 'id' => $attachment_id ] );
	}

	public function sync_tags( WP_REST_Request $request ): WP_REST_Response {
		$id           = (int) $request->get_param( 'id' );
		$site_store   = new SiteStore();
		$site_setting = $site_store->find_single( $id );
		if ( ! $site_setting ) {
			return $this->respondNotFound();
		}
		$site = new Site( $site_setting );
		$tags = $site->get_tags_list();
		if ( is_wp_error( $tags ) ) {
			return $this->respondWithWpError( $tags );
		}

		$bg_task = ProcessNewsTag::init();
		foreach ( $tags as $slug => $name ) {
			$bg_task->push_to_queue( [
				'task'    => 'sync_tags_with_site',
				'site_id' => $site->get_id(),
				'slug'    => $slug,
				'name'    => $name,
			] );
		}

		return $this->respondOK( [
			'tags_count' => count( $tags )
		] );
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

	public function sync_categories( WP_REST_Request $request ): WP_REST_Response {
		$id           = (int) $request->get_param( 'id' );
		$site_store   = new SiteStore();
		$site_setting = $site_store->find_single( $id );
		if ( ! $site_setting ) {
			return $this->respondNotFound();
		}
		$site       = new Site( $site_setting );
		$categories = $site->get_categories_list();
		if ( is_wp_error( $categories ) ) {
			return $this->respondWithWpError( $categories );
		}

		$cats = [];
		foreach ( $categories as $category ) {
			$cats[ $category['slug'] ] = $category['name'];
		}

		Category::update_categories( $cats );

		return $this->respondOK( [
			'tags_count' => count( $categories )
		] );
	}
}

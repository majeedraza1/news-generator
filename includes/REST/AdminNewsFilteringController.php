<?php

namespace StackonetNewsGenerator\REST;

use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNews;
use StackonetNewsGenerator\OpenAIApi\Models\InterestingNews;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use WP_REST_Server;

class AdminNewsFilteringController extends ApiController {
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
			'/admin/news-filtering',
			[
				[
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [ $this, 'get_items' ],
					'args'     => $this->get_collection_params(),
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/admin/news-filtering/(?P<id>\d+)',
			[
				[
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [ $this, 'get_item' ],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/admin/news-filtering/(?P<id>\d+)/recalculate',
			[
				[
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'recalculate_item' ],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/admin/news-filtering/(?P<id>\d+)/recreate',
			[
				[
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'recreate_item' ],
				],
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_items( $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );

		$args = [
			'page'     => $page,
			'per_page' => $per_page,
		];

		$items       = InterestingNews::find_multiple( $args );
		$total_items = InterestingNews::count_records( $args );
		$pagination  = self::get_pagination_data( $total_items['all'], $per_page, $page );

		return $this->respondOK(
			[
				'items'      => $items,
				'pagination' => $pagination,
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = InterestingNews::find_single( $id );
		if ( ! $item instanceof InterestingNews ) {
			return $this->respondNotFound();
		}
		$source_news = $item->get_source_news();
		$openai_news = [];
		if ( count( $item->get_openai_news_ids() ) ) {
			$openai_news = ( new NewsStore() )->find_multiple( [
				'id__in' => $item->get_openai_news_ids(),
				'count'  => count( $item->get_openai_news_ids() )
			] );
		}

		return $this->respondOK( [
			'item'        => $item,
			'source_news' => $source_news,
			'openai_news' => $openai_news,
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function recalculate_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = InterestingNews::find_single( $id );
		if ( ! $item instanceof InterestingNews ) {
			return $this->respondNotFound();
		}

		$item->recalculate_suggested_news_ids();
		$item->recalculate_openai_news_ids();

		return $this->respondOK( [
			'item'        => $item,
			'source_news' => $item->get_source_news(),
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function recreate_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = InterestingNews::find_single( $id );
		if ( ! $item instanceof InterestingNews ) {
			return $this->respondNotFound();
		}

		foreach ( $item->get_suggested_news_ids() as $id ) {
			OpenAiReCreateNews::add_to_sync( $id );
		}

		return $this->respondAccepted();
	}
}

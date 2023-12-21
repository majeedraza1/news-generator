<?php

namespace StackonetNewsGenerator\REST;

use DateTime;
use StackonetNewsGenerator\BackgroundProcess\OpenAiFindInterestingNews;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNews;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use Stackonet\WP\Framework\Supports\Validate;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use WP_REST_Request;
use WP_REST_Server;

class AdminNewsController extends ApiController {
	use ApiPermissionChecker;

	/**
	 * @var self
	 */
	private static $instance;

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/admin/news',
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
			'/admin/news/(?P<id>\d+)',
			[
				[
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [ $this, 'get_item' ],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/admin/news/batch',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'batch_operation' ],
					'permission_callback' => [ $this, 'is_editor' ],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/admin/news/(?P<id>\d+)/openai',
			[
				[
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'recreate_item' ],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/admin/news/openai-recreate',
			[
				[
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'recreate_items' ],
				],
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_items( $request ) {
		$per_page               = (int) $request->get_param( 'per_page' );
		$page                   = (int) $request->get_param( 'page' );
		$date                   = $request->get_param( 'date' );
		$country                = $request->get_param( 'country' );
		$category               = $request->get_param( 'category' );
		$search                 = $request->get_param( 'search' );
		$in_sync                = Validate::checked( $request->get_param( 'in_sync' ) );
		$pending_openai_request = OpenAiReCreateNews::init()->get_pending_background_tasks();

		$args = [
			'country'  => $country,
			'category' => $category,
			'in_sync'  => $in_sync,
			'search'   => $search,
		];
		if ( Validate::date( $date ) ) {
			$date_time = DateTime::createFromFormat( 'Y-m-d', $date );

			$start_datetime = clone $date_time;
			$start_datetime->modify( 'midnight' );

			$end_datetime = clone $date_time;
			$end_datetime->modify( 'tomorrow' );
			$end_datetime->setTimestamp( $end_datetime->getTimestamp() - 1 );

			$args['datetime_start'] = $start_datetime->format( 'Y-m-d H:i:s' );
			$args['datetime_end']   = $end_datetime->format( 'Y-m-d H:i:s' );
		}

		$store = new ArticleStore();

		$query = $store->get_query_builder();
		$query->limit( $per_page );
		$query->page( $page );
		$query->order_by( 'news_datetime', 'DESC' );
		if ( ! empty( $category ) ) {
			$query->where( 'primary_category', $category );
		}
		if ( ! empty( $search ) ) {
			$query->where( 'title', '%' . $search . '%', 'LIKE' );
		}
		$results = $query->get();

		$total_items = $store->count_records( $args );
		$pagination  = self::get_pagination_data( $total_items['all'], $per_page, $page );

		$categories = ArticleStore::get_unique_primary_categories( $in_sync ? $pending_openai_request : [] );

		return $this->respondOK(
			[
				'items'                  => self::format_items_for_response( $results ),
				'pagination'             => $pagination,
				'categories'             => $categories,
				'countries'              => [],
				'pending_openai_request' => OpenAiReCreateNews::init()->get_pending_background_tasks(),
			]
		);
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

	/**
	 * @param array $items
	 *
	 * @return array
	 */
	public static function format_items_for_response( array $items ): array {
		$response = [];
		foreach ( $items as $item ) {
			$response[] = [
				'id'               => (int) $item['id'],
				'title'            => $item['title'],
				'source_title'     => $item['source_title'],
				'source_uri'       => $item['source_uri'],
				'image'            => $item['image'],
				'category'         => $item['primary_category'],
				'news_datetime'    => mysql_to_rfc3339( $item['news_datetime'] ),
				'sync_datetime'    => $item['created_at'] ? mysql_to_rfc3339( $item['created_at'] ) : '',
				'openai_news_id'   => (int) $item['openai_news_id'],
				'body_words_count' => (int) $item['body_words_count'],
				'news_filtering'   => Validate::checked( $item['news_filtering'] ),
				'openai_error'     => (string) $item['openai_error'],
			];
		}

		return $response;
	}

	public function get_item( $request ) {
		$id     = (int) $request->get_param( 'id' );
		$record = ( new ArticleStore() )->find_single( $id );
		if ( ! $record ) {
			return $this->respondNotFound();
		}

		$record['body'] = nl2br( $record['body'] );

		if ( $record['openai_news_id'] ) {
			$ai_news = ( new NewsStore() )->find_single( (int) $record['openai_news_id'] );
			if ( $ai_news ) {
				$record['openai_news'] = new News( $ai_news );
			}
		}

		return $this->respondOK( $record );
	}

	public function recreate_item( WP_REST_Request $request ) {
		$start_time = microtime( true );
		$article_id = (int) $request->get_param( 'id' );
		$force      = Validate::checked( $request->get_param( 'force' ) );
		$article    = ArticleStore::find_by_id( $article_id );
		if ( ! $article instanceof Article ) {
			return $this->respondNotFound();
		}

		if ( ! $force ) {
			return $this->respondAccepted(
				[
					'pending_tasks' => OpenAiReCreateNews::add_to_sync( $article_id ),
				]
			);
		}

		$news_array = NewsCompletion::to_news( $article, $start_time, true );
		if ( is_wp_error( $news_array ) ) {
			// Update article.
			( new ArticleStore() )->update(
				[
					'id'           => $article_id,
					'openai_error' => $news_array->get_error_message(),
				]
			);

			return $this->respondWithWpError( $news_array );
		}

		$ai_news_id = $news_array['id'] ?? 0;

		$ai_news = ( new NewsStore() )->find_single( (int) $ai_news_id );
		// Send news to sites.
		if ( $ai_news instanceof News ) {
			$ai_news->send_to_sites();
		}

		return $this->respondCreated(
			[
				'id'             => $article_id,
				'openai_news_id' => $ai_news_id,
				'openai_news'    => $ai_news,
			]
		);
	}

	public function recreate_items( WP_REST_Request $request ) {
		$ids = (array) $request->get_param( 'ids' );
		$ids = array_map( 'intval', $ids );

		if ( count( $ids ) ) {
			foreach ( $ids as $id ) {
				OpenAiReCreateNews::add_to_sync( $id, true );
			}
		}

		return $this->respondAccepted();
	}

	public function batch_operation( WP_REST_Request $request ) {
		$action = $request->get_param( 'action' );
		$ids    = $request->get_param( 'ids' );
		$ids    = is_array( $ids ) ? array_map( 'intval', $ids ) : [];

		if ( 'delete' === $action && count( $ids ) ) {
			( new ArticleStore() )->batch_delete( $ids );
		}

		if ( 'interesting-filter' === $action && count( $ids ) ) {
			OpenAiFindInterestingNews::add_to_sync( $ids );
		}

		return $this->respondAccepted();
	}
}

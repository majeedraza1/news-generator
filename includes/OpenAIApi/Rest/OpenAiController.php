<?php

namespace StackonetNewsGenerator\OpenAIApi\Rest;

use Stackonet\WP\Framework\Abstracts\Data;
use Stackonet\WP\Framework\Supports\Validate;
use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsTitle;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncInstagramFields;
use StackonetNewsGenerator\BackgroundProcess\ProcessNewsTag;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\Category;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettingsStore;
use StackonetNewsGenerator\Modules\Site\BackgroundSendTagsToSite;
use StackonetNewsGenerator\Modules\Site\SiteStore;
use StackonetNewsGenerator\Modules\Site\Stores\NewsToSiteLogStore;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Setting;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsTagStore;
use StackonetNewsGenerator\REST\ApiController;
use StackonetNewsGenerator\Supports\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class OpenAiController extends ApiController {
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
			'/openai/news',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_items' ),
					'args'     => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_news' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/openai/news-tags',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_news_tags' ),
					'args'     => $this->get_collection_params(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/openai/news-tags/batch',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'run_tags_batch_action' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/openai/news/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/openai/news/(?P<id>\d+)/sync',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'sync_item' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/openai/news/(?P<id>\d+)/instagram',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'instagram_feed' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/openai/completions',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'create_item' ),
					'args'     => array(
						'id'          => array(
							'description'       => 'Id of the news record.',
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
							'required'          => true,
						),
						'field'       => array(
							'description'       => 'Limit results to those matching a string.',
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
							'required'          => true,
							'enum'              => array( 'title', 'body', 'meta', 'tweet', 'facebook', 'tag' ),
						),
						'instruction' => array(
							'description'       => 'Instruction to edit content.',
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/openai/news/batch',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'batch_operation' ),
					'permission_callback' => array( $this, 'is_editor' ),
				),
			)
		);
	}

	public function get_items( $request ) {
		$per_page  = (int) $request->get_param( 'per_page' );
		$page      = (int) $request->get_param( 'page' );
		$search    = $request->get_param( 'search' );
		$filter_by = $request->get_param( 'filter_by' );
		$status    = $request->get_param( 'status' );

		$store = new NewsStore();

		$query = $store->get_query_builder();
		$query->limit( $per_page );
		$query->page( $page );
		if ( 'skipped-openai' === $status ) {
			$query->where( 'openai_skipped', 1 );
		} elseif ( 'openai-complete' === $status ) {
			$query->where( 'openai_skipped', 0 );
			$query->where( 'sync_status', 'complete' );
		} elseif ( 'complete' !== $status ) {
			$query->where( 'openai_skipped', 0 );
		}
		if ( $status && ! in_array( $status, array( 'skipped-openai', 'openai-complete' ), true ) ) {
			$query->where( 'sync_status', $status );
		}
		if ( 'use_for_instagram' === $filter_by ) {
			$query->where( 'use_for_instagram', 1 );
		}
		if ( 'important_for_tweet' === $filter_by ) {
			$query->where( 'important_for_tweet', 1 );
		}
		if ( 'has_image_id' === $filter_by ) {
			$query->where( 'image_id', 0, '>' );
		}
		if ( ! empty( $search ) ) {
			if ( is_numeric( $search ) ) {
				$query->where( array( array( 'id', $search ), array( 'source_id', $search ) ), 'OR' );
			} else {
				$query->where( 'title', '%' . $search . '%', 'LIKE' );
			}
		}
		$query->order_by( 'id', 'DESC' );

		$items  = $query->get();
		$counts = $store->count_records( $request->get_params() );
		$count  = $counts[ $status ] ?? $counts['all'];
		if ( ! empty( $search ) ) {
			$count = count( $items );
		}
		$pagination = static::get_pagination_data( $count, $per_page, $page );

		$items = array_map( array( $this, 'prepare_collection_item_for_response' ), $items );

		$news_ids = wp_list_pluck( $items, 'id' );
		$logs     = NewsToSiteLogStore::find_by_news_id( $news_ids );
		foreach ( $items as $item_index => $item ) {
			foreach ( $logs as $log ) {
				if ( intval( $item['id'] ) === intval( $log['news_id'] ) ) {
					$items[ $item_index ]['remote_log'][] = $log;
				}
			}
		}

		$statuses = array(
			array(
				'key'    => 'complete',
				'label'  => 'All',
				'count'  => $counts['complete'] ?? 0,
				'active' => 'complete' === $status,
			),
			array(
				'key'    => 'openai-complete',
				'label'  => 'OpenAI Complete',
				'count'  => $counts['openai-complete'] ?? 0,
				'active' => 'openai-complete' === $status,
			),
			array(
				'key'    => 'in-progress',
				'label'  => 'OpenAI In-Progress',
				'count'  => $counts['in-progress'] ?? 0,
				'active' => 'in-progress' === $status,
			),
			array(
				'key'    => 'skipped-openai',
				'label'  => 'Skipped OpenAI',
				'count'  => $counts['skipped-openai'] ?? 0,
				'active' => 'skipped-openai' === $status,
			),
			array(
				'key'    => 'fail',
				'label'  => 'OpenAI Fail',
				'count'  => $counts['fail'] ?? 0,
				'active' => 'fail' === $status,
			),
		);

		// All = OpenAI complete & OpenAI skipped

		return $this->respondOK(
			array(
				'items'                             => $items,
				'pagination'                        => $pagination,
				'count'                             => $counts,
				'statuses'                          => $statuses,
				'news_to_site_logs'                 => $logs,
				'query_args'                        => $request->get_params(),
				'sql'                               => $query->get_query_sql(),
				'categories'                        => Category::get_categories(),
				'default_category'                  => Category::get_default_category(),
				'important_news_for_tweets_enabled' => Setting::is_important_news_for_tweets_enabled(),
				'sync_settings_options'             => SyncSettingsStore::get_settings_as_select_options(),
			)
		);
	}

	public function create_news( WP_REST_Request $request ) {
		$news_title        = $request->get_param( 'news_title' );
		$title_words_count = Utils::str_word_count_utf8( $news_title );
		if ( $title_words_count < 3 ) {
			return $this->respondUnprocessableEntity(
				'news_title_length_error',
				'News title is too short. Add least 3 words required.'
			);
		}
		$news_content     = $request->get_param( 'news_content' );
		$body_words_count = Utils::str_word_count_utf8( $news_content );
		if ( $body_words_count < 100 ) {
			return $this->respondUnprocessableEntity(
				'news_content_length_error',
				sprintf(
					'News content is too short (%s words). Add least 100 words required. Recommender words 300 or more.',
					$body_words_count
				)
			);
		}

		$image_id         = (int) $request->get_param( 'image_id' );
		$extra_images     = (array) $request->get_param( 'extra_images' );
		$extra_images_ids = array_map( 'intval', $extra_images );
		$extra_videos     = (array) $request->get_param( 'extra_videos' );
		$extra_videos_ids = array_map( 'intval', $extra_videos );

		$news_category = $request->get_param( 'news_category' );

		$slug       = sanitize_title_with_dashes( $news_title, '', 'save' );
		$article_id = ( new ArticleStore() )->create(
			array(
				'title'             => $news_title,
				'slug'              => mb_substr( $slug, 0, 250 ),
				'body'              => $news_content,
				'title_words_count' => $title_words_count,
				'body_words_count'  => $body_words_count,
				'image_id'          => $image_id,
				'news_datetime'     => current_time( 'mysql', true ),
				'primary_category'  => $news_category,
			)
		);

		$data = array(
			'source_id'        => $article_id,
			'title'            => $news_title,
			'body'             => $news_content,
			'sync_status'      => 'in-progress',
			'sync_setting_id'  => '',
			'live_news'        => 0,
			'image_id'         => $image_id,
			'created_via'      => 'manual',
			'primary_category' => $news_category,
			'openai_category'  => $news_category,
			'extra_images'     => count( $extra_images_ids ) ? maybe_serialize( $extra_images_ids ) : null,
			'extra_videos'     => count( $extra_videos_ids ) ? maybe_serialize( $extra_videos_ids ) : null,
		);

		$use_for_instagram = $request->get_param( 'use_for_instagram' );
		if ( Validate::checked( $use_for_instagram ) ) {
			$data['use_for_instagram']    = 1;
			$data['instagram_heading']    = sanitize_text_field( $request->get_param( 'instagram_heading' ) );
			$data['instagram_subheading'] = sanitize_text_field( $request->get_param( 'instagram_subheading' ) );
			$data['instagram_body']       = sanitize_textarea_field( $request->get_param( 'instagram_body' ) );
			$data['instagram_hashtag']    = sanitize_text_field( $request->get_param( 'instagram_hashtag' ) );
			$data['instagram_image_id']   = (int) $request->get_param( 'instagram_image_id' );
		}

		$id = ( new NewsStore() )->create( $data );
		if ( ! $id ) {
			return $this->respondInternalServerError();
		}

		( new ArticleStore() )->update(
			array(
				'id'             => $article_id,
				'openai_news_id' => $id,
			)
		);

		if ( Validate::checked( $request->get_param( 'sync_with_openai' ) ) ) {
			OpenAiReCreateNewsTitle::init()->push_to_queue( array( 'news_id' => $article_id ) );
		}

		$news = ( new NewsStore() )->find_single( $id );

		return $this->respondCreated( $news->to_array() );
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

	public function get_news_tags( $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$sort     = $request->get_param( 'sort' );
		$sort     = is_array( $sort ) ? $sort : array(
			array(
				'field' => 'count',
				'order' => 'DESC',
			),
		);

		$store = new NewsTagStore();

		$query = $store->get_query_builder();
		$query->limit( $per_page );
		$query->page( $page );
		foreach ( $sort as $_sort ) {
			$query->order_by( $_sort['field'], $_sort['order'] );
		}

		$items      = $query->get();
		$counts     = $store->count_records( $request->get_params() );
		$count      = $counts['all'] ?? 0;
		$pagination = static::get_pagination_data( $count, $per_page, $page );

		return $this->respondOK(
			array(
				'items'      => $items,
				'pagination' => $pagination,
				'query_args' => $request->get_params(),
			)
		);
	}

	public function run_tags_batch_action( WP_REST_Request $request ) {
		$valid_actions = array(
			'delete',
			'copy_from_existing_news',
			'generate_meta_description',
			'send_to_sites',
		);
		$action        = $request->get_param( 'action' );
		if ( ! in_array( $action, $valid_actions ) ) {
			return $this->respondUnprocessableEntity();
		}

		if ( 'delete' === $action ) {
			$min_count = (int) $request->get_param( 'min_count' );
			$min_count = max( 1, min( 10, $min_count ) );

			$affected = NewsTagStore::delete_tags_with_low_count( $min_count );

			return $this->respondOK(
				array(
					'message' => sprintf( 'Number of deleted tags %s', $affected ),
					'count'   => $affected,
				)
			);
		}

		if ( 'copy_from_existing_news' === $action ) {
			$limit = (int) $request->get_param( 'limit' );
			$limit = max( 10, min( 200, $limit ) );

			ProcessNewsTag::init()->push_to_queue(
				array(
					'limit' => $limit,
					'task'  => 'create_tags_from_news',
				)
			);
		}

		if ( 'generate_meta_description' === $action ) {
			$id        = (int) $request->get_param( 'id' );
			$min_count = (int) $request->get_param( 'min_count' );
			$min_count = max( 1, $min_count );

			if ( ! ( $id || $min_count ) ) {
				return $this->respondUnprocessableEntity( 'Please provide tag id or minimum count.' );
			}

			if ( $id ) {
				$description = NewsTagStore::generate_meta_description(
					$id,
					array(
						'source_type' => 'admin-ui-action',
						'source_id'   => $id,
					)
				);

				return $this->respondOK(
					array(
						'meta_description' => $description,
					)
				);
			} elseif ( $min_count ) {
				$store = new NewsTagStore();
				$query = $store->get_query_builder();
				$query->where( 'count', $min_count, '>' );
				$items = $query->get();
				$ids   = array();
				foreach ( $items as $item ) {
					ProcessNewsTag::init()->push_to_queue(
						array(
							'task' => 'generate_meta_description',
							'id'   => intval( $item['id'] ),
						)
					);
					$ids[] = intval( $item['id'] );
				}

				$this->respondAccepted(
					array(
						'message' => 'A background task is running to generate tags for the ids.',
						'ids'     => $ids,
					)
				);
			}
		}

		if ( 'send_to_sites' === $action ) {
			$sites   = ( new SiteStore() )->find_multiple();
			$tags    = NewsTagStore::get_tags_with_meta_description();
			$bg_task = BackgroundSendTagsToSite::init();
			foreach ( $sites as $site ) {
				foreach ( $tags as $tag ) {
					$bg_task->push_to_queue(
						array(
							'site_id' => $site['id'],
							'tag_id'  => $tag['id'],
						)
					);
				}
			}

			return $this->respondAccepted();
		}

		if ( 'clean_meta_description' === $action ) {
			$store = new NewsTagStore();
			$query = $store->get_query_builder();
			$query->where( 'meta_description', '%"%', 'LIKE' );
			$items = $query->get();

			foreach ( $items as $item ) {
				$meta = stripslashes( $item['meta_description'] );
				$meta = str_replace( '"', '', $meta );
				$store->update(
					array(
						'id'               => intval( $item['id'] ),
						'meta_description' => $meta,
					)
				);
			}
		}

		return $this->respondUnprocessableEntity();
	}

	public function get_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$news = ( new NewsStore() )->find_single( $id );
		if ( ! $news instanceof News ) {
			return $this->respondNotFound();
		}

		$logs = NewsToSiteLogStore::find_by_news_id( $news->get_id() );

		return $this->respondOK(
			array(
				'news'          => $news->to_array(),
				'logs'          => $news->get_logs(),
				'source_news'   => $news->get_source_news(),
				'sites'         => $news->get_sites_list(),
				'news_to_sites' => $logs,
			)
		);
	}

	public function create_item( $request ) {
		return $this->respondInternalServerError();
	}

	public function sync_item( WP_REST_Request $request ): WP_REST_Response {
		$start_time = microtime( true );
		$id         = (int) $request->get_param( 'id' );
		$news       = NewsStore::find_by_id( $id );
		if ( ! $news instanceof News ) {
			return $this->respondNotFound( null, 'There is no re-created news for that id #' . $id );
		}

		$source_id     = $news->get_source_id();
		$article_store = new ArticleStore();
		$article_data  = $article_store->find_single( $source_id );
		if ( ! $article_data ) {
			return $this->respondNotFound();
		}
		$article = new Article( $article_data );
		if ( $news->get_id() !== $article->get_openai_news_id() ) {
			$news->update_field( 'sync_status', 'fail' );

			return $this->respondOK();
		}
		$news_array = NewsCompletion::news_completions( $news, $start_time, true );
		if ( is_wp_error( $news_array ) ) {
			return $this->respondWithWpError( $news_array );
		}

		if ( $news_array->is_sync_complete() ) {
			$news->send_to_sites();
		}

		return $this->respondOK(
			array(
				'id'   => $id,
				'news' => $news_array,
			)
		);
	}

	public function instagram_feed( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$news = NewsStore::find_by_id( $id );
		if ( ! $news instanceof News ) {
			return $this->respondNotFound( null, 'There is no re-created news for that id #' . $id );
		}

		( new NewsStore() )->update(
			array(
				'id'                => $news->get_id(),
				'use_for_instagram' => 1,
			)
		);

		OpenAiSyncInstagramFields::add_to_queue( $news );
		OpenAiSyncInstagramFields::init()->dispatch();

		return $this->respondAccepted();
	}

	public function batch_operation( WP_REST_Request $request ) {
		$action = $request->get_param( 'action' );
		$ids    = $request->get_param( 'ids' );
		$ids    = is_array( $ids ) ? array_map( 'intval', $ids ) : array();

		if ( 'mark-fail' === $action && count( $ids ) ) {
			foreach ( $ids as $id ) {
				( new NewsStore() )->update(
					array(
						'id'          => $id,
						'sync_status' => 'fail',
					)
				);
			}
		}

		if ( 'mark-complete' === $action && count( $ids ) ) {
			foreach ( $ids as $id ) {
				( new NewsStore() )->update(
					array(
						'id'          => $id,
						'sync_status' => 'complete',
					)
				);
			}
		}
		if ( 'delete-fail' === $action ) {
			NewsStore::delete_failed_news();
		}
		if ( 'delete' === $action && count( $ids ) ) {
			( new NewsStore() )->batch_delete( $ids );
		}

		return $this->respondAccepted();
	}

	/**
	 * Prepares the collection item for the REST response.
	 *
	 * @param  mixed|Data  $item  The collection item.
	 *
	 * @return array|mixed Response object on success.
	 */
	public function prepare_collection_item_for_response( $item ) {
		if ( $item instanceof Data ) {
			return $item->to_array();
		}
		$news                    = new News( $item );
		$data                    = $news->to_array();
		$data['created']         = mysql_to_rfc3339( $data['created'] );
		$data['updated']         = mysql_to_rfc3339( $data['updated'] );
		$data['sync_step_done']  = $news->sync_step_done();
		$data['total_sync_step'] = $news->total_sync_step();
		$data['created_via']     = $news->get_prop( 'created_via' );
		$data['remote_log']      = array();

		return $data;
	}
}

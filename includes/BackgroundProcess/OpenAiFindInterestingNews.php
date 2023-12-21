<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettings;
use StackonetNewsGenerator\OpenAIApi\Models\InterestingNews;

/**
 * OpenAiFindInterestingNews class
 */
class OpenAiFindInterestingNews extends BackgroundProcessBase {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	public static $instance = null;

	/**
	 * Admin notice heading
	 *
	 * @var string
	 */
	protected $admin_notice_heading = 'A background task is running to process {{total_items}} items to find interesting news.';
	/**
	 * Action
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'bg_find_interesting_news';

	/**
	 * Add new data to sync
	 *
	 * @param  array  $news_ids
	 * @param  array  $sync_settings
	 *
	 * @return void
	 */
	public static function add_to_sync( array $news_ids, array $sync_settings = array() ) {
		if ( isset( $sync_settings['query_info'] ) ) {
			unset( $sync_settings['query_info'] );
		}
		$settings = new SyncSettings( $sync_settings );
		static::init()->push_to_queue(
			array(
				'ids'            => $news_ids,
				'sync_option_id' => $settings->get_option_id(),
			)
		);
	}

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function task( $item ) {
		$start_time     = microtime( true );
		$sync_option_id = $item['sync_option_id'] ?? '';
		if ( ! static::can_send_more_openai_request() ) {
			return $item;
		}
		if ( $this->is_item_running( $sync_option_id, 'find_interesting_news' ) ) {
			return false;
		}
		$this->set_item_running( $sync_option_id, 'find_interesting_news', ( MINUTE_IN_SECONDS * 15 ) );
		$sync_options = SyncSettings::get_setting( $sync_option_id );
		if ( empty( $sync_options ) ) {
			$sync_options = $item['sync_options'] ?? array();
		}
		$new_ids = isset( $item['ids'] ) && is_array( $item['ids'] ) ? array_map( 'intval', $item['ids'] ) : array();
		if ( count( $new_ids ) ) {
			$article_store = new ArticleStore();
			$articles      = $article_store->find_multiple(
				array(
					'id__in'   => $new_ids,
					'per_page' => count( $new_ids ),
				)
			);

			if ( count( $articles ) > 2 ) {
				/** @var InterestingNews|\WP_Error $response */
				$response = InterestingNews::generate_list_via_openai( $articles, $sync_options );
				if ( is_wp_error( $response ) ) {
					if ( 'Too Many Requests' === $response->get_error_message() ) {
						$this->handle_too_many_requests( $response );

						return $item;
					}

					return false;
				}

				$batch_id       = $response->get_id();
				$suggested_news = $response->get_suggested_news_ids();
			} else {
				$batch_id       = 0;
				$suggested_news = array_map( 'intval', wp_list_pluck( $articles, 'id' ) );
			}
			$total_suggested = count( $suggested_news );

			$pending_tasks = OpenAiReCreateNews::init()->get_pending_background_tasks();
			foreach ( $suggested_news as $id ) {
				$article = ArticleStore::find_by_id( $id );
				if ( ! $article instanceof Article ) {
					continue;
				}
				if ( ! in_array( $id, $pending_tasks, true ) ) {
					OpenAiReCreateNews::init()->push_to_queue(
						array(
							'news_id'     => $id,
							'created_via' => 'interesting-news',
							'batch_id'    => $batch_id,
							'created_at'  => current_time( 'mysql', true ),
						)
					);
				}
			}
			if ( $total_suggested ) {
				foreach ( $new_ids as $new_id ) {
					if ( in_array( $new_id, $suggested_news, true ) ) {
						continue;
					}
					$article_store->update(
						array(
							'id'           => $new_id,
							'openai_error' => 'Exclude by interesting news filtering',
						)
					);
				}
			}
		}

		$total_time = microtime( true ) - $start_time;

		// Logger::log( sprintf( 'Total time to complete is %s seconds for #%s.', $total_time, $sync_option_id ) );

		return false;
	}
}

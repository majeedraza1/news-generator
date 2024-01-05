<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use DateTimeZone;
use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\OpenAiRestClient;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Setting;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * BackgroundSync
 */
class OpenAiReCreateNews extends BackgroundProcessBase {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	public static $instance = null;

	/**
	 * Action
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'sync_openai_api';

	protected $admin_notice_heading = 'A background task is running to create {{total_items}} news with OpenAI api.';

	/**
	 * Add to sync
	 *
	 * @param  int  $news_id  News id.
	 *
	 * @return array
	 */
	public static function add_to_sync( int $news_id, bool $force = false ): array {
		if ( Setting::is_auto_sync_enabled() || $force ) {
			$pending_tasks = static::init()->get_pending_background_tasks();
			if ( ! in_array( $news_id, $pending_tasks, true ) ) {
				static::init()->push_to_queue(
					array(
						'news_id'    => $news_id,
						'created_at' => current_time( 'mysql', true ),
					)
				);
				$pending_tasks[] = $news_id;
			}

			return $pending_tasks;
		}

		return array();
	}

	/**
	 * Get pending background tasks
	 *
	 * @return array
	 */
	public function get_pending_background_tasks(): array {
		$items = $this->get_pending_items();

		$data = array();
		foreach ( $items as $value ) {
			$data[] = $value['news_id'];
		}

		if ( count( $data ) > 1 ) {
			return array_values( array_unique( $data ) );
		}

		return $data;
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


	protected function process_item_card_content_html( $task ): string {
		$html = '';
		$html .= 'News ID: <strong>' . ( $task['news_id'] ?? 0 ) . '</strong><br>';
		if ( isset( $task['created_at'] ) ) {
			$datetime   = date_create( $task['created_at'], new DateTimeZone( 'UTC' ) );
			$human_time = human_time_diff( $datetime->getTimestamp() );
			$html       .= 'Datetime: <strong>' . $human_time . ' ago</strong><br>';
		}
		$is_interesting = isset( $item['created_via'] ) && 'interesting-news' === $item['created_via'];
		if ( $is_interesting ) {
			$html .= 'Via News Filtering: <strong>Yes</strong>';
		}

		return $html;
	}

	/**
	 * Perform task
	 *
	 * @param  array  $item  Lists of data to process.
	 *
	 * @return array|false
	 */
	protected function task( $item ) {
		$news_id = isset( $item['news_id'] ) ? intval( $item['news_id'] ) : 0;
		if ( ! $this->can_send_more_openai_request() ) {
			return $item;
		}
		if ( $this->is_item_running( $news_id, 'title' ) ) {
			return false;
		}
		$this->set_item_running( $news_id, 'title' );

		$article = ArticleStore::find_by_id( $news_id );

		// Check if news id is valid.
		if ( ! $article instanceof Article ) {
			Logger::log( 'No article found for the id #' . $news_id );

			return false;
		}

		// Check if content length within approve limit.
		if ( ! OpenAiRestClient::is_valid_for_max_token( $article->title_and_body_words_count() ) ) {
			$error_message = sprintf(
				'It is going to exceed max token. Total words: %s; Approximate token: %s',
				$article->title_and_body_words_count(),
				$article->title_and_body_words_count() * 1.3
			);
			$article->update_field( 'openai_error', $error_message );

			return false;
		}

		// Check duplicate.
		$is_duplicate = ArticleStore::is_it_duplicate( $article->to_array() );
		if ( $is_duplicate ) {
			$article->update_field( 'openai_error', 'Duplicate news' );

			return false;
		}

		// Check if already news exists.
		if ( $article->get_openai_news_id() ) {
			OpenAiSyncNews::add_to_sync(
				array_merge(
					$item,
					array(
						'news_id' => $article->get_openai_news_id(),
					)
				)
			);

			return false;
		}

		$sync_settings = $article->get_sync_settings();
		if ( $sync_settings->rewrite_title_and_body() ) {
			$ai_news = NewsCompletion::article_to_news( $article );
			if ( is_wp_error( $ai_news ) ) {
				Logger::log( 'Fail to sync news from OpenAI. #' . $article->get_id() );
				Logger::log( $ai_news->get_error_message() );

				if ( 'exceeded_max_token' === $ai_news->get_error_code() ) {
					$article->update_field( 'openai_error', $ai_news->get_error_message() );

					return false;
				}

				if ( 'Too Many Requests' === $ai_news->get_error_message() ) {
					$this->handle_too_many_requests( $ai_news );

					// Push the item to bottom of queue to try later.
					static::init()->push_to_queue( $item );

					return false;
				}

				$attempt = static::get_article_fail_attempt( $article );
				if ( $attempt >= 2 ) {
					$article->update_field( 'openai_error', $ai_news->get_error_message() );

					Logger::log( '3 fail attempt to sync with OpenAI #' . $article->get_id() . '. Removing from sync list.' );

					return false;
				}

				static::increase_article_fail_attempt( $article );

				// Push the item to bottom of queue to try later.
				static::init()->push_to_queue( $item );

				return false;
			}
		} else {
			$article_data       = array(
				'source_id'        => $article->get_id(),
				'primary_category' => $article->get_primary_category_slug(),
				'sync_status'      => 'in-progress',
				'created_via'      => 'newsapi.ai',
				'sync_setting_id'  => $sync_settings->get_option_id(),
				'live_news'        => $sync_settings->is_live_news_enabled() ? 1 : 0,
				'title'            => $article->get_title(),
				'body'             => $article->get_body(),
			);
			$article_data['id'] = ( new NewsStore() )->create( $article_data );
			$ai_news            = new News( $article_data );
			$ai_news->set_id( $article_data['id'] );
			$ai_news->set_object_read( true );
		}

		if ( $ai_news instanceof News ) {
			OpenAiSyncNews::add_to_sync(
				array_merge(
					$item,
					array(
						'news_id' => $ai_news->get_id(),
					)
				)
			);

			return false;
		}
		Logger::log( 'Invalid response for the article id #' . $news_id );

		return false;
	}
}

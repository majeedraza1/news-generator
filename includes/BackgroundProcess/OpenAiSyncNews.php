<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use StackonetNewsGenerator\OpenAIApi\Models\InterestingNews;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Setting;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * SyncNewsWithOpenAi class
 */
class OpenAiSyncNews extends BackgroundProcessBase {

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
	protected $action = 'sync_openai_news';

	protected $admin_notice_heading = 'A background task is running to complete syncing for {{total_items}} news with OpenAI api.';

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

	/**
	 * Perform task
	 *
	 * @param  array  $item  Lists of data to process.
	 *
	 * @return array|false
	 */
	public function task( $item ) {
		$start_time = microtime( true );
		$news_id    = isset( $item['news_id'] ) ? intval( $item['news_id'] ) : 0;
		$field      = isset( $item['field'] ) ? sanitize_text_field( $item['field'] ) : '';

		if ( ! $this->can_send_more_openai_request() ) {
			return $item;
		}

		if ( $this->is_item_running( $news_id, $field ) ) {
			return false;
		}
		$this->set_item_running( $news_id, $field );

		$news = NewsStore::find_by_id( $news_id );
		if ( ! $news instanceof News ) {
			Logger::log( 'No news found for the id #' . $news_id );

			return false;
		}

		// Delete in case of duplicate creation.
		$article = ArticleStore::find_by_id( $news->get_source_id() );
		if ( $article instanceof Article ) {
			if ( $article->get_openai_news_id() !== $news->get_id() ) {
				NewsStore::delete_duplicate_news( $article->get_openai_news_id(), $article->get_id() );

				return false;
			}
		}

		if ( $news->is_sync_complete() ) {
			$this->do_on_sync_complete( $item, $news );

			return false;
		}

		if ( isset( $item['field'] ) ) {
			if ( 'body' !== $item['field'] && ( empty( $news->get_content() ) || empty( $news->get_title() ) ) ) {
				$body = NewsCompletion::generate_body( $news );
				if ( is_wp_error( $body ) ) {
					return false;
				}

				$news->set_prop( 'body', $body );
				$news->apply_changes();
			}
			$ai_news = NewsCompletion::generate_field_value( $news, $item['field'] );
		} else {
			$ai_news = NewsCompletion::news_completions( $news, $start_time, true );
		}
		if ( is_wp_error( $ai_news ) ) {
			$article = new Article( $news->get_source_news() );

			return $this->process_wp_error_response( $ai_news, $article, $news, $item );
		}

		$ai_news->recalculate_sync_status();

		if ( ! isset( $item['field'] ) && $ai_news->is_in_progress() ) {
			return $item;
		}

		if ( $ai_news->is_sync_complete() ) {
			$this->do_on_sync_complete( $item, $ai_news );
		}

		return false;
	}

	/**
	 * Add to sync
	 *
	 * @param  array  $data  Array of data to process.
	 *
	 * @return void
	 */
	public static function add_to_sync( array $data ) {
		$pending = static::init()->get_pending_background_tasks();
		if ( in_array( $data['news_id'], $pending, true ) ) {
			return;
		}
		if ( 'full_news' === Setting::news_sync_method() ) {
			static::init()->push_to_queue( $data );
		} else {
			$fields = array_keys( NewsCompletion::fields_to_actions() );
			foreach ( $fields as $field ) {
				if ( Setting::should_sync_field( $field ) ) {
					$data['field']      = $field;
					$data['created_at'] = current_time( 'mysql', true );
					static::init()->push_to_queue( $data );
				}
			}
		}
	}

	/**
	 * Get pending background tasks
	 *
	 * @return int[]
	 */
	public function get_pending_background_tasks(): array {
		$items = $this->get_pending_items();

		$data = [];
		foreach ( $items as $value ) {
			$data[] = $value['news_id'];
		}

		return array_values( array_unique( $data ) );
	}

	/**
	 * Do action when sync is complete.
	 *
	 * @param  array  $item  The data to sync.
	 * @param  News  $ai_news  The News object.
	 *
	 * @return void
	 */
	public function do_on_sync_complete( array $item, News $ai_news ): void {
		$is_interesting = isset( $item['created_via'] ) && 'interesting-news' === $item['created_via'];
		$batch_id       = isset( $item['batch_id'] ) ? intval( $item['batch_id'] ) : 0;
		if ( $is_interesting && $batch_id ) {
			$interesting_news = InterestingNews::find_single( $batch_id );
			if ( $interesting_news instanceof InterestingNews ) {
				$interesting_news->recalculate_openai_news_ids();
			}
		}

		// Update sync statistic.
		$statistic = ApiResponseLog::get_completion_time_and_requests_count( $ai_news->get_source_id() );
		$ai_news->update_fields(
			[
				'total_time'              => $statistic['total_time'],
				'total_request_to_openai' => $statistic['total_requests'],
			]
		);

		// Send news to sites.
		$ai_news->send_to_sites();
	}
}

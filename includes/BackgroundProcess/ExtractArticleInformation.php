<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\Client;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\Supports\Utils;

/**
 * ExtractArticleInformation class
 */
class ExtractArticleInformation extends BackgroundProcessBase {
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
	protected $action = 'extract_article_information';

	/**
	 * Admin notice heading
	 *
	 * @var string
	 */
	protected $admin_notice_heading = 'A background task is running to sync news content for {{total_items}}  news with News api.';


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
	 * Add to sync
	 *
	 * @param  int  $article_id  News id.
	 *
	 * @return void
	 */
	public static function add_to_sync( int $article_id ) {
		$pending_tasks = static::init()->get_pending_background_tasks();
		if ( ! in_array( $article_id, $pending_tasks, true ) ) {
			static::init()->push_to_queue( array( 'article_id' => $article_id ) );
		}
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
			$data[] = $value['article_id'];
		}

		if ( count( $data ) > 1 ) {
			return array_values( array_unique( $data ) );
		}

		return $data;
	}

	/**
	 * Perform task
	 *
	 * @param  array  $item  Lists of data to process.
	 *
	 * @return array|false
	 */
	protected function task( $item ) {
		$article_id = isset( $item['article_id'] ) ? intval( $item['article_id'] ) : 0;
		if ( ! $this->can_send_more_openai_request() ) {
			return $item;
		}
		if ( $this->is_item_running( $article_id, 'extract_article_information' ) ) {
			return $item;
		}
		$this->set_item_running( $article_id, 'extract_article_information' );

		$article = ArticleStore::find_by_id( $article_id );
		if ( ! $article instanceof Article ) {
			Logger::log(
				sprintf(
					'No article found for the id #%s; Field: %s',
					$article_id,
					'extract_article_information'
				)
			);

			return false;
		}

		$news_source_url = $article->get_news_source_url();
		if ( empty( $news_source_url ) ) {
			Logger::log(
				sprintf(
					'No source url found for the id #%s; Field: %s',
					$article_id,
					'extract_article_information'
				)
			);

			return false;
		}

		$details = Client::extract_article_information( $article->get_news_source_url() );
		if ( is_wp_error( $details ) ) {
			Logger::log(
				sprintf(
					'Api error for the id #%s; Field: %s; Error: %s',
					$article_id,
					'extract_article_information',
					$details->get_error_message()
				)
			);

			return false;
		}

		$sync_settings = $article->get_sync_settings();

		if ( is_string( $details['body'] ) && Utils::str_word_count_utf8( $details['body'] ) > 50 ) {
			$body = stripslashes( wp_filter_post_kses( $details['body'] ) );
			$article->update_field( 'body', $body );
			if ( $sync_settings->use_actual_news() && $article->get_openai_news_id() ) {
				( new NewsStore() )->update(
					array(
						'id'   => $article->get_openai_news_id(),
						'body' => $body,
					)
				);
			}
		}

		return false;
	}
}

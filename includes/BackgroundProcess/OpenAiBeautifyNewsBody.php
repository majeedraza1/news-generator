<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * OpenAiBeautifyNewsBody class
 */
class OpenAiBeautifyNewsBody extends BackgroundProcessBase {
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
	protected $action = 'beautify_news_body';

	/**
	 * Admin notice heading
	 *
	 * @var string
	 */
	protected $admin_notice_heading = 'A background task is running to beautify news body for {{total_items}} news with News api.';


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
	 * Is Article id in queue
	 *
	 * @param  int  $article_id  Article id.
	 *
	 * @return bool
	 */
	public static function is_in_queue( int $article_id ): bool {
		$pending_tasks = static::init()->get_pending_background_tasks();

		return in_array( $article_id, $pending_tasks, true );
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
		if ( $this->is_item_running( $article_id, 'beautify_news_body' ) ) {
			return $item;
		}
		$this->set_item_running( $article_id, 'beautify_news_body' );

		$article = ArticleStore::find_by_id( $article_id );
		if ( ! $article instanceof Article ) {
			Logger::log(
				sprintf(
					'No article found for the id #%s; Field: %s',
					$article_id,
					'beautify_news_body'
				)
			);

			return false;
		}

		$body = NewsCompletion::beautify_article( $article );
		if ( ! is_string( $body ) ) {
			return false;
		}

		$article->update_field( 'body', $body );
		$article->set_prop( 'body', $body );
		$article->apply_changes();

		$sync_settings = $article->get_sync_settings();
		if ( $sync_settings->use_actual_news() && $article->get_openai_news_id() ) {
			( new NewsStore() )->update(
				array(
					'id'   => $article->get_openai_news_id(),
					'body' => $body,
				)
			);
		}

		return false;
	}
}

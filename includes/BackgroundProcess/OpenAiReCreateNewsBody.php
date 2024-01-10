<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * OpenAiReCreateNewsBody class
 */
class OpenAiReCreateNewsBody extends BackgroundProcessBase {
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
	protected $action = 'openai_re_create_news_body';

	/**
	 * Admin notice heading
	 *
	 * @var string
	 */
	protected $admin_notice_heading = 'A background task is running to create news content for {{total_items}}  news with OpenAI api.';


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
	protected function task( $item ) {
		$news_id = isset( $item['news_id'] ) ? intval( $item['news_id'] ) : 0;
		if ( ! $this->can_send_more_openai_request() ) {
			return $item;
		}
		if ( $this->is_item_running( $news_id, 'news_body' ) ) {
			return $item;
		}
		$this->set_item_running( $news_id, 'news_body' );

		$news = NewsStore::find_by_id( $news_id );
		if ( ! $news instanceof News ) {
			Logger::log( sprintf( 'No news found for the id #%s; Field: %s', $news_id, 'body' ) );

			return false;
		}

		$body = NewsCompletion::generate_body( $news );
		if ( is_wp_error( $body ) ) {
			Logger::log( $body );

			return false;
		}

		$news->set_prop( 'body', $body );
		$news->apply_changes();
		if ( empty( $news->get_content() ) ) {
			Logger::log( 'Could not generate news content. News: ' . $news->get_id() );

			return $item;
		}

		OpenAiSyncNews::add_to_sync(
			array(
				'news_id' => $news->get_id(),
			)
		);

		return false;
	}

	/**
	 * Add to sync
	 *
	 * @param  int  $news_id  News id.
	 *
	 * @return void
	 */
	public static function add_to_sync( int $news_id ) {
		$pending = static::init()->get_pending_items();
		if ( count( $pending ) > 0 ) {
			$pending_news_ids = wp_list_pluck( $pending, 'news_id' );
			if ( in_array( $news_id, $pending_news_ids, true ) ) {
				return;
			}
		}

		static::init()->push_to_queue( array( 'news_id' => $news_id ) );
	}
}

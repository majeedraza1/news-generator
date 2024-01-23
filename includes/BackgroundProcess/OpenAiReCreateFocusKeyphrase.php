<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * OpenAiReCreateFocusKeyphrase class
 */
class OpenAiReCreateFocusKeyphrase extends BackgroundProcessBase {

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
	protected $action = 'openai_create_news_focus_keyphrase';

	/**
	 * Admin notice heading
	 *
	 * @var string
	 */
	protected $admin_notice_heading = 'A background task is running to create {{total_items}} news focus keyphrase with OpenAI api.';

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
	 * @param  int  $news_id  Article id.
	 */
	public static function add_to_sync( int $news_id ) {
		$pending_tasks = static::init()->get_pending_background_tasks();
		if ( ! in_array( $news_id, $pending_tasks, true ) ) {
			static::init()->push_to_queue(
				array(
					'news_id'    => $news_id,
					'created_at' => current_time( 'mysql', true ),
				)
			);
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
			$data[] = $value['news_id'];
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
		$news_id = isset( $item['news_id'] ) ? intval( $item['news_id'] ) : 0;
		if ( ! $this->can_send_more_openai_request() ) {
			return $item;
		}
		if ( $this->is_item_running( $news_id, 'focus_keyphrase' ) ) {
			return false;
		}
		$this->set_item_running( $news_id, 'focus_keyphrase' );

		$news = NewsStore::find_by_id( $news_id );
		if ( ! $news instanceof News ) {
			Logger::log( sprintf( 'No news found for the id #%s; Field: %s', $news_id, 'focus_keyphrase' ) );

			return false;
		}

		$body = NewsCompletion::generate_focus_keyphrase( $news );
		if ( is_wp_error( $body ) ) {
			Logger::log( $body );

			return false;
		}

		OpenAiReCreateNewsBody::add_to_sync( $news_id );

		return false;
	}
}

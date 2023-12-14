<?php

namespace TeraPixelNewsGenerator\BackgroundProcess;

use TeraPixelNewsGenerator\OpenAIApi\Client;
use TeraPixelNewsGenerator\OpenAIApi\Stores\NewsTagStore;

/**
 * ProcessNewsTag class
 */
class ProcessNewsTag extends BackgroundProcessBase {
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
	protected $action = 'sync_batch_openai_news_tags';

	protected $admin_notice_heading = 'A background task is running to generate {{total_items}} tags meta description.';

	/**
	 * Perform task
	 *
	 * @param  array $item  Lists of data to process.
	 *
	 * @return array|false
	 */
	protected function task( $item ) {
		$id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
		if ( ! Client::can_send_more_request() ) {
			return $item;
		}

		if ( $this->is_item_running( $id, 'generate_meta_description' ) ) {
			return false;
		}
		$this->set_item_running( $id, 'generate_meta_description' );
		$description = NewsTagStore::generate_meta_description(
			$id,
			array(
				'source_type' => $item['source_type'] ?? '',
				'source_id'   => $item['source_id'] ?? '',
			)
		);
		if ( ! empty( $description ) ) {
			SendTagToRemoteSites::init()->push_to_queue( array( 'id' => $id ) );
		}

		return false;
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
}

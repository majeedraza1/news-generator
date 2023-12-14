<?php

namespace TeraPixelNewsGenerator\BackgroundProcess;

use TeraPixelNewsGenerator\EventRegistryNewsApi\ArticleStore;

/**
 * Add to background task
 */
class OpenAiReCreateOldNews extends BackgroundProcessBase {

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
	protected $action = 'sync_batch_openai_api';

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'shutdown', [ self::$instance, 'dispatch_data' ] );
		}

		return self::$instance;
	}

	/**
	 * Save and run background on shutdown of all code
	 */
	public function dispatch_data() {
		if ( ! empty( $this->data ) ) {
			$this->save()->dispatch();
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function task( $item ) {
		$limit       = isset( $item['limit'] ) ? intval( $item['limit'] ) : 100;
		$items       = ArticleStore::get_unsync_items( $limit );
		$found_items = count( $items );
		if ( $found_items > 0 ) {
			foreach ( $items as $_item ) {
				OpenAiReCreateNews::add_to_sync( (int) $_item['id'] );
			}
		}
		if ( $found_items >= $limit ) {
			return $item;
		}

		return false;
	}
}

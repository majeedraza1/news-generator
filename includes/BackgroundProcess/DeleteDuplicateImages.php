<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use Stackonet\WP\Framework\BackgroundProcessing\BackgroundProcess;

/**
 * DeleteDuplicateImages
 */
class DeleteDuplicateImages extends BackgroundProcess {
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
	protected $action = 'delete_duplicate_image';

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
	 * @param mixed $item The value to be used to perform the task.
	 *
	 * @return false
	 */
	protected function task( $item ) {
		$image_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
		wp_delete_attachment( $image_id, true );

		return false;
	}

	/**
	 * Run the task
	 *
	 * @param bool $force If it should run instantly.
	 * @param int $limit Total number of record to proceed.
	 *
	 * @return array
	 */
	public static function run( bool $force = false, int $limit = 100 ) {
		$posts = NewsStore::get_duplicate_images( $limit );
		$ids   = [];
		if ( count( $posts ) ) {
			$instance = static::init();
			foreach ( $posts as $_post ) {
				$ids[] = $_post->ID;
				if ( $force ) {
					wp_delete_attachment( $_post->ID, true );
				} else {
					static::init()->push_to_queue( [ 'id' => $_post->ID ] );
				}
			}

			if ( false === $force ) {
				$instance->save()->dispatch();
			}
		}

		return $ids;
	}
}

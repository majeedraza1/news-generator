<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use StackonetNewsGenerator\Modules\Site\Site;
use StackonetNewsGenerator\Modules\Site\SiteStore;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsTagStore;

/**
 * SendTagToRemoteSites class
 */
class SendTagToRemoteSites extends BackgroundProcessWithUiHelper {
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
	protected $action = 'send_tag_to_remote_sites';

	protected $admin_notice_heading = 'A background task is running to send {{total_items}} tags to remote site.';

	/**
	 * Perform task
	 *
	 * @param  array  $item  Lists of data to process.
	 *
	 * @return false
	 */
	protected function task( $item ) {
		$id     = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
		$record = ( new NewsTagStore() )->find_single( $id );
		if ( $record ) {
			$sites = ( new SiteStore() )->find_multiple();
			foreach ( $sites as $site_data ) {
				$site = new Site( $site_data );
				$site->post_data( $record, 'update_news_tag' );
			}
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
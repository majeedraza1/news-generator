<?php

namespace TeraPixelNewsGenerator\Modules\Site;

use TeraPixelNewsGenerator\BackgroundProcess\BackgroundProcessBase;
use TeraPixelNewsGenerator\OpenAIApi\Stores\NewsTagStore;

class BackgroundSendTagsToSite extends BackgroundProcessBase {

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
	protected $action = 'sync_send_tags_to_site';
	protected $admin_notice_heading = "A background task is running to send {{total_items}} tags to remote sites.";

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
	 * @inheritDoc
	 */
	protected function task( $item ) {
		$site_id = isset( $item['site_id'] ) ? intval( $item['site_id'] ) : 0;
		$tag_id  = isset( $item['tag_id'] ) ? intval( $item['tag_id'] ) : 0;

		$site_data = ( new SiteStore() )->find_single( $site_id );
		$tag       = ( new NewsTagStore() )->find_single( $tag_id );
		if ( $site_data && $tag ) {
			$data = [
				'slug'             => $tag['slug'],
				'name'             => $tag['name'],
				'meta_description' => $tag['meta_description']
			];
			( new Site( $site_data ) )->post_data( $data, 'update_news_tag' );
		}

		return false;
	}
}

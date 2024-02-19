<?php

namespace StackonetNewsGenerator\Modules\Site;

use StackonetNewsGenerator\BackgroundProcess\BackgroundProcessBase;
use StackonetNewsGenerator\BackgroundProcess\ExtractArticleInformation;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\Supports\Utils;

/**
 * BackgroundSendNewsToSite class
 */
class BackgroundSendNewsToSite extends BackgroundProcessBase {
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
	protected $action = 'sync_send_news_to_site';

	protected $admin_notice_heading = 'A background task is running to send {{total_items}} news to remote sites.';

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

	public function save_and_dispatch() {
		if ( ! empty( $this->data ) ) {
			$this->save()->dispatch();
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function task( $item ) {
		$site_id = isset( $item['site_id'] ) ? intval( $item['site_id'] ) : 0;
		$news_id = isset( $item['news_id'] ) ? intval( $item['news_id'] ) : 0;

		$item_id = sprintf( '%s-%s', $site_id, $news_id );
		if ( $this->is_item_running( $item_id, 'send_news_to_site' ) ) {
			return false;
		}
		$this->set_item_running( $item_id, 'send_news_to_site', MINUTE_IN_SECONDS );

		// News is in queue, so push it in queue.
		if ( Utils::is_in_sync_queue( $news_id ) ) {
			static::init()->push_to_queue( $item );

			return false;
		}

		$site_data = ( new SiteStore() )->find_single( $site_id );
		$news      = NewsStore::find_by_id( $news_id );
		if ( ! $news instanceof News ) {
			return false;
		}

		// Article need to extract body wait.
		if ( ExtractArticleInformation::is_in_queue( $news->get_source_id() ) ) {
			// Push item to the end of current queue.
			static::init()->push_to_queue( $item );

			// Remove item from current queue.
			return false;
		}

		if ( $site_data ) {
			if ( ! $news->is_sync_complete() ) {
				return $item;
			}
			$news->get_source_news();
			$news->apply_changes();
			( new Site( $site_data ) )->post_news( $news );
		}

		return false;
	}

	/**
	 * Add to queue if it not exists already
	 *
	 * @param  int  $site_id  The site id.
	 * @param  int  $news_id  The news id.
	 *
	 * @return void
	 */
	public static function add_to_queue( int $site_id, int $news_id ) {
		$self   = static::init();
		$queue  = $self->get_pending_items();
		$exists = false;
		foreach ( $queue as $item ) {
			if ( $item['site_id'] === $site_id && $item['news_id'] === $news_id ) {
				$exists = true;
				break;
			}
		}
		if ( false === $exists ) {
			static::init()->push_to_queue(
				array(
					'site_id' => $site_id,
					'news_id' => $news_id,
				)
			);
		}
	}
}

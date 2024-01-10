<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettingsStore;

/**
 * Class BackgroundSync
 */
class SyncEventRegistryNews extends BackgroundProcessBase {

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
	protected $action = 'sync_event_registry_news';

	/**
	 * Admin notice heading
	 *
	 * @var string
	 */
	protected $admin_notice_heading = 'A background task is running to process {{total_items}} sync settings to sync news.';

	/**
	 * Sync settings
	 */
	public static function sync() {
		$batch_id      = wp_generate_uuid4();
		$sync_settings = SyncSettingsStore::get_settings_as_model();
		$instance      = static::init();
		foreach ( $sync_settings as $setting ) {
			$instance->push_to_queue(
				array(
					'batch_id'  => $batch_id,
					'option_id' => $setting->get_uuid(),
				)
			);
		}
		$instance->save()->dispatch();

		delete_transient( 'find_important_news_for_tweet_last_run' );
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

	/**
	 * Handle background task
	 *
	 * @inheritDoc
	 */
	protected function task( $item ) {
		$option_id = $item['option_id'] ?? '';
		if ( $this->is_item_running( $option_id ) ) {
			Logger::log( sprintf( 'Another background task is running to sync #%s', $option_id ) );

			return false;
		}
		$this->set_item_running( $option_id, 'event_registry_news' );
		$option = SyncSettingsStore::find_by_uuid( $option_id );
		if ( ! $option instanceof SyncSettingsStore ) {
			Logger::log( sprintf( 'Sync settings is not available for option id #%s', $option_id ) );

			return false;
		}
		$response = ArticleStore::sync_news( $option );
		if ( is_wp_error( $response ) ) {
			$log = array(
				'from'      => 'Event Registry News API',
				'option_id' => $option_id,
				'code'      => $response->get_error_code(),
				'message'   => $response->get_error_message(),
			);
			if ( 'http_request_failed' === $response->get_error_code() ) {
				$cache_key = 'http_request_failed_attempt_' . $option_id;
				$attempt   = (int) get_transient( $cache_key ) + 1;
				if ( $attempt >= 3 ) {
					$log['note'] = '3 fail attempt; Removing from list';
				} else {
					$log['note'] = sprintf( 'Sync failed; %s fail attempt; It will try again later', $attempt );
					$this->push_to_queue( $item );
				}
				set_transient( $cache_key, $attempt, ( 5 * MINUTE_IN_SECONDS ) );
			}
			Logger::log( $log );
		}

		return false;
	}
}

<?php

namespace StackonetNewsGenerator\Modules\TweetToArticle;

use StackonetNewsGenerator\BackgroundProcess\BackgroundProcessBase;
use StackonetNewsGenerator\Modules\TweetToArticle\Models\TwitterTweets;
use StackonetNewsGenerator\Modules\TweetToArticle\Models\TwitterUsername;
use StackonetNewsGenerator\Supports\TwitterApi;

/**
 * SyncTweets class
 */
class SyncTweets extends BackgroundProcessBase {
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
	protected $action = 'sync_tweets';

	/**
	 * Add username to batch
	 *
	 * @return void
	 */
	public static function sync() {
		$batch_id = wp_generate_uuid4();
		$items    = ( new TwitterUsername() )->get_options();
		foreach ( $items as $item ) {
			if ( Settings::is_username_batch_type() ) {
				$batch_id = wp_generate_uuid4();
			}
			static::init()->push_to_queue(
				[
					'batch_id' => $batch_id,
					'user'     => $item,
				]
			);
		}
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
		$batch_id = $item['batch_id'];
		$user     = $item['user'];
		$tweets   = TwitterApi::get_user_tweets( $user['username'] );
		if ( is_array( $tweets ) && isset( $tweets['data'] ) ) {
			TwitterTweets::batch_create_if_not_exists( $tweets['data'], $user['username'], $batch_id );
		}

		return false;
	}
}

<?php

namespace StackonetNewsGenerator\Modules\TweetToArticle;

use StackonetNewsGenerator\Modules\TweetToArticle\Api\AdminTwitterSettingsController;
use StackonetNewsGenerator\Modules\TweetToArticle\Api\AdminTwitterTweetsController;
use StackonetNewsGenerator\Modules\TweetToArticle\Api\AdminTwitterUsernameController;
use StackonetNewsGenerator\Modules\TweetToArticle\Models\TwitterTweets;

/**
 * TweetToArticleManager
 */
class TweetToArticleManager {
	/**
	 * The instance of the class
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( static::$instance ) ) {
			self::$instance = new self();

			add_action( 'admin_init', [ TwitterTweets::class, 'create_table' ] );

			if ( ! is_admin() ) {
				AdminTwitterUsernameController::init();
				AdminTwitterTweetsController::init();
				AdminTwitterSettingsController::init();
			}
		}

		return self::$instance;
	}
}

<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;

/**
 * NewsCrawlerManager class
 */
class NewsCrawlerManager {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Only one instance of the class can be loaded.
	 *
	 * @return NewsCrawlerManager
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'wp_ajax_crawl_article_content', array( self::$instance, 'crawl_article_content' ) );

			AdminNewsCrawlerLogController::init();
		}

		return self::$instance;
	}

	/**
	 * Crawl article content
	 *
	 * @return void
	 */
	public function crawl_article_content() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'Sorry. This link only for developer to do some testing.',
					'stackonet-news-generator'
				)
			);
		}

		$article_id = isset( $_REQUEST['article_id'] ) ? intval( $_REQUEST['article_id'] ) : 0;
		$article    = ArticleStore::find_by_id( $article_id );
		if ( ! $article instanceof Article ) {
			wp_die(
				esc_html__(
					'No article found for article_id',
					'stackonet-news-generator'
				)
			);
		}

		$news = NewsParser::parse_news_from_article( $article );
		var_dump( $news );
		wp_die();
	}
}

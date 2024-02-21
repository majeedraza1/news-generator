<?php

namespace StackonetNewsGenerator\Modules\NaverDotComNews;

use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\Client;
use StackonetNewsGenerator\Modules\NewsCrawler\NewsParser;

/**
 * NaverDotComNewsManager class
 */
class NaverDotComNewsManager {
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

			add_action( 'wp_ajax_stackonet_news_crawl', array( self::$instance, 'news_crawl' ) );
		}

		return self::$instance;
	}

	/**
	 * Doing some news crawl test
	 *
	 * @return void
	 */
	public function news_crawl() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'Sorry. This link only for developer to do some testing.',
					'stackonet-news-generator'
				)
			);
		}

		$url = 'https://www.stardailynews.co.kr/news/articleView.html?idxno=436755';
		$url = 'http://www.newsis.com/view/?id=NISX20240221_0002633905&cID=10810&pID=10800';

		$article = ArticleStore::find_by_id( 1481 );
		$news    = NewsParser::parse_news_from_article( $article );

//		$settings     = SyncSettings::find_single( 20 );
//		$api_response = NaverApiClient::search_news( $settings->get_keyword() );
		$api_response = Client::extract_article_information( $url );
		$api_response = '';
		var_dump( $news->get_image_url() );
		wp_die();
	}
}

<?php

namespace StackonetNewsGenerator\Modules\NaverDotComNews;

use Symfony\Component\DomCrawler\Crawler;

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

		$api  = NaverApiClient::search_news( '무신사' );
		$data = [];
		foreach ( $api['items'] as $item ) {
			$data[] = NaverApiClient::format_api_data_for_database( $item );
		}
		var_dump( $data );
		wp_die();
	}
}

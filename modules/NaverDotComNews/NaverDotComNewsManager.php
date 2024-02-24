<?php

namespace StackonetNewsGenerator\Modules\NaverDotComNews;

use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\Client;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettings;
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

			add_action( 'wp_ajax_naver_api_response', array( self::$instance, 'naver_api_response' ) );
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

		// $settings     = SyncSettings::find_single( 20 );
		// $api_response = NaverApiClient::search_news( $settings->get_keyword() );
		$api_response = Client::extract_article_information( $url );
		$api_response = '';
		var_dump( $news->get_image_url() );
		wp_die();
	}

	/**
	 * Debug api response
	 *
	 * @return void
	 */
	public function naver_api_response() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'Sorry. This link only for developer to do some testing.',
					'stackonet-news-generator'
				)
			);
		}

		if ( wp_verify_nonce( $_REQUEST['token'] ?? '', 'naver_api_response' ) ) {
			$id           = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;
			$setting      = SyncSettings::find_single( $id );
			$api_response = NaverApiClient::search_news( $setting->get_keyword() );
			$location     = $setting->get_keyword_location();
			$total_found  = 0;

			$item_html = '<div class="items">';
			if ( is_array( $api_response ) && isset( $api_response['items'] ) ) {
				foreach ( $api_response['items'] as $item ) {
					$title       = static::esc_html( $item['title'] );
					$description = static::esc_html( $item['description'] );

					$selected = false;
					if ( 'title-or-body' === $location ) {
						$selected = true;
					} elseif ( 'title' === $location && false !== mb_strpos( $title, $setting->get_keyword() ) ) {
						$selected = true;
					} elseif ( 'body' === $location && false !== mb_strpos( $description, $setting->get_keyword() ) ) {
						$selected = true;
					} elseif (
						'title-and-body' === $location &&
						false !== mb_strpos( $title, $setting->get_keyword() ) &&
						false !== mb_strpos( $description, $setting->get_keyword() )
					) {
						$selected = true;
					}

					$item_classes = array( 'item' );
					if ( $selected ) {
						$item_classes[] = 'is-selected';
						++ $total_found;
					}

					$item_html .= '<div class="' . esc_attr( join( ' ', $item_classes ) ) . '">';
					$item_html .= '<div><div class="item-label">Title: </div><div class="item-content">' . $title . '</div></div>';
					$item_html .= '<div><div class="item-label">originallink: </div><div class="item-content">' . esc_html( $item['originallink'] ) . '</div></div>';
					$item_html .= '<div><div class="item-label">link: </div><div class="item-content">' . esc_html( $item['link'] ) . '</div></div>';
					$item_html .= '<div><div class="item-label">description: </div><div class="item-content">' . $description . '</div></div>';
					$item_html .= '<div><div class="item-label">pubDate: </div><div class="item-content">' . esc_html( $item['pubDate'] ) . '</div></div>';
					$item_html .= '</div>';
				}
			}
			$item_html .= '</div>';

			$html = '<div>';

			$html .= '<div class="heading">';
			$html .= '<h1>Naver.com result for Keyword: ' . $setting->get_keyword() . '</h1>';
			$html .= '<div>Keyword Location: ' . $location . '</div>';
			$html .= '<div>Total Match: ' . $total_found . '</div>';
			$html .= '</div>';

			$html .= $item_html;

			$html .= '</div>';

			ob_start();
			?>
            <style type="text/css">
                .item {
                    margin-bottom: 1rem;
                    margin-top: 1rem;
                    border: 1px solid rgba(0, 0, 0, .12);
                    padding: 0.5rem;
                    border-radius: 4px;
                }

                .item.is-selected {
                    border-color: green;
                }

                .item:not(.is-selected) {
                    opacity: .38;
                }

                .item > div {
                    display: flex;
                }

                .item > div:not(:last-child) {
                    border-bottom: 1px dashed rgba(0, 0, 0, .12);
                    margin-bottom: 0.5rem;
                    padding-bottom: 0.5rem;
                }

                .item-label {
                    min-width: 96px;
                    font-weight: bold;
                }

                .item-content b {
                    color: #ff0000;
                    font-weight: bold;
                }
            </style>
			<?php
			$html .= ob_get_clean();

			_default_wp_die_handler( $html, esc_html( $setting->get_keyword() ) );
		}

		wp_die();
	}

	public static function esc_html( $data ) {
		return wp_kses( stripslashes( $data ), 'post' );
	}
}

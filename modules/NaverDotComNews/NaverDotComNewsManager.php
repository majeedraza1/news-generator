<?php

namespace StackonetNewsGenerator\Modules\NaverDotComNews;

use StackonetNewsGenerator\EventRegistryNewsApi\Article;
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
					$article_data = NaverApiClient::format_api_data_for_database( $item, $setting );
					$title        = static::esc_html( $item['title'] );
					$description  = $article_data['body'];

					$article    = ArticleStore::find_by_slug_or_uri( $article_data['slug'] );
					$article_id = $article instanceof Article ? $article->get_id() : 0;

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

					if ( $article instanceof Article && $article->get_openai_news_id() ) {
						$item_classes[] = 'has-openai-news';
					}

					$link = '<span>' . esc_html( $item['originallink'] ) . '</span>';
					$link .= '<a class="item-content-link" href="' . esc_url( $item['originallink'] ) . '" target="_blank">';
					$link .= '<svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">';
					$link .= '<path d="M440-280H280q-83 0-141.5-58.5T80-480q0-83 58.5-141.5T280-680h160v80H280q-50 0-85 35t-35 85q0 50 35 85t85 35h160v80ZM320-440v-80h320v80H320Zm200 160v-80h160q50 0 85-35t35-85q0-50-35-85t-85-35H520v-80h160q83 0 141.5 58.5T880-480q0 83-58.5 141.5T680-280H520Z"/>';
					$link .= '</svg>';
					$link .= '<span>Open Link</span>';
					$link .= '</a>';

					$item_html .= '<div class="' . esc_attr( join( ' ', $item_classes ) ) . '">';
					$item_html .= '<div><div class="item-label">Title: </div><div class="item-content">' . $title . '</div></div>';
					$item_html .= '<div><div class="item-label">Original Link: </div><div class="item-content">' . $link . '</div></div>';
					$item_html .= '<div><div class="item-label">link: </div><div class="item-content">' . esc_html( $item['link'] ) . '</div></div>';
					$item_html .= '<div><div class="item-label">Description: </div><div class="item-content">' . $description . '</div></div>';
					$item_html .= '<div><div class="item-label">Publish Date: </div><div class="item-content">' . esc_html( $item['pubDate'] ) . '</div></div>';
					$item_html .= '<div>';
					$item_html .= '<div class="item-label">Article ID: </div>';
					$item_html .= '<div class="item-content">';

					if ( $article instanceof Article ) {
						$crawl_url   = add_query_arg(
							array(
								'action'     => 'crawl_article_content',
								'article_id' => $article_id,
							),
							admin_url( 'admin-ajax.php' )
						);
						$extract_url = add_query_arg(
							array(
								'action'     => 'debug_extract_article_info',
								'article_id' => $article_id,
								'update'     => 1,
							),
							admin_url( 'admin-ajax.php' )
						);

						$item_html .= esc_html( $article_id );
						$item_html .= '<div>';
						$item_html .= '<a class="item-content-link" href="' . $crawl_url . '" target="_blank">Crawl Body</a>';
						$item_html .= '<a class="item-content-link" href="' . $extract_url . '" target="_blank">Newsapi extract article</a>';
						$item_html .= '</div>';
						$item_html .= '<details>';
						$item_html .= '<summary>Details</summary>';
						$item_html .= $article->get_body();
						$item_html .= '</details>';
						if ( $article->get_openai_news_id() ) {
							$item_html .= '<span class="has-openai-news-icon"><svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 -960 960 960" width="18"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg></span>';
						}
					} else {
						$item_html .= 0;
					}

					$item_html .= '</div>'; // .item-content
					$item_html .= '</div>';

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
                .flex {
                    display: flex
                }

                .item {
                    margin-bottom: 1rem;
                    margin-top: 1rem;
                    border: 1px solid rgba(0, 0, 0, .12);
                    padding: 0.5rem;
                    border-radius: 4px;
                    position: relative;
                }

                .item.is-selected {
                    border-color: green;
                }

                .item.has-openai-news {
                    border-left-width: 1.25rem;
                }

                .has-openai-news-icon {
                    position: absolute;
                    top: .5rem;
                    left: -1.125rem
                }

                .has-openai-news-icon svg {
                    fill: white;
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

                .item-content-link,
                .button {
                    height: 24px;
                    display: inline-flex;
                    margin-left: 0.5rem;
                    vertical-align: middle;
                    justify-content: center;
                    align-items: center;
                    border: 1px solid rgba(0, 0, 0, .12);
                    border-radius: 4px;
                    padding: 0.125rem 0.5rem;
                    line-height: 1;
                    text-decoration: none;
                }

                .item-content-link svg,
                .button svg {
                    fill: currentColor;
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

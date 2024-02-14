<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

use Symfony\Component\DomCrawler\Crawler;

/**
 * NewsParser
 */
class NewsParser {
	/**
	 * Get content from URL
	 *
	 * @param  string  $url  The URL to be parsed.
	 *
	 * @return string
	 */
	public static function get_content( string $url ): string {
		$cache_key = sprintf( 'get_url_content_%s', md5( $url ) );
		$body      = get_transient( $cache_key );
		if ( false === $body ) {
			$response = wp_remote_get( $url );
			$body     = wp_remote_retrieve_body( $response );

			set_transient( $cache_key, $body, HOUR_IN_SECONDS );
		}

		return $body;
	}

	public static function parse_url( string $url ) {
		$html    = static::get_content( $url );
		$crawler = new Crawler( $html );
		$crawler = $crawler->filter( 'body' );

		$settings = static::get_site_setting( $url );
		if ( $settings instanceof SiteSetting ) {
			$article = $crawler->filter( $settings->get_body_selector() );

			return trim( $article->text( null, false ) );
		}

//		$article = $crawler->filter( '#article_main' );
//
//		return trim( $article->text( null, false ) );
	}

	public static function get_site_setting( string $url ) {
		$host = wp_parse_url( $url );
		if ( isset( $host['host'] ) ) {
			$url = str_replace( 'www.', '', $host['host'] );
		}
		$sites = static::get_sites_list();

		if ( isset( $sites[ $url ] ) ) {
			return new SiteSetting( $sites[ $url ] );
		}

		return false;
	}

	/**
	 * Get site list
	 */
	public static function get_sites_list(): array {
		return array(
			'thebell.co.kr' => array(
				'bodySelector' => '#article_main',
			),
		);
	}
}

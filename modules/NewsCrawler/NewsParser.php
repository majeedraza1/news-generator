<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use Symfony\Component\DomCrawler\Crawler;

/**
 * NewsParser
 */
class NewsParser {
	/**
	 * Get remote content
	 *
	 * @param  string  $url  The URL content.
	 *
	 * @return string
	 */
	protected static function _get_remote_content( string $url ) {
		$response = wp_remote_get( $url );
		$body     = wp_remote_retrieve_body( $response );

		return $body;
	}

	/**
	 * Get content from URL
	 *
	 * @param  string  $url  The URL to be parsed.
	 * @param  bool  $force  If it should read content force fully.
	 *
	 * @return string
	 */
	public static function get_remote_content( string $url, bool $force = false ): string {
		$cache_key = sprintf( 'get_url_content_%s', md5( $url ) );
		$body      = get_transient( $cache_key );
		if ( false === $body || true === $force ) {
			$body = static::_get_remote_content( $url );

			if ( ! empty( $body ) ) {
				set_transient( $cache_key, $body, HOUR_IN_SECONDS );
			}
		}

		return $body;
	}

	/**
	 * Parse news from URL
	 *
	 * @param  string  $url
	 * @param  bool  $force
	 *
	 * @return News|\WP_Error
	 */
	public static function parse_news_from_url( string $url, bool $force = false ) {
		$html = static::get_remote_content( $url, $force );
		if ( empty( $html ) ) {
			return new \WP_Error(
				'no_content',
				sprintf( 'No content is recovered from that url: %s', $url )
			);
		}
		$news = new News( new Crawler( $html ), $url );

		$settings = static::get_site_setting( $url );
		if ( $settings instanceof SiteSetting ) {
			$news->set_site_setting( $settings );
		}

		NewsCrawlerLog::first_or_create( $news );

		return $news;
	}

	public static function parse_news_from_article( Article $article, bool $force = false ) {
		$url  = $article->get_news_source_url();
		$html = static::get_remote_content( $article->get_news_source_url(), $force );
		if ( empty( $html ) ) {
			return new \WP_Error(
				'no_content',
				sprintf( 'No content is recovered from that url: %s', $url )
			);
		}
		$news = new News( new Crawler( $html ), $url );

		$settings = static::get_site_setting( $url );
		if ( $settings instanceof SiteSetting ) {
			$news->set_site_setting( $settings );
		}

		$body = $news->get_article();
		if ( empty( $body ) ) {
			$article->update_field( 'body', $body );
		}

		NewsCrawlerLog::first_or_create( $news, $article );

		return $news;
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
			'thebell.co.kr'    => array(
				'titleSelector' => '.viewHead > .tit',
				'bodySelector'  => '#article_main',
			),
			'fashionbiz.co.kr' => array(
				'titleSelector' => '.content .tit03',
				'bodySelector'  => '.content .view_cont',
			),
		);
	}
}

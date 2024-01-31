<?php

namespace StackonetNewsGenerator\Modules\NaverDotComNews;

use Stackonet\WP\Framework\Supports\RestClient;
use StackonetNewsGenerator\BackgroundProcess\OpenAiFindInterestingNews;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsTitle;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\ClientResponseLog;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettingsStore;
use StackonetNewsGenerator\Supports\Utils;
use WP_Error;

/**
 * SerpApi class
 */
class NaverApiClient extends RestClient {
	const OPTION_NAME = 'NAVER_API_SETTINGS';

	/**
	 * Get defaults
	 *
	 * @return string[]
	 */
	public static function defaults(): array {
		return array(
			// 'api_key' => 'df5e09b42c6bff5fd5622fb00d3313bdec90fe11dcd68c001aec6062ae21cdd2',
			'client_id'     => 'b6n3CU61_0B9TP36mb2u',
			'client_secret' => 'Ndu4ARJSd6',
		);
	}

	/**
	 * If the setting is defined from config file
	 *
	 * @return bool
	 */
	public static function is_in_config_file(): bool {
		return defined( self::OPTION_NAME );
	}

	/**
	 * Get settings
	 *
	 * @return array The settings.
	 */
	public static function get_settings(): array {
		$default = static::defaults();
		if ( static::is_in_config_file() ) {
			$settings = constant( self::OPTION_NAME );
			if ( is_string( $settings ) ) {
				$settings = json_decode( $settings, true );
			}
		} else {
			$settings = get_option( self::OPTION_NAME );
		}

		if ( is_array( $settings ) ) {
			return array_merge( $default, $settings );
		}

		return $default;
	}

	/**
	 * Get setting
	 *
	 * @param  string  $key  The setting key.
	 * @param  mixed  $default  The default value.
	 *
	 * @return false|mixed
	 */
	public static function get_setting( string $key, $default = false ) {
		$settings = static::get_settings();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Get api key
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		return (string) static::get_setting( 'api_key' );
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->add_headers( 'X-Naver-Client-Id', static::get_setting( 'client_id' ) );
		$this->add_headers( 'X-Naver-Client-Secret', static::get_setting( 'client_secret' ) );
		// $this->set_global_parameter( 'engine', 'naver' );
		// $this->set_global_parameter( 'where', 'news' );
		parent::__construct( 'https://openapi.naver.com/v1/search' );
	}

	/**
	 * Search news
	 *
	 * @param  string  $query  Query string.
	 *
	 * @return array|WP_Error
	 */
	public static function search_news( string $query ) {
		$arguments = array(
			'query'   => rawurlencode( $query ),
			'display' => 100,
			'start'   => 1,
			'sort'    => 'date',
		);
		$cache_key = sprintf( 'serp_api_search_%s', md5( wp_json_encode( $arguments ) ) );
		$results   = get_transient( $cache_key );
		if ( false === $results ) {
			$results = ( new static() )->get( 'news.json', $arguments );
			if ( ! is_wp_error( $results ) ) {
				set_transient( $cache_key, $results, HOUR_IN_SECONDS );
			}
		}

		return $results;
	}

	/**
	 * Sanitize item for database
	 *
	 * @param  array  $item  Raw data.
	 *
	 * @return array
	 */
	public static function format_api_data_for_database( array $item, ?SyncSettingsStore $settings = null ): array {
		$title         = sanitize_text_field( $item['title'] ?? '' );
		$slug          = sanitize_title_with_dashes( $title, '', 'save' );
		$slug          = mb_substr( $slug, 0, 250 );
		$news_datetime = gmdate( 'Y-m-d H:i:s', strtotime( $item['pubDate'] ) );

		$data = array(
			'title'             => $title,
			'slug'              => $slug,
			'body'              => $item['description'] ?? '',
			'news_source_url'   => esc_url( $item['originallink'] ?? '' ),
			'news_datetime'     => $news_datetime,
			'title_words_count' => Utils::str_word_count_utf8( $item['title'] ),
			'body_words_count'  => Utils::str_word_count_utf8( $item['description'] ),
		);
		if ( $settings instanceof SyncSettingsStore ) {
			$data['primary_category'] = $settings->get_primary_category();
			$data['news_filtering']   = $settings->is_news_filtering_enabled() ? 1 : 0;
			$data['sync_settings']    = array( 'option_id' => $settings->get_option_id() );
		}

		return $data;
	}

	/**
	 * Sync news
	 *
	 * @param  array  $settings  Api settings.
	 * @param  bool  $force  Load from api.
	 *
	 * @return array|WP_Error
	 */
	public static function sync_news( SyncSettingsStore $settings, bool $force = true ) {
		if ( ! $settings->has_keyword() ) {
			return new WP_Error( 'no_keyword', 'Sync settings has no keyword.' );
		}
		$api_response = static::search_news( $settings->get_keyword() );
		if ( is_wp_error( $api_response ) ) {
			return $api_response;
		}
		$total_pages            = ceil( $api_response['total'] / $api_response['display'] );
		$store                  = new ArticleStore();
		$existing_news_ids      = array();
		$new_ids                = array();
		$articles               = array();
		$total_omitted_articles = 0;
		foreach ( $api_response['items'] as $item ) {
			$article_data  = NaverApiClient::format_api_data_for_database( $item );
			$existing_news = ArticleStore::find_by_slug_or_uri( $article_data['slug'] );
			if ( $existing_news ) {
				$article_id          = $existing_news['id'] ?? 0;
				$existing_news_ids[] = $article_id;
				$articles[]          = array_merge(
					$item,
					array(
						'id'   => $article_id,
						'type' => 'existing',
					)
				);
				continue;
			}

			$id = $store->create( $item );
			if ( $id ) {
				$new_ids[]  = $id;
				$articles[] = array_merge(
					$item,
					array(
						'id'   => $id,
						'type' => 'new',
					)
				);
			}
		}

		if ( $settings->is_news_filtering_enabled() ) {
			if ( count( $new_ids ) ) {
				OpenAiFindInterestingNews::add_to_sync( $new_ids, $settings );
			}
		} elseif ( $settings->is_live_news_enabled() ) {
			foreach ( $new_ids as $id ) {
				OpenAiReCreateNewsTitle::add_to_sync( $id );
			}
		} elseif ( $settings->use_actual_news() ) {
			foreach ( $new_ids as $id ) {
				$article = ArticleStore::find_by_id( $id );
				if ( $article instanceof Article ) {
					$article->copy_to_news();
				}
			}
		} else {
			foreach ( $new_ids as $id ) {
				OpenAiReCreateNewsTitle::add_to_sync( $id );
			}
		}

		ClientResponseLog::add_log(
			array(
				'sync_setting_id'      => $settings->get_option_id(),
				'news_articles'        => $articles,
				'existing_records_ids' => $existing_news_ids,
				'new_records_ids'      => $new_ids,
				'total_pages'          => $total_pages,
			)
		);

		$settings->set_total_found_items( count( $articles ) );
		$settings->set_total_existing_items( count( $existing_news_ids ) );
		$settings->set_total_new_items( count( $new_ids ) );
		$settings->set_total_omitted_items( $total_omitted_articles );
		$settings->update();

		return array(
			'existing_records_ids' => $existing_news_ids,
			'new_records_ids'      => $new_ids,
			'total_pages'          => $total_pages,
		);
	}
}

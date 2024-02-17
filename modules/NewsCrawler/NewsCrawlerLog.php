<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

use DateTime;
use Exception;
use Stackonet\WP\Framework\Abstracts\DatabaseModel;
use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\Supports\Utils;

/**
 * NewsCrawlerLog class
 */
class NewsCrawlerLog extends DatabaseModel {
	/**
	 * Table name
	 *
	 * @var string
	 */
	protected $table = 'news_crawler_log';

	public static function find_failed() {

	}

	/**
	 * Find by article id
	 *
	 * @param  int  $article_id
	 *
	 * @return false|static
	 */
	public static function find_by_article_id( int $article_id ) {
		$item = static::get_query_builder()->where( 'article_id', $article_id )->first();
		if ( $item ) {
			return new static( $item );
		}

		return false;
	}

	/**
	 * Find by source url
	 *
	 * @param  string  $source_url  News source url.
	 *
	 * @return false|static
	 */
	public static function find_by_source_url( string $source_url ) {
		$item = static::get_query_builder()->where( 'source_url', $source_url )->first();
		if ( $item ) {
			return new static( $item );
		}

		return false;
	}

	/**
	 * Find a news in log or create if not exists
	 *
	 * @param  News  $news  News Object.
	 * @param  Article|null  $article  The Article object.
	 *
	 * @return static|false
	 */
	public static function first_or_create( News $news, ?Article $article = null ) {
		$item = static::find_by_source_url( $news->get_source_url() );
		if ( $item instanceof static ) {
			return $item;
		}

		$date_published = '';
		$date_modified  = '';
		if ( $news->has_news_article_schema() ) {
			try {
				$published_datetime = new DateTime( $news->get_news_article_schema()->get_published_datetime() );
				$published_datetime->setTimezone( new \DateTimeZone( 'UTC' ) );
				$date_published    = $published_datetime->format( 'Y-m-d H:i:s' );
				$modified_datetime = new DateTime( $news->get_news_article_schema()->get_modified_datetime() );
				$modified_datetime->setTimezone( new \DateTimeZone( 'UTC' ) );
				$date_modified = $modified_datetime->format( 'Y-m-d H:i:s' );
			} catch ( Exception $e ) {
				Logger::log( $e );
			}
		}

		$data = array(
			'site_url'              => $news->get_site_url(),
			'source_url'            => $news->get_source_url(),
			'title'                 => $news->get_heading(),
			'summery'               => $news->get_summery(),
			'body'                  => $news->get_article(),
			'opengraph_title'       => $news->get_opengraph_title(),
			'opengraph_description' => $news->get_opengraph_description(),
			'opengraph_image'       => $news->get_opengraph_image(),
			'keywords'              => $news->get_search_keywords(),
			'has_schema_markup'     => $news->has_news_article_schema() ? 1 : 0,
			'date_published'        => $date_published,
			'date_modified'         => $date_modified,
		);

		if ( $article instanceof Article ) {
			$data['article_id']     = $article->get_id();
			$data['openai_news_id'] = $article->get_openai_news_id();
		}

		if ( Utils::str_word_count_utf8( $news->get_article() ) > 100 ) {
			$data['status'] = 'success';
		} else {
			$data['status'] = 'fail';
		}

		$id = static::create( $data );
		if ( $id ) {
			return static::find_single( $id );
		}

		global $wpdb;
		Logger::log( $wpdb->last_error );

		return false;
	}

	/**
	 * Create table
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		$self    = new static();
		$table   = $self->get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				`article_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				`openai_news_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                `source_url` varchar(200) NULL DEFAULT NULL COMMENT 'News unique id. Can be used to get details.',
                `site_url` varchar(100) NULL DEFAULT NULL COMMENT 'News unique id. Can be used to get details.',
                `title` text NULL DEFAULT NULL COMMENT 'Headline of the article',
                `summery` text NULL DEFAULT NULL COMMENT 'Summery of the article',
                `body` longtext NULL DEFAULT NULL COMMENT 'Article body',
                `opengraph_title` text NULL DEFAULT NULL COMMENT 'Headline of the article for facebook',
                `opengraph_description` longtext NULL DEFAULT NULL COMMENT 'Body of the article for facebook',
                `opengraph_image` text NULL DEFAULT NULL COMMENT 'direct URL to the article thumbnail image',
                `keywords` text NULL DEFAULT NULL COMMENT 'Keywords for better SEO',
				`date_published` datetime NULL DEFAULT NULL,
				`date_modified` datetime NULL DEFAULT NULL,
				`has_schema_markup` TINYINT NOT NULL DEFAULT 0,
    			`status` varchar(20) NULL DEFAULT NULL COMMENT 'success or fail',
    			`created_at` DATETIME NULL DEFAULT NULL,
    			`updated_at` DATETIME NULL DEFAULT NULL,
				PRIMARY KEY (id),
    			UNIQUE `source_url` (`source_url`)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
	}
}

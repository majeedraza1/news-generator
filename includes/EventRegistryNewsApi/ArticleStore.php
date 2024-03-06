<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

use DateTime;
use DateTimeZone;
use Stackonet\WP\Framework\Abstracts\DataStoreBase;
use StackonetNewsGenerator\BackgroundProcess\OpenAiFindInterestingNews;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsTitle;
use StackonetNewsGenerator\EventRegistryNewsApi\Setting as EventRegistryNewsApiSettings;
use StackonetNewsGenerator\Modules\NaverDotComNews\NaverApiClient;
use StackonetNewsGenerator\Supports\Utils;
use WP_Error;

/**
 * ArticleStore class
 */
class ArticleStore extends DataStoreBase {
	protected $table = 'event_registry_news';

	/**
	 * Get news by source id
	 *
	 * @param  int  $news_id  Source news id.
	 *
	 * @return false|Article
	 */
	public static function find_by_id( int $news_id ) {
		global $wpdb;
		$table  = ( new self() )->get_table_name();
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $news_id ),
			ARRAY_A
		);
		if ( $result ) {
			return new Article( $result );
		}

		return false;
	}

	public static function get_unique_primary_categories( array $ids = array() ): array {
		global $wpdb;
		$self  = new self();
		$table = $self->get_table_name();
		$sql   = "SELECT DISTINCT(primary_category) FROM $table";
		if ( count( $ids ) ) {
			$sql .= ' WHERE id IN(' . implode( ',', $ids ) . ')';
		}
		$results            = $wpdb->get_results( $sql, ARRAY_A );
		$primary_categories = wp_list_pluck( $results, 'primary_category' );

		$sql = "SELECT primary_category, COUNT(*) AS total_records FROM {$table}";
		if ( count( $ids ) ) {
			$sql .= ' WHERE id IN(' . implode( ',', $ids ) . ')';
		}
		$sql           .= ' GROUP BY primary_category';
		$count_results = $wpdb->get_results( $sql, ARRAY_A );
		$counts        = array();
		foreach ( $count_results as $result ) {
			$counts[ $result['primary_category'] ] = intval( $result['total_records'] );
		}

		$all_cats   = Category::get_categories();
		$categories = array();
		foreach ( $primary_categories as $cat_slug ) {
			if ( empty( $cat_slug ) ) {
				continue;
			}
			$name         = ! empty( $all_cats[ $cat_slug ] ) ? $all_cats[ $cat_slug ] : $cat_slug;
			$count        = $counts[ $cat_slug ] ?? 0;
			$categories[] = array(
				'name'  => ! empty( $name ) ? $name : 'undefined',
				'slug'  => $cat_slug,
				'count' => $count,
				'label' => sprintf( '%s (%s)', ( ! empty( $name ) ? $name : 'undefined' ), $count ),
			);
		}

		return $categories;
	}

	public static function find_by_date( string $date ): array {
		list( $start_datetime, $end_datetime ) = self::get_start_and_end_datetime( $date );

		$query = ( new static() )->get_query_builder();
		$query->where( 'news_datetime', $start_datetime->format( 'Y-m-d H:i:s' ), '>=' );
		$query->where( 'news_datetime', $end_datetime->format( 'Y-m-d H:i:s' ), '<=' );

		return $query->get();
	}

	/**
	 * @param  string  $date
	 *
	 * @return array
	 */
	public static function get_start_and_end_datetime( string $date ): array {
		$datetime = DateTime::createFromFormat( 'Y-m-d', $date, new DateTimeZone( 'UTC' ) );

		$start_datetime = clone $datetime;
		$start_datetime->modify( 'midnight' );

		$end_datetime = clone $datetime;
		$end_datetime->modify( 'tomorrow' );
		$end_datetime->setTimestamp( $end_datetime->getTimestamp() - 1 );

		return array( $start_datetime, $end_datetime );
	}

	public static function is_it_duplicate( array $article ): bool {
		// Don't check duplicate news if it is disabled.
		if ( false === EventRegistryNewsApiSettings::is_duplicate_checking_enabled() ) {
			return false;
		}
		$min_percent = EventRegistryNewsApiSettings::get_similarity_in_percent();
		$titles      = static::find_title_by_date( $article );
		$similarity  = array( 1, 2 );
		foreach ( $titles as $title ) {
			similar_text( $article['title'], $title, $percent );
			$similarity[] = $percent;
		}

		return max( $similarity ) >= $min_percent;
	}

	public static function find_title_by_date( array $article ): array {
		$include_ids  = OpenAiReCreateNewsTitle::init()->get_pending_background_tasks();
		$hour_count   = EventRegistryNewsApiSettings::get_num_of_hours_for_similarity();
		$end_datetime = DateTime::createFromFormat(
			'Y-m-d H:i:s',
			$article['news_datetime'],
			new DateTimeZone( 'UTC' )
		);

		$start_datetime = clone $end_datetime;
		if ( $hour_count === 1 ) {
			$start_datetime->modify( '-1 hour' );
		} elseif ( $hour_count > 1 ) {
			$start_datetime->modify( sprintf( '-%s hours', $hour_count ) );
		}

		global $wpdb;
		$table = ( new static() )->get_table_name();
		$sql   = $wpdb->prepare(
			"SELECT id, title FROM $table WHERE news_datetime >= %s AND news_datetime <= %s",
			$start_datetime->format( 'Y-m-d H:i:s' ),
			$end_datetime->format( 'Y-m-d H:i:s' )
		);
		$sql   .= $wpdb->prepare( ' AND id != %d', intval( $article['id'] ) );
		if ( $include_ids ) {
			$include_ids = array_map( 'intval', $include_ids );
			$sql         .= ' AND ( id IN (' . implode( ',', $include_ids ) . ') OR openai_news_id > 0 )';
		} else {
			$sql .= ' AND openai_news_id > 0';
		}
		$results = $wpdb->get_results( $sql, ARRAY_A );

		$data = array();
		foreach ( $results as $result ) {
			$data[ $result['id'] ] = $result['title'];
		}

		return $data;
	}

	/**
	 * Get un-sync items
	 *
	 * @param  int  $limit  Max items to fetch.
	 *
	 * @return array
	 */
	public static function get_unsync_items( int $limit = 100 ): array {
		$pending_tasks = OpenAiReCreateNewsTitle::init()->get_pending_background_tasks();
		$query         = ( new static() )->get_query_builder();
		$query->where( 'openai_news_id', 0 );
		if ( count( $pending_tasks ) ) {
			$query->where( 'id', $pending_tasks, 'NOT IN' );
		}
		$query->limit( $limit );

		return $query->get();
	}

	/**
	 * Get un-sync items count
	 *
	 * @return int
	 */
	public static function get_unsync_items_count(): int {
		$query = ( new static() )->get_query_builder();
		$query->where( 'openai_news_id', 0 );

		return $query->count();
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
		if ( $settings->is_service_provider_naver() ) {
			return NaverApiClient::sync_news( $settings, $force );
		}
		$news = ( new Client() )->get_articles( $settings, $force );
		if ( is_wp_error( $news ) ) {
			return $news;
		}
		if ( ! ( is_array( $news ) && isset( $news['results'], $news['pages'] ) ) ) {
			return new WP_Error( 'no_news_found', 'No news found from news api.' );
		}

		$store = new static();

		$existing_news_ids      = array();
		$new_ids                = array();
		$articles               = array();
		$total_omitted_articles = 0;
		foreach ( $news['results'] as $result ) {
			$slug = sanitize_title_with_dashes( $result['title'], '', 'save' );
			$slug = mb_substr( $slug, 0, 250 );

			$existing_news = static::find_by_slug_or_uri( $slug, $result['uri'] );
			if ( $existing_news instanceof Article ) {
				$article_id          = $existing_news->get_id();
				$existing_news_ids[] = $article_id;
				$articles[]          = array_merge(
					$result,
					array(
						'id'   => $article_id,
						'type' => 'existing',
					)
				);
				continue;
			}

			$news_time   = strtotime( $result['dateTimePub'] );
			$max_minutes = Setting::get_news_not_before_in_minutes();
			$not_before  = time() - ( $max_minutes * MINUTE_IN_SECONDS );

			if ( $news_time < $not_before ) {
				$articles[]             = array_merge(
					$result,
					array(
						'id'   => 0,
						'type' => 'very-old',
					)
				);
				$total_omitted_articles += 1;
				continue;
			}

			$article = $store::format_api_data_for_database( $result, $settings );

			if ( Setting::sync_image_copy_setting_from_source() ) {
				$source = NewsSource::find_by_uri( $article['source_uri'] );
				if ( $source instanceof NewsSource && ! $source->should_copy_image() ) {
					$article['image'] = '';
				}
			}

			$id = $store->create( $article );
			if ( $id ) {
				$new_ids[]  = $id;
				$articles[] = array_merge(
					$result,
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
				$article = self::find_by_id( $id );
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
				'total_pages'          => $news['pages'],
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
			'total_pages'          => $news['pages'],
		);
	}

	/**
	 * Find news by slug
	 *
	 * @param  string  $slug  News slug.
	 * @param  string  $uri  News uri.
	 *
	 * @return Article|false
	 */
	public static function find_by_slug_or_uri( string $slug, ?string $uri = '' ) {
		global $wpdb;
		$self  = new static();
		$table = $self->get_table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug );
		if ( $uri ) {
			$sql .= $wpdb->prepare( ' OR uri = %s', $uri );
		}
		$result = $wpdb->get_row( $sql, ARRAY_A );
		if ( $result ) {
			return new Article( $result );
		}

		return false;
	}

	/**
	 * Find news by slug
	 *
	 * @param  string  $title  News title.
	 *
	 * @return Article|false
	 */
	public static function find_by_title( string $title ) {
		global $wpdb;
		$self   = new static();
		$table  = $self->get_table_name();
		$sql    = $wpdb->prepare( "SELECT * FROM $table WHERE title = %s", $title );
		$result = $wpdb->get_row( $sql, ARRAY_A );
		if ( $result ) {
			return new Article( $result );
		}

		return false;
	}

	/**
	 * Format api data for database
	 *
	 * @param  array  $data
	 * @param  array  $sync_settings
	 * @param  bool  $copy_news_image
	 *
	 * @return array
	 */
	public static function format_api_data_for_database( array $data, SyncSettingsStore $settings ): array {
		$sync_settings = $settings->to_array();
		$slug          = sanitize_title_with_dashes( $data['title'], '', 'save' );
		$slug          = mb_substr( $slug, 0, 250 );

		$category         = is_array( $sync_settings['categoryUri'] ) && count( $sync_settings['categoryUri'] ) ?
			$sync_settings['categoryUri'][0] : $sync_settings['categoryUri'];
		$primary_category = is_array( $sync_settings['primary_category'] ) ? $sync_settings['primary_category'][0] :
			$sync_settings['primary_category'];
		$location         = is_array( $sync_settings['locationUri'] ) && count( $sync_settings['locationUri'] ) ?
			$sync_settings['locationUri'][0] : $sync_settings['locationUri'];

		return array(
			'uri'               => $data['uri'],
			'data_type'         => $data['dataType'],
			'lang'              => $data['lang'],
			'news_source_url'   => $data['url'],
			'title'             => $data['title'],
			'slug'              => $slug,
			'body'              => $data['body'],
			'title_words_count' => Utils::str_word_count_utf8( $data['title'] ),
			'body_words_count'  => Utils::str_word_count_utf8( $data['body'] ),
			'source_title'      => $data['source']['title'] ?? '',
			'source_uri'        => $data['source']['uri'] ?? '',
			'links'             => static::sanitize_links( $data['links'] ?? '' ),
			'source_data_type'  => $data['source']['dataType'] ?? '',
			'image'             => $settings->should_copy_image() ? $data['image'] : '',
			'event_uri'         => $data['eventUri'],
			'sim'               => (float) $data['sim'],
			'sentiment'         => (float) $data['sentiment'],
			'category'          => $category,
			'primary_category'  => $primary_category,
			'location'          => $location,
			'concept'           => $settings->get_primary_concept(),
			'news_datetime'     => gmdate( 'Y-m-d H:i:s', strtotime( $data['dateTimePub'] ) ),
			'news_filtering'    => $settings->is_news_filtering_enabled() ? 1 : 0,
			'sync_settings'     => static::sanitize_sync_settings( $sync_settings ),
		);
	}

	/**
	 * @param $settings
	 *
	 * @return array
	 */
	public static function sanitize_sync_settings( $settings ): array {
		$black_list = array( 'query_info', 'last_sync', 'concepts', 'sources', 'news_filtering_instruction' );
		$options    = array();
		foreach ( $settings as $key => $value ) {
			if ( empty( $value ) || in_array( $key, $black_list, true ) ) {
				continue;
			}
			$options[ $key ] = $value;
		}

		return $options;
	}

	/**
	 * Delete old news
	 *
	 * @param  int  $day  Number of days.
	 *
	 * @return void
	 */
	public static function delete_old_articles( int $day = 3 ) {
		global $wpdb;
		$self  = new static();
		$table = $self->get_table_name();
		$time  = time() - ( max( 1, $day ) * DAY_IN_SECONDS );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE `openai_news_id` = 0 AND created_at <= %s",
				gmdate( 'Y-m-d H:i:s', $time )
			)
		);
	}

	/**
	 * Create table
	 */
	public static function create_table() {
		global $wpdb;
		$self    = new static();
		$table   = $self->get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `uri` varchar(20) NULL DEFAULT NULL COMMENT 'News unique id. Can be used to get details.',
                `data_type` varchar(20) NULL DEFAULT NULL COMMENT 'Data type',
                `lang` varchar(20) NULL DEFAULT NULL COMMENT 'Data language',
                `news_source_url` text NULL DEFAULT NULL COMMENT 'direct URL to the article',
                `title` text NULL DEFAULT NULL COMMENT 'Headline of the article',
    			`slug` VARCHAR(255) NULL DEFAULT NULL,
                `body` longtext NULL DEFAULT NULL COMMENT 'Article body',
    			`title_words_count` INT UNSIGNED NOT NULL DEFAULT '0',
    			`body_words_count` INT UNSIGNED NOT NULL DEFAULT '0',
                `source_title` varchar(255) NULL DEFAULT NULL COMMENT 'Source name',
                `source_uri` varchar(255) NULL DEFAULT NULL COMMENT 'Source URI',
    			`links` text NULL DEFAULT NULL,
                `source_data_type` varchar(255) NULL DEFAULT NULL COMMENT 'Source type',
                `image` text NULL DEFAULT NULL COMMENT 'direct URL to the article thumbnail image',
    			`image_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                `event_uri` varchar(255) NULL DEFAULT NULL,
                `sim` FLOAT NOT NULL DEFAULT 0,
                `sentiment` FLOAT NOT NULL DEFAULT 0,
                `location` varchar(255) NULL DEFAULT NULL,
                `category` varchar(255) NULL DEFAULT NULL,
    			`concept` varchar(255) NULL DEFAULT null,
                `primary_category` varchar(50) NOT NULL DEFAULT 'general',
				`country` char(2) NULL DEFAULT NULL,
				`news_datetime` datetime NULL DEFAULT NULL,
    			`news_filtering` TINYINT UNSIGNED NOT NULL DEFAULT '0',
    			`openai_news_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    			`openai_error` text NULL DEFAULT NULL,
    			`sync_settings` longtext NULL DEFAULT NULL,
    			`created_at` DATETIME NULL DEFAULT NULL,
    			`updated_at` DATETIME NULL DEFAULT NULL,
				PRIMARY KEY (id),
    			UNIQUE `uri` (`uri`),
    			UNIQUE `slug` (`slug`),
    			INDEX `source_uri` (`source_uri`),
    			INDEX `category` (`category`),
    			INDEX `concept` (`concept`),
    			INDEX `primary_category` (`primary_category`),
    			INDEX `location` (`location`),
    			INDEX `country` (`country`)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
	}

	public static function sanitize_links( $links ): array {
		if ( ! is_array( $links ) ) {
			return array();
		}
		$black_list_links = array(
			'chat.whatsapp.com',
			'login',
			'signup',
		);
		$black_list_index = array();
		foreach ( $black_list_links as $black_list_link ) {
			foreach ( $links as $index => $link ) {
				if ( false !== strpos( $link, $black_list_link ) ) {
					$black_list_index[ $index ] = $link;
				}
			}
		}

		return array_diff( $links, $black_list_index );
	}

	/**
	 * Count record from database
	 *
	 * @param  array  $args  The optional arguments.
	 *
	 * @return array {
	 * Number of found records for each group.
	 *
	 * @type int $all The total number of records except trashed.
	 * @type int $trash The total number of trashed records.
	 * }
	 */
	public function count_records( array $args = array() ): array {
		$cache_key = $this->get_cache_key_for_count_records( $args );
		$counts    = $this->get_cache( $cache_key );

		if ( false === $counts ) {
			global $wpdb;
			$table = $this->get_table_name();

			$counts = array();
			$sql    = "SELECT COUNT(*) AS total_records FROM {$table} WHERE 1 = 1";

			if ( isset( $args['datetime_start'], $args['datetime_end'] ) ) {
				$sql .= $wpdb->prepare(
					' AND news_datetime BETWEEN %s AND %s',
					$args['datetime_start'],
					$args['datetime_end']
				);
			}

			if ( ! empty( $args['country'] ) ) {
				$sql .= $wpdb->prepare( ' AND country = %s', $args['country'] );
			}

			if ( ! empty( $args['category'] ) ) {
				$sql .= $wpdb->prepare( ' AND primary_category = %s', $args['category'] );
			}

			if ( $args['in_sync'] ) {
				$ids = OpenAiReCreateNewsTitle::init()->get_pending_background_tasks();
				$ids = array_map( 'intval', $ids );
				$sql .= ' AND id IN(' . implode( ',', $ids ) . ')';
			}

			if ( $args['search'] ) {
				$sql .= $wpdb->prepare( ' AND title LIKE %s', '%' . $args['search'] . '%' );
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$row           = $wpdb->get_row( $sql, ARRAY_A );
			$counts['all'] = isset( $row['total_records'] ) ? intval( $row['total_records'] ) : 0;

			// Set cache for one day.
			$this->set_cache( $cache_key, $counts, DAY_IN_SECONDS );
		}

		return $counts;
	}
}

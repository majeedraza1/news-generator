<?php

namespace StackonetNewsGenerator\OpenAIApi\Stores;

use Stackonet\WP\Framework\Abstracts\DataStoreBase;
use StackonetNewsGenerator\OpenAIApi\Models\InstagramAttemptLog;
use StackonetNewsGenerator\OpenAIApi\News;

class NewsStore extends DataStoreBase {
	protected $table = 'openai_news';
	protected $status = 'sync_status';
	protected $model = News::class;

	/**
	 * Get news by source id
	 *
	 * @param  int  $source_id  Source news id.
	 *
	 * @return false|News
	 */
	public static function find_by_id( int $news_id ) {
		global $wpdb;
		$table  = ( new self() )->get_table_name();
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $news_id ),
			ARRAY_A
		);
		if ( $result ) {
			return new News( $result );
		}

		return false;
	}

	/**
	 * Get news by source id
	 *
	 * @param  int  $source_id  Source news id.
	 *
	 * @return false|News
	 */
	public static function find_by_source_id( int $source_id ) {
		global $wpdb;
		$table  = ( new self() )->get_table_name();
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE source_id = %d", $source_id ),
			ARRAY_A
		);
		if ( $result ) {
			return new News( $result );
		}

		return false;
	}

	/**
	 * Get news by source id
	 *
	 * @param  array  $news_ids  Source news id.
	 *
	 * @return array|News[]
	 */
	public static function find_by_ids( array $news_ids ): array {
		$news_ids = array_map( 'intval', $news_ids );
		$news     = array();
		if ( count( $news_ids ) ) {
			global $wpdb;
			$table  = ( new self() )->get_table_name();
			$sql    = "SELECT * FROM $table WHERE id IN(" . implode( ',', $news_ids ) . ')';
			$result = $wpdb->get_results( $sql, ARRAY_A );
			foreach ( $result as $item ) {
				$news[] = new News( $item );
			}
		}

		return $news;
	}

	/**
	 * Get unsent news
	 *
	 * @return News[]|array
	 */
	public static function get_unsent_news(): array {
		global $wpdb;
		$self   = new static();
		$table  = $self->get_table_name();
		$table2 = $self->get_table_name( 'openai_news_to_site_logs' );

		$sql = "SELECT t1.* FROM {$table} as t1 LEFT JOIN {$table2} as t2 ON t1.id = t2.news_id";
		$sql .= ' WHERE t2.news_id IS NULL';

		try {
			$datetime = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
			$datetime->modify( '- 1 hour' );

			$sql .= $wpdb->prepare( ' AND t1.created_at >= %s', $datetime->format( 'Y-m-d H:i:s' ) );
		} catch ( \Exception $e ) {
		}

		$sql    .= ' ORDER BY t1.id DESC';
		$sql    .= ' LIMIT 100';
		$result = $wpdb->get_results( $sql, ARRAY_A );

		$items = array();
		foreach ( $result as $item ) {
			$items[] = new News( $item );
		}

		return $items;
	}

	/**
	 * Get news for instagram
	 *
	 * @param  bool  $force
	 * @param  array  $news_ids
	 *
	 * @return array
	 */
	public static function get_news_for_instagram( bool $force, array $news_ids = array() ): array {
		$results = get_transient( 'news_for_instagram' );
		if ( ! is_array( $results ) || $force ) {
			$news_ids       = array_map( 'intval', $news_ids );
			$total_news_ids = count( $news_ids );
			global $wpdb;
			$self       = new static();
			$table      = $self->get_table_name();
			$not_before = gmdate( 'Y-m-d H:i:s', ( time() - HOUR_IN_SECONDS ) );

			$sql = "SELECT * FROM {$table} WHERE use_for_instagram = 0";

			if ( $total_news_ids ) {
				if ( $total_news_ids > 1 ) {
					$sql .= ' AND id IN(' . implode( ',', $news_ids ) . ')';
				} else {
					$sql .= $wpdb->prepare( ' AND id = %d', $news_ids[0] );
				}
			} else {
				$max_id = (int) get_option( 'news_for_instagram_up_to' );
				if ( $max_id ) {
					$sql .= $wpdb->prepare( ' AND id > %d', $max_id );
				}
			}

			$sql .= $wpdb->prepare( ' AND sync_status = %s AND created_at >= %s', 'complete', $not_before );
			$sql .= ' ORDER BY id DESC';
			$sql .= ' LIMIT 100';

			$all_results = $wpdb->get_results( $sql, ARRAY_A );

			$results = array();
			foreach ( $all_results as $result ) {
				$image_id = intval( $result['image_id'] ?? 0 );
				if ( $image_id ) {
					$results[] = $result;
				}
			}

			if ( count( $all_results ) > 0 && count( $results ) < 1 ) {
				InstagramAttemptLog::error(
					array(
						'message'    => 'No news found with image. Using all news without image.',
						'suggestion' => $news_ids,
						'force_run'  => $force ? 1 : 0,
						'query_sql'  => $sql,
					)
				);
				$results = $all_results;
			}

			// Cache it for an hour.
			set_transient( 'news_for_instagram', $results, HOUR_IN_SECONDS );
			if ( count( $results ) ) {
				$ids    = array_map( 'intval', wp_list_pluck( $results, 'id' ) );
				$max_id = count( $ids ) > 1 ? max( $ids ) : $ids[0];
				update_option( 'news_for_instagram_up_to', $max_id, true );
				InstagramAttemptLog::success(
					array(
						'message'    => sprintf( 'New news selection from ids %s', implode( ',', $news_ids ) ),
						'query_sql'  => $sql,
						'suggestion' => $ids,
						'force_run'  => $force ? 1 : 0,
					)
				);
			} else {
				InstagramAttemptLog::error(
					array(
						'message'   => sprintf( 'No news found from ids %s', implode( ',', $news_ids ) ),
						'force_run' => $force ? 1 : 0,
						'query_sql' => $sql,
					)
				);
			}
		}

		$items = array();
		foreach ( $results as $item ) {
			$items[] = new News( $item );
		}

		return $items;
	}

	/**
	 * Get last one hour instagram news
	 *
	 * @return News[]
	 */
	public static function get_last_one_hour_instagram_news(): array {
		$required_fields = array( 'instagram_heading', 'instagram_body' );
		global $wpdb;
		$self       = new static();
		$table      = $self->get_table_name();
		$not_before = gmdate( 'Y-m-d H:i:s', ( time() - HOUR_IN_SECONDS ) );

		$sql = "SELECT * FROM {$table} WHERE use_for_instagram = 1";
		$sql .= $wpdb->prepare( ' AND sync_status = %s AND created_at >= %s', 'complete', $not_before );

		foreach ( $required_fields as $field ) {
			$sql .= " AND ($field IS NOT NULL OR $field != '')";
		}

		$sql .= ' ORDER BY id DESC';
		$sql .= ' LIMIT 10';

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$items = array();
		foreach ( $results as $item ) {
			$items[] = new News( $item );
		}

		return $items;
	}

	/**
	 * Get last one hour twitter news
	 *
	 * @return News[]
	 */
	public static function get_last_one_hour_twitter_news(): array {
		$required_fields = array( 'tweet' );
		global $wpdb;
		$self       = new static();
		$table      = $self->get_table_name();
		$not_before = gmdate( 'Y-m-d H:i:s', ( time() - HOUR_IN_SECONDS ) );

		$sql = "SELECT * FROM {$table} WHERE important_for_tweet = 1";
		$sql .= $wpdb->prepare( ' AND sync_status = %s AND created_at >= %s', 'complete', $not_before );

		foreach ( $required_fields as $field ) {
			$sql .= " AND ($field IS NOT NULL OR $field != '')";
		}

		$sql .= ' ORDER BY id DESC';
		$sql .= ' LIMIT 10';

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$items = array();
		foreach ( $results as $item ) {
			$items[] = new News( $item );
		}

		return $items;
	}

	/**
	 * Get duplicate images
	 *
	 * @param  int  $limit  Limit the result.
	 *
	 * @return array|\WP_Post[]
	 */
	public static function get_duplicate_images( int $limit = 100 ): array {
		global $wpdb;
		$sql = "SELECT t1.* FROM `{$wpdb->posts}` as t1";
		$sql .= " LEFT JOIN `{$wpdb->prefix}openai_news` as t2 ON t1.ID = t2.image_id";
		$sql .= " WHERE t1.post_type = 'attachment' AND t1.post_parent = 0";
		$sql .= ' AND t2.image_id IS NULL';
		$sql .= $wpdb->prepare( ' ORDER BY t1.ID ASC LIMIT %d', $limit );

		$results = $wpdb->get_results( $sql );
		$posts   = array();
		foreach ( $results as $result ) {
			$_post = sanitize_post( $result, 'raw' );
			wp_cache_add( $_post->ID, $_post, 'posts' );
			$posts[] = new \WP_Post( $_post );
		}

		return $posts;
	}

	/**
	 * Get news by source id
	 *
	 * @param  int  $source_id  Source news id.
	 *
	 * @return false|News
	 */
	public static function delete_duplicate_news( int $news_id, int $source_id ) {
		global $wpdb;
		$table  = ( new self() )->get_table_name();
		$result = $wpdb->query(
			$wpdb->prepare( "DELETE FROM $table WHERE id != %s AND source_id = %d", $news_id, $source_id )
		);

		return (bool) $result;
	}

	/**
	 * Get processing news id
	 *
	 * @param  int  $min_id  Minimum news id.
	 *
	 * @return array|News[]
	 */
	public static function get_processing_news( int $min_id = 0 ): array {
		global $wpdb;
		$table = ( new self() )->get_table_name();

		$sql = "SELECT * FROM $table WHERE sync_status = 'in-progress'";
		$sql .= " AND (body IS NULL OR body = '')";
		if ( $min_id ) {
			$sql .= $wpdb->prepare( ' AND id > %d', $min_id );
		}

		$sql .= ' ORDER BY id DESC';
		$sql .= ' LIMIT 100';

		$results = $wpdb->get_results( $sql, ARRAY_A );
		$items   = array();
		foreach ( $results as $result ) {
			$items[] = new News( $result );
		}

		return $items;
	}

	/**
	 * Get failed news for re-sync
	 *
	 * @return array|News[]
	 */
	public static function get_news_for_resync(): array {
		global $wpdb;
		$self  = new static();
		$table = $self->get_table_name();

		$sql = $wpdb->prepare( "SELECT * FROM $table WHERE sync_status != %s", 'complete' );

		$time = time() - HOUR_IN_SECONDS;
		$date = gmdate( 'Y-m-d H:i:s', $time );

		$sql .= $wpdb->prepare( ' AND created_at >= %s', $date );
		$sql .= ' ORDER BY id DESC';
		$sql .= ' LIMIT 100';

		$results = $wpdb->get_results( $sql, ARRAY_A );
		$items   = array();
		foreach ( $results as $result ) {
			$news = new News( $result );
			if ( $news->is_in_progress() ) {
				$items[] = $news;
			} elseif ( $news->is_sync_fail() && $news->has_title_and_content() ) {
				$items[] = $news;
			}
		}

		return $items;
	}

	public static function delete_failed_news() {
		global $wpdb;
		$self  = new self();
		$table = $self->get_table_name();
		$sql   = $wpdb->prepare( "DELETE FROM $table WHERE sync_status = %s", 'fail' );

		return $wpdb->query( $sql );
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
			$counts = array();

			$filter_by = $args['filter_by'] ?? '';

			$query = $this->get_query_builder();
			$query->where( 'sync_status', 'complete' );
			$query->where( 'openai_skipped', 0 );
			if ( 'use_for_instagram' === $filter_by ) {
				$query->where( 'use_for_instagram', 1 );
			} elseif ( 'important_for_tweet' === $filter_by ) {
				$query->where( 'important_for_tweet', 1 );
			} elseif ( 'has_image_id' === $filter_by ) {
				$query->where( 'image_id', 0, '>' );
			}

			$counts['openai-complete'] = $query->count();

			$query2 = $this->get_query_builder();
			$query2->where( 'sync_status', 'in-progress' );
			$query2->where( 'openai_skipped', 0 );
			$counts['in-progress'] = $query2->count();

			$query3 = $this->get_query_builder();
			$query3->where( 'sync_status', 'fail' );
			$query3->where( 'openai_skipped', 0 );
			$counts['fail'] = $query3->count();

			$query4 = $this->get_query_builder();
			$query4->where( 'sync_status', 'complete' );
			$query4->where( 'openai_skipped', 1 );
			if ( 'use_for_instagram' === $filter_by ) {
				$query4->where( 'use_for_instagram', 1 );
			} elseif ( 'important_for_tweet' === $filter_by ) {
				$query4->where( 'important_for_tweet', 1 );
			} elseif ( 'has_image_id' === $filter_by ) {
				$query4->where( 'image_id', 0, '>' );
			}
			$counts['skipped-openai'] = $query4->count();

			$counts['complete'] = $counts['openai-complete'] + $counts['skipped-openai'];

			// Set cache for one day.
			$this->set_cache( $cache_key, $counts, DAY_IN_SECONDS );
		}

		return $counts;
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
				`source_id` BIGINT(20) UNSIGNED NOT NULL,
                `title` text NULL DEFAULT NULL,
                `body` longtext NULL DEFAULT NULL,
                `meta` text NULL DEFAULT NULL,
    			`important_for_tweet` TINYINT(1) NOT NULL DEFAULT '0',
                `tweet` text NULL DEFAULT NULL,
    			`use_for_instagram` TINYINT(1) NOT NULL DEFAULT 0,
    			`instagram_heading` text NULL DEFAULT NULL,
    			`instagram_subheading` text NULL DEFAULT NULL,
    			`instagram_body` text NULL DEFAULT NULL,
    			`instagram_hashtag` text NULL DEFAULT NULL,
    			`instagram_image_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                `facebook` text NULL DEFAULT NULL,
    			`linkedin_text` text NULL DEFAULT NULL,
                `tags` text NULL DEFAULT NULL,
    			`news_faqs` longtext NULL DEFAULT NULL,
    			`focus_keyphrase` varchar(255) NULL DEFAULT NULL,
    			`medium` text NULL DEFAULT NULL,
    			`tumblr` text NULL DEFAULT NULL,
    			`image_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    			`primary_category` varchar(50) NOT NULL DEFAULT 'general',
    			`openai_category` varchar(50) NULL DEFAULT NULL,
    			`openai_category_response` TEXT NULL DEFAULT NULL,
    			`sync_status` varchar(20) NOT NULL DEFAULT 'complete',
    			`openai_error` text NULL DEFAULT NULL,
    			`sync_setting_id` varchar(50) NULL DEFAULT NULL,
    			`live_news` TINYINT(1) NOT NULL DEFAULT 0,
    			`country_code` CHAR(2) NULL DEFAULT NULL,
    			`created_via` VARCHAR(50) NULL DEFAULT 'newsapi.ai',
    			`total_time` VARCHAR(6) NULL DEFAULT '0',
    			`total_request_to_openai` VARCHAR(6) NULL DEFAULT '0',
    			`has_country_in_title` TINYINT(1) NOT NULL DEFAULT 0,
				`extra_videos` text NULL DEFAULT NULL,
				`extra_images` text NULL DEFAULT NULL,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			$fk_table      = $self->get_table_name( 'event_registry_news' );
			$constant_name = $self->get_foreign_key_constant_name( $table, $fk_table );
			$sql           = "ALTER TABLE `{$table}` ADD CONSTRAINT $constant_name FOREIGN KEY (`source_id`)";
			$sql           .= " REFERENCES `{$fk_table}`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;";
			$wpdb->query( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
		if ( version_compare( $version, '1.1.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `openai_skipped` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sync_status`" );
			update_option( $table . '_version', '1.1.0' );
		}
		if ( version_compare( $version, '1.2.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `primary_concept` varchar(255) NULL DEFAULT NULL AFTER `primary_category`" );
			update_option( $table . '_version', '1.2.0' );
		}
	}
}

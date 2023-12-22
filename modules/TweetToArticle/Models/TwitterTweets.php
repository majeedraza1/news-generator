<?php

namespace StackonetNewsGenerator\Modules\TweetToArticle\Models;

use DateTime;
use DateTimeZone;
use Exception;
use Stackonet\WP\Framework\Abstracts\DatabaseModel;

/**
 * TwitterTweets class
 */
class TwitterTweets extends DatabaseModel {
	/**
	 * Table name.$tweet['text']
	 *
	 * @var string
	 */
	protected $table = 'twitter_tweets';

	/**
	 * Batch create
	 *
	 * @param  array  $data
	 * @param  string  $username
	 * @param  string  $batch_id
	 *
	 * @return int[]
	 */
	public static function batch_create_if_not_exists( array $data, string $username, string $batch_id ) {
		$tweet_ids = wp_list_pluck( $data, 'id' );

		$query = static::get_query_builder();
		$query->where( 'tweet_id', $tweet_ids, 'IN' );
		$items     = $query->get();
		$existing  = wp_list_pluck( $items, 'tweet_id' );
		$sanitized = [];
		foreach ( $data as $raw_item ) {
			if ( in_array( $raw_item['id'], $existing, true ) ) {
				continue;
			}
			$sanitized[] = static::prepare_item_for_database( $raw_item, $username, $batch_id );
		}

		return static::batch_create( $sanitized );
	}

	/**
	 * Prepare item for database.
	 *
	 * @param  array  $tweet  Twitter data from response.
	 * @param  string  $username  Twitter username.
	 * @param  string|null  $batch_id  Batch id.
	 *
	 * @return array
	 */
	public static function prepare_item_for_database(
		array $tweet,
		string $username,
		?string $batch_id = null
	): array {
		try {
			$datatime   = new DateTime( $tweet['created_at'], new DateTimeZone( 'UTC' ) );
			$created_at = $datatime->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			$created_at = current_time( 'mysql', true );
		}
		if ( ! wp_is_uuid( $batch_id ) ) {
			$batch_id = wp_generate_uuid4();
		}

		return [
			'username'       => $username,
			'tweet_id'       => $tweet['id'],
			'tweet_text'     => $tweet['text'],
			'language'       => $tweet['lang'],
			'tweet_datetime' => $created_at,
			'batch_id'       => $batch_id,
		];
	}

	/**
	 * Create a new record if not exists
	 *
	 * @param  array  $data  Raw data.
	 *
	 * @return int
	 */
	public static function create_if_not_exists( array $data ): int {
		$query = static::get_query_builder();
		$query->where( 'tweet_id', $data['tweet_id'] );
		$item = $query->first();
		if ( ! $item ) {
			return static::create( $data );
		}

		return $item['id'];
	}

	public static function find_by_batch_id( string $batch_id, ?string $username = null ) {
		$query = static::get_query_builder();
		$query->where( 'batch_id', $batch_id );
		if ( $username ) {
			$query->where( 'username', $username );
		}
		$items = $query->get();

		return $items;
	}

	public static function create_table() {
		global $wpdb;
		$self    = new static();
		$table   = $self->get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				`username` VARCHAR(20) NOT NULL,
				`tweet_id` VARCHAR(30) NOT NULL,
                `tweet_text` text NULL DEFAULT NULL,
    			`sync_with_openai` TINYINT(1) NOT NULL DEFAULT 0,
    			`batch_id` CHAR(36) NULL DEFAULT NULL,
    			`tweet_datetime` datetime NULL DEFAULT NULL,
    			`language` CHAR(2) NULL DEFAULT NULL,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id),
    			INDEX `username` (`username`),
    			UNIQUE `tweet_id` (`tweet_id`)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.2.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.2.0' );
		}
	}

	public function to_array(): array {
		$data                   = parent::to_array();
		$data['tweet_datetime'] = mysql_to_rfc3339( $data['tweet_datetime'] );
		$data['created_at']     = mysql_to_rfc3339( $data['created_at'] );
		$data['updated_at']     = mysql_to_rfc3339( $data['updated_at'] );

		return $data;
	}
}

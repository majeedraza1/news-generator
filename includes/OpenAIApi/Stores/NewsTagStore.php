<?php

namespace StackonetNewsGenerator\OpenAIApi\Stores;

use StackonetNewsGenerator\OpenAIApi\Client;
use StackonetNewsGenerator\OpenAIApi\News;
use Stackonet\WP\Framework\Abstracts\DataStoreBase;

class NewsTagStore extends DataStoreBase {
	protected $table = 'openai_news_tags';

	public static function get_tags_for_names( array $names, array $source_info = array() ): array {
		if ( count( $names ) < 1 ) {
			return array();
		}
		global $wpdb;
		$self  = new static();
		$table = $self->get_table_name();
		$sql   = "SELECT * FROM $table WHERE 1 = 1";
		$sql  .= ' AND (';
		foreach ( $names as $index => $name ) {
			if ( $index > 0 ) {
				$sql .= ' OR';
			}
			$slug = sanitize_title_with_dashes( $name, '', 'save' );
			$sql .= $wpdb->prepare( ' slug = %s', $slug );
		}
		$sql .= ' )';

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function get_tags_with_meta_description() {
		global $wpdb;
		$self    = new static();
		$table   = $self->get_table_name();
		$sql     = "SELECT * FROM $table WHERE meta_description IS NOT NULL";
		$results = $wpdb->get_results( $sql, ARRAY_A );

		return $results;
	}

	public static function delete_tags_with_low_count( int $min_count = 2 ) {
		global $wpdb;
		$self  = new static();
		$table = $self->get_table_name();
		$sql   = $wpdb->prepare(
			"DELETE FROM `$table` WHERE count < %d",
			$min_count
		);

		return $wpdb->query( $sql );
	}

	public static function create_from_news( int $limit = 100 ) {
		$last_id = (int) get_option( '_last_processed_news_id_for_news_tags' );
		if ( - 1 === $last_id ) {
			return false;
		}
		$query = ( new NewsStore() )->get_query_builder();
		$query->where( 'id', $last_id, '>' );
		$query->limit( $limit );

		$items = $query->get();
		if ( count( $items ) < 1 ) {
			update_option( '_last_processed_news_id_for_news_tags', - 1 );

			return false;
		}
		$ids  = array();
		$tags = array();
		foreach ( $items as $item ) {
			if ( ! $item instanceof News ) {
				$item = new News( $item );
			}

			$ids[] = $item->get_id();
			foreach ( $item->get_tags() as $tag ) {
				$tags[] = static::first_or_create( $tag );
			}
		}
		update_option( '_last_processed_news_id_for_news_tags', max( $ids ) );

		return array(
			'record' => array(
				'from' => min( $ids ),
				'to'   => max( $ids ),
			),
			'sql'    => $query->get_query_sql(),
			'tags'   => $tags,
		);
	}

	public static function first_or_create( string $title, ?string $slug = null ): array {
		if ( empty( $slug ) ) {
			$slug = sanitize_title_with_dashes( $title, '', 'save' );
		}

		$static = new static();

		$query = $static->get_query_builder();
		$query->where( 'name', $title );
		$query->where( 'slug', $slug );
		$data = $query->first();

		if ( $data ) {
			$count = intval( $data['count'] );
			$static->update(
				array(
					'id'    => $data['id'],
					'count' => $count + 1,
				)
			);

			return $data;
		}
		$data       = array(
			'name'             => $title,
			'slug'             => $slug,
			'count'            => 1,
			'meta_description' => '',
		);
		$data['id'] = $static->create( $data );

		return $data;
	}

	/**
	 * @param int $id
	 *
	 * @return null|string
	 */
	public static function generate_meta_description( int $id, array $source_info = array() ): ?string {
		$store = new static();

		$record = $store->find_single( $id );
		if ( ! $record ) {
			return null;
		}
		if ( ! empty( $record['meta_description'] ) ) {
			return $record['meta_description'];
		}
		$meta = Client::tag_meta_completions( $record['name'], $source_info );
		if ( is_string( $meta ) ) {
			$store->update(
				array(
					'id'               => $id,
					'meta_description' => $meta,
				)
			);

			return $meta;
		}

		return null;
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
                `name` varchar(200) NULL DEFAULT NULL,
                `slug` varchar(200) NULL DEFAULT NULL,
                `description` LONGTEXT NULL DEFAULT NULL,
                `meta_description` TEXT NULL DEFAULT NULL,
				`count` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id),
    			UNIQUE `name` (`name`),
    			UNIQUE `slug` (`slug`)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
	}

	public static function parse_news_tags( $tags_text ) {
		if ( ! is_string( $tags_text ) ) {
			return false;
		}
		$tag_array = News::parse_tag( $tags_text );

		return implode( ',', $tag_array );
	}
}

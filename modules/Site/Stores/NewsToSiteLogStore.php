<?php

namespace StackonetNewsGenerator\Modules\Site\Stores;

use Stackonet\WP\Framework\Abstracts\DataStoreBase;
use StackonetNewsGenerator\Modules\Site\Models\NewsToSiteLog;

/**
 * NewsToSiteLog class
 */
class NewsToSiteLogStore extends DataStoreBase {
	/**
	 * Database table name
	 *
	 * @var string
	 */
	protected $table = 'openai_news_to_site_logs';

	/**
	 * Find by news ids
	 *
	 * @param  int|int[]  $news_id  News id or array of news ids.
	 *
	 * @return array
	 */
	public static function find_by_news_id( $news_id ): array {
		$query = ( new static() )->get_query_builder();
		if ( is_numeric( $news_id ) ) {
			$items = $query->where( 'news_id', $news_id )->get();
		} elseif ( is_array( $news_id ) && count( $news_id ) ) {
			$items = $query->where( 'news_id', $news_id, 'IN' )->get();
		} else {
			$items = [];
		}

		$data = [];
		foreach ( $items as $item ) {
			$data[] = new NewsToSiteLog( $item );
		}

		return $data;
	}

	public static function create_if_not_exists( array $data ) {
		$news_id        = $data['news_id'];
		$site_id        = $data['site_id'];
		$remote_news_id = $data['remote_news_id'];
		$self           = new static();

		$item = $self
			->get_query_builder()
			->where( 'news_id', $news_id )
			->where( 'site_id', $site_id )
			->where( 'remote_news_id', $remote_news_id )
			->first();
		if ( $item ) {
			$data['id'] = $item['id'];
			$self->update( $data );
		} else {
			$self->create( $data );
		}
	}

	/**
	 * Create table
	 */
	public static function create_table() {
		global $wpdb;
		$self    = new self();
		$table   = $self->get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				`news_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				`site_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                `remote_news_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                `remote_site_url` VARCHAR(255) NULL DEFAULT NULL,
                `remote_news_url` TEXT NULL DEFAULT NULL,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			$fk_table      = $self->get_table_name( 'openai_news' );
			$constant_name = $self->get_foreign_key_constant_name( $table, $fk_table );
			$sql           = "ALTER TABLE `{$table}` ADD CONSTRAINT $constant_name FOREIGN KEY (`news_id`)";
			$sql           .= " REFERENCES `{$fk_table}`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;";
			$wpdb->query( $sql );

			$fk_table      = $self->get_table_name( 'openai_news_sites' );
			$constant_name = $self->get_foreign_key_constant_name( $table, $fk_table );
			$sql           = "ALTER TABLE `{$table}` ADD CONSTRAINT $constant_name FOREIGN KEY (`site_id`)";
			$sql           .= " REFERENCES `{$fk_table}`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;";
			$wpdb->query( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
	}
}

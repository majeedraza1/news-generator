<?php

namespace StackonetNewsGenerator\Modules\Keyword\Models;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;
use StackonetNewsGenerator\Supports\Utils;

/**
 * Class Keyword
 *
 * This class represents a keyword entity and extends the DatabaseModel class.
 */
class Keyword extends DatabaseModel {
	/**
	 * Get table name
	 *
	 * @var string
	 */
	protected $table = 'news_keywords';

	/**
	 * Get news id
	 *
	 * @return int
	 */
	public function get_news_id(): int {
		return (int) $this->get_prop( 'news_id' );
	}

	/**
	 * Get keyword
	 *
	 * @return string
	 */
	public function get_keyword(): string {
		return (string) $this->get_prop( 'keyword' );
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function get_title(): string {
		return (string) $this->get_prop( 'title' );
	}

	/**
	 * If it has news body
	 *
	 * @return bool
	 */
	public function has_title(): bool {
		$title = $this->get_title();
		if ( empty( $title ) ) {
			return false;
		}

		$words = Utils::str_word_count_utf8( $title );
		if ( $words < 3 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get body
	 *
	 * @return string
	 */
	public function get_body(): string {
		return (string) $this->get_prop( 'body' );
	}

	/**
	 * If it has news body
	 *
	 * @return bool
	 */
	public function has_body(): bool {
		$body = $this->get_body();
		if ( empty( $body ) ) {
			return false;
		}

		$words = Utils::str_word_count_utf8( $body );
		if ( $words < 100 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get instruction
	 *
	 * @return string
	 */
	public function get_instruction(): string {
		return (string) $this->get_prop( 'instruction' );
	}

	/**
	 * If it has instruction
	 *
	 * @return bool
	 */
	public function has_instruction(): bool {
		return ! empty( $this->get_instruction() );
	}

	/**
	 * Creates a new table in the database.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		$self    = new self();
		$table   = $self->get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `keyword` VARCHAR(255) NULL DEFAULT NULL,
                `instruction` TEXT NULL DEFAULT NULL,
				`news_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
		if ( version_compare( $version, '1.1.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `body` longtext NULL DEFAULT NULL AFTER `keyword`" );
			$wpdb->query( "ALTER TABLE $table ADD COLUMN `title` text NULL DEFAULT NULL AFTER `keyword`" );

			update_option( $table . '_version', '1.1.0' );
		}
	}
}

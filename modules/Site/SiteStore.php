<?php

namespace StackonetNewsGenerator\Modules\Site;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;
use StackonetNewsGenerator\OpenAIApi\News;

/**
 * SiteStore class
 */
class SiteStore extends DatabaseModel {
	/**
	 * Database table name
	 *
	 * @var string
	 */
	protected $table = 'openai_news_sites';

	/**
	 * Sync concept
	 *
	 * @var array
	 */
	protected $sync_concepts;

	/**
	 * Sync category
	 *
	 * @var array
	 */
	protected $sync_categories;

	/**
	 * Get site sync settings
	 *
	 * @return array
	 */
	public function get_sync_settings(): array {
		$settings = $this->get_prop( 'sync_settings' );
		if ( is_array( $settings ) ) {
			return $settings;
		}

		return array();
	}

	/**
	 * Get sync concept
	 *
	 * @return array
	 */
	public function get_sync_concepts(): array {
		if ( ! is_array( $this->sync_concepts ) ) {
			$this->sync_concepts = array();
			$placeholders        = array(
				'http://en.wikipedia.org/wiki/'  => '',
				'https://en.wikipedia.org/wiki/' => '',
			);
			foreach ( $this->get_sync_settings() as $sync_setting ) {
				if ( 'concept' === $sync_setting['sync_method'] ) {
					$this->sync_concepts[] = str_replace(
						array_keys( $placeholders ),
						array_values( $placeholders ),
						$sync_setting['concept']
					);
				}
			}
		}

		return $this->sync_concepts;
	}

	/**
	 * Get sync categories
	 *
	 * @return array
	 */
	public function get_sync_categories(): array {
		if ( ! is_array( $this->sync_categories ) ) {
			$this->sync_categories = array();
			foreach ( $this->get_sync_settings() as $sync_setting ) {
				if ( 'primaryCategory' === $sync_setting['sync_method'] ) {
					$this->sync_categories[] = $sync_setting['primaryCategory'];
				}
			}
		}

		return $this->sync_categories;
	}

	/**
	 * If the news should send to site
	 *
	 * @param  News $news  The news object.
	 *
	 * @return bool
	 */
	public function should_send_news( News $news ): bool {
		$category_slug = $news->get_primary_category_slug();
		if ( in_array( $category_slug, $this->get_sync_categories(), true ) ) {
			return true;
		}
		$openai_category_slug = $news->get_openai_category_slug();
		if ( in_array( $openai_category_slug, $this->get_sync_categories(), true ) ) {
			return true;
		}

		$concept = $news->get_concept();
		if ( mb_strlen( $concept ) ) {
			$placeholders = array( 'http://en.wikipedia.org/wiki/', 'https://en.wikipedia.org/wiki/' );
			$concept      = str_replace( $placeholders, '', $concept );

			if ( in_array( $concept, $this->get_sync_concepts(), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find by URL
	 *
	 * @param  string $site_url  Site url.
	 *
	 * @return array|false
	 */
	public static function find_by_url( string $site_url ) {
		global $wpdb;
		$site_store = new static();
		$table      = $site_store->get_table_name();
		$sql        = $wpdb->prepare(
			"SELECT * FROM $table WHERE site_url = %s OR site_url LIKE %s",
			$site_url,
			rtrim( $site_url, '/' ) . '%'
		);
		$site_info  = $wpdb->get_row( $sql, ARRAY_A );

		return is_array( $site_info ) ? $site_info : false;
	}

	/**
	 * Send general data to sites
	 *
	 * @return void
	 */
	public static function send_general_data_to_sites() {
		$sites = static::find_multiple( array( 'per_page' => 100 ) );
		foreach ( $sites as $site ) {
			( new Site( $site ) )->send_general_data();
		}
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
                `site_url` VARCHAR(255) NULL DEFAULT NULL,
                `auth_credentials` VARCHAR(255) NULL DEFAULT NULL,
                `auth_username` VARCHAR(50) NULL DEFAULT NULL,
                `auth_password` VARCHAR(255) NULL DEFAULT NULL,
                `auth_type` VARCHAR(50) NULL DEFAULT NULL,
                `auth_post_params` VARCHAR(50) NULL DEFAULT NULL,
                `auth_get_args` VARCHAR(50) NULL DEFAULT NULL,
				`last_sync_datetime` datetime NULL DEFAULT NULL,
				`sync_settings` LONGTEXT NULL DEFAULT NULL,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id),
    			INDEX `site_url` (`site_url`)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( $table . '_version', '1.0.0' );
		}
	}
}

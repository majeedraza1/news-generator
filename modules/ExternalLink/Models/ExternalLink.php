<?php

namespace TeraPixelNewsGenerator\Modules\ExternalLink\Models;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;

/**
 * ExternalLink class
 */
class ExternalLink extends DatabaseModel {
	/**
	 * Table name
	 *
	 * @var string
	 */
	protected $table = 'external_links';

	/**
	 * Add link to content
	 *
	 * @param  string  $content
	 *
	 * @return string
	 */
	public static function add_links( string $content ): string {
		$items = static::find_multiple( [ 'per_page' => 1000 ] );
		foreach ( $items as $item ) {
			$title       = $item['name'];
			$replacement = "<a href='" . esc_url( $item['link'] ) . "'>$1</a>";
			$content     = preg_replace( "/({$title})/i", $replacement, $content );
		}

		return $content;
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
				`name` VARCHAR(255) NOT NULL,
                `link` text NULL DEFAULT NULL,
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
	}
}

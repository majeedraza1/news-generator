<?php

namespace StackonetNewsGenerator\OpenAIApi\Models;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;

/**
 * InstagramAttemptLog
 */
class InstagramAttemptLog extends DatabaseModel {
	const LOG_FOR_INSTAGRAM = 'instagram';
	const LOG_FOR_TWITTER = 'twitter';
	const LOG_FOR_LINKEDIN = 'linkedin';
	const LOG_FOR = [ self::LOG_FOR_INSTAGRAM, self::LOG_FOR_TWITTER, self::LOG_FOR_LINKEDIN ];
	/**
	 * Database table name
	 *
	 * @var string
	 */
	protected $table = 'openai_instagram_fail_log';
	protected $status = 'log_for';

	/**
	 * Log a success
	 *
	 * @param  array|string  $data  The data to record
	 * @param  array  $suggestion  Array of suggested ids.
	 *
	 * @return integer
	 */
	public static function success( $data, array $suggestion = array(), string $log_for = null ) {
		if ( is_string( $data ) ) {
			$data = array(
				'message'    => $data,
				'suggestion' => $suggestion,
			);
		}
		$data['type'] = 'success';
		if ( ! isset( $data['log_for'] ) ) {
			$data['log_for'] = $log_for ?? static::LOG_FOR_INSTAGRAM;
		}

		return static::create( $data );
	}

	/**
	 * Log an error
	 *
	 * @param  array|string  $data  The data to record
	 *
	 * @return integer
	 */
	public static function error( $data, string $log_for = null ) {
		if ( is_string( $data ) ) {
			$data = array(
				'message' => $data,
			);
		}
		$data['type'] = 'error';
		if ( ! isset( $data['log_for'] ) ) {
			$data['log_for'] = $log_for ?? static::LOG_FOR_INSTAGRAM;
		}

		return static::create( $data );
	}

	/**
	 * Delete old logs
	 *
	 * @param  int  $day  Number of days.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function delete_old_logs( int $day = 3 ) {
		global $wpdb;
		$table = static::get_table_name();

		$day         = max( 1, $day );
		$day_or_days = 1 === $day ? '- 1 day' : sprintf( '- %s days', $day );
		$datetime    = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$datetime->modify( $day_or_days );

		$sql = "DELETE FROM `{$table}` WHERE 1 = 1";
		$sql .= $wpdb->prepare( " AND created_at <= %s", $datetime->format( 'Y-m-d H:i:s' ) );

		$wpdb->query( $sql );
	}

	/**
	 * Create database table
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
				`type` varchar(50) NULL DEFAULT NULL,
    			`force_run` TINYINT(1) NOT NULL DEFAULT 0,
                `message` longtext NULL DEFAULT NULL,
                `suggestion` longtext NULL DEFAULT NULL,
    			`query_sql` text NULL DEFAULT NULL,
    			`log_for` VARCHAR(50) NOT NULL DEFAULT 'instagram',
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

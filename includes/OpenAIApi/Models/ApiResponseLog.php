<?php

namespace TeraPixelNewsGenerator\OpenAIApi\Models;

use Stackonet\WP\Framework\Abstracts\DatabaseModel;

/**
 * ApiResponseLog Table
 */
class ApiResponseLog extends DatabaseModel {
	/**
	 * Database table name
	 *
	 * @var string
	 */
	protected $table = 'openai_response_log';

	public static function search( $search_keywords ): array {
		global $wpdb;
		$table = static::get_table_name();
		$sql   = "SELECT * FROM {$table} WHERE 1=1";
		if ( is_numeric( $search_keywords ) ) {
			$sql .= $wpdb->prepare( " AND source_id LIKE %s", '%' . intval( $search_keywords ) . '%' );
		} else {
			$sql .= ' AND';
			$sql .= $wpdb->prepare( "  `belongs_to_group` LIKE %s", '%' . $search_keywords . '%' );
		}

		$sql .= ' ORDER BY id DESC';
		$sql .= ' LIMIT 100';

		$results = $wpdb->get_results( $sql, ARRAY_A );
		$data    = [];
		foreach ( $results as $result ) {
			$data[] = new static( $result );
		}

		return $data;
	}

	/**
	 * Get group count
	 *
	 * @return array
	 */
	public static function get_groups_count(): array {
		global $wpdb;
		$table = static::get_table_name();
		$sql   = "SELECT `belongs_to_group`, COUNT(*) AS num_rows FROM {$table}";
		$sql   .= " GROUP BY `belongs_to_group`";

		$results = (array) $wpdb->get_results( $sql, ARRAY_A );

		$counts = [];
		foreach ( $results as $row ) {
			$counts[ $row['belongs_to_group'] ] = intval( $row['num_rows'] );
		}

		return $counts;
	}

	/**
	 * Get total completion time
	 *
	 * @param int $source_id Source id.
	 *
	 * @return array
	 */
	public static function get_completion_time_and_requests_count( int $source_id ): array {
		$logs           = static::get_logs( $source_id );
		$seconds        = 0;
		$total_requests = 0;
		foreach ( $logs as $log ) {
			$seconds += $log->get_total_time();
			++ $total_requests;
		}

		return [
			'total_requests' => $total_requests,
			'total_time'     => ceil( $seconds ),
		];
	}

	/**
	 * Get logs for source news
	 *
	 * @param int $source_id Source news id.
	 *
	 * @return static[]|array
	 */
	public static function get_logs( int $source_id ): array {
		global $wpdb;
		$table   = static::get_table_name();
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE source_id = %d", $source_id ),
			ARRAY_A
		);
		$data    = [];
		foreach ( $results as $result ) {
			$data[] = new static( $result );
		}

		return $data;
	}

	/**
	 * Get log
	 *
	 * @param int $source_id Source news id.
	 * @param string $group Group.
	 *
	 * @return false|array
	 */
	public static function get_log( int $source_id, string $group ) {
		global $wpdb;
		$table  = static::get_table_name();
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE `source_id` = %d AND `belongs_to_group` = %s", $source_id, $group ),
			ARRAY_A
		);
		if ( ! ( is_array( $result ) && isset( $result['api_response'], $result['response_type'] ) ) ) {
			return false;
		}

		if ( 'error' === $result['response_type'] ) {
			return false;
		}

		return maybe_unserialize( $result['api_response'] );
	}

	/**
	 * Get total time
	 *
	 * @return float
	 */
	public function get_total_time(): float {
		return (float) $this->get_prop( 'total_time' );
	}

	/**
	 * Delete old logs
	 *
	 * @param int $day Number of days.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function delete_old_logs( int $day = 3 ) {
		global $wpdb;
		$self  = new static();
		$table = $self->get_table_name();

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
				`model` varchar(50) NULL DEFAULT NULL,
                `instruction` longtext NULL DEFAULT NULL,
                `api_response` longtext NULL DEFAULT NULL,
    			`response_type` varchar(255) NULL DEFAULT NULL,
				`total_time` varchar(20) NULL DEFAULT NULL,
				`total_tokens` varchar(20) NULL DEFAULT NULL,
				`belongs_to_group` varchar(50) NULL DEFAULT NULL,
				`source_type` varchar(50) NULL DEFAULT NULL,
				`source_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
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

	public function to_array(): array {
		$data = parent::to_array();

		$data['created_at'] = mysql_to_rfc3339( $data['created_at'] );
		$data['total_time'] = round( $this->get_total_time(), 2 );
		$data['group']      = $this->get_prop( 'belongs_to_group' );

		return $data;
	}
}

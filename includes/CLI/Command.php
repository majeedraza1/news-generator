<?php

namespace StackonetNewsGenerator\CLI;

use StackonetNewsGenerator\Supports\Utils;

/**
 * Command class
 */
class Command extends \WP_CLI_Command {
	/**
	 * Empty tables
	 *
	 * @param  mixed  $args  The arguments.
	 * @param  mixed  $assoc_args  The additional arguments.
	 *
	 * @return void
	 */
	public function empty_tables( $args, $assoc_args ) {
		global $wpdb;
		foreach ( Utils::get_tables_list() as $table ) {
			$wpdb->query( "TRUNCATE `{$wpdb->prefix}$table`" );
			\WP_CLI::line( "Truncating table: $table" );
		}
		\WP_CLI::success( 'All tables are truncated.' );
	}
}

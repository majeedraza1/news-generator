<?php

namespace StackonetNewsGenerator\Modules\ImportExport;

/**
 * ImportExportManager class
 */
class ImportExportManager {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'wp_ajax_import_sample_data', array( self::$instance, 'import_sample_data' ) );
		}

		return self::$instance;
	}

	/**
	 * Import sample data
	 *
	 * @return void
	 */
	public function import_sample_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'Sorry. This link only for developer to do some testing.',
					'stackonet-news-generator'
				)
			);
		}

		$group    = $_REQUEST['group'] ?? '';
		$response = array(
			'group' => $group,
			'data'  => array(),
		);
		if ( 'snyc_settings' === $group ) {
			$response['data'] = ImportSampleData::sync_settings();
		} elseif ( 'keywords' === $group ) {
			$response['data'] = ImportSampleData::keywords();
		} else {
			wp_die( 'No group info. Please provide a group.' );
		}

		var_dump( $response );
		wp_die();
	}
}

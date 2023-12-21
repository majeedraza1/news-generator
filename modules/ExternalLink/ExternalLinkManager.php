<?php

namespace StackonetNewsGenerator\Modules\ExternalLink;

use StackonetNewsGenerator\Modules\ExternalLink\Models\ExternalLink;

/**
 * ExternalLinkManager class
 */
class ExternalLinkManager {
	/**
	 * The instance of the class
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( static::$instance ) ) {
			self::$instance = new self();

			add_action( 'admin_init', [ ExternalLink::class, 'create_table' ] );
			AdminRestController::init();
			add_action( 'wp_ajax_external_link_test', [ self::$instance, 'external_link_test' ] );
		}

		return self::$instance;
	}

	/**
	 * External link test
	 *
	 * @return void
	 */
	public function external_link_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}

		$text = 'Sayful islam is a WordPress developer. Here the name should be replaced with a link.';
		$text .= " But only Sayful or islam should remain intact.";

		$updated_text = ExternalLink::add_links( $text );

		$text2 = 'Saif Al Araf is his first son. It should not replaced.';

		$updated_text2 = ExternalLink::add_links( $text2 );

		var_dump(
			[
				'has_link_in_text'          => $text,
				'has_link_in_text_updated'  => $updated_text,
				'has_link_in_text2'         => $text2,
				'has_link_in_text_updated2' => $updated_text2,
			]
		);
		wp_die();
	}

}

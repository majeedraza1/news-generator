<?php

namespace TeraPixelNewsGenerator\Modules\Keyword;

use TeraPixelNewsGenerator\Modules\Keyword\Models\Keyword;
use TeraPixelNewsGenerator\Modules\Keyword\Rest\AdminKeywordController;

/**
 * KeywordManager class
 */
class KeywordManager {
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

			add_action( 'admin_init', array( Keyword::class, 'create_table' ) );
			add_filter( 'cron_schedules', array( self::$instance, 'cron_schedules' ) );
			add_action( 'wp', array( self::$instance, 'schedule_cron_event' ) );
			add_action( 'terapixel_news_generator/keyword_sync', array( self::$instance, 'sync' ) );
			add_action( 'admin_notices', array( self::$instance, 'admin_notices' ) );
			add_action( 'wp_ajax_terapixel_keyword_test', array( self::$instance, 'keyword_test' ) );

			AdminKeywordController::init();
			BackgroundKeywordToNews::init();
		}

		return self::$instance;
	}

	/**
	 * Do some testing
	 *
	 * @return void
	 */
	public function keyword_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'Sorry. This link only for developer to do some testing.',
					'terapixel-news-generator'
				)
			);
		}

		$keyword = BackgroundKeywordToNews::sync();
		wp_die();
	}

	/**
	 * Admin notice for next cron event run
	 *
	 * @return void
	 */
	public function admin_notices() {
		$event = wp_get_scheduled_event( 'terapixel_news_generator/keyword_sync' );
		if ( false === $event ) {
			?>
            <div class="notice notice-error is-dismissible">
                <p>Scheduled event (to sync news using keyword) is not running.</p>
            </div>
			<?php
			// try to schedule again.
			wp_schedule_event( time(), 'keyword_sync_interval', 'terapixel_news_generator/keyword_sync' );

			return;
		}
		$dif     = human_time_diff( time(), $event->timestamp );
		$message = sprintf( 'Next cron event will run to generate news from keyword in %s.', $dif );
		?>
        <div class="notice notice-warning is-dismissible">
            <p><?php printf( $message ); ?></p>
        </div>
		<?php
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param  array  $schedules  array of available schedules.
	 *
	 * @return array
	 */
	public function cron_schedules( array $schedules ): array {
		$interval = Setting::get_sync_interval();

		$schedules['keyword_sync_interval'] = array(
			'interval' => $interval * MINUTE_IN_SECONDS,
			'display'  => __( 'TeraPixel: Keyword Sync Interval', 'terapixel-news-generator' ),
		);

		return $schedules;
	}

	/**
	 * Schedule cron event
	 *
	 * @return void
	 */
	public static function schedule_cron_event() {
		if ( ! wp_next_scheduled( 'terapixel_news_generator/keyword_sync' ) ) {
			wp_schedule_event( time(), 'keyword_sync_interval', 'terapixel_news_generator/keyword_sync' );
		}
	}

	/**
	 * Sync data
	 */
	public function sync() {
		BackgroundKeywordToNews::sync();
	}
}

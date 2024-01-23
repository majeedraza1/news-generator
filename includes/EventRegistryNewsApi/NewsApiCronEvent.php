<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

use StackonetNewsGenerator\BackgroundProcess\CopyNewsImage;
use StackonetNewsGenerator\BackgroundProcess\DeleteDuplicateImages;
use StackonetNewsGenerator\BackgroundProcess\OpenAiFindInterestingNews;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateFocusKeyphrase;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsBody;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsTitle;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncInstagramFields;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncNews;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncTwitterFields;
use StackonetNewsGenerator\BackgroundProcess\ProcessNewsTag;
use StackonetNewsGenerator\BackgroundProcess\SyncEventRegistryNews;
use StackonetNewsGenerator\Modules\Site\BackgroundSendNewsToSite;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\Client as OpenAIApiClient;
use StackonetNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use StackonetNewsGenerator\OpenAIApi\Models\InstagramAttemptLog;
use StackonetNewsGenerator\OpenAIApi\Models\InterestingNews;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\Supports\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * NewsApiCronEvent class
 */
class NewsApiCronEvent {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	public static $instance = null;

	/**
	 * Send instagram news to remove sites
	 *
	 * @return void
	 */
	private static function send_instagram_news() {
		$is_lock = get_transient( 'send_instagram_news_log' );
		if ( false === $is_lock ) {
			set_transient( 'send_instagram_news_log', 'yes', ( MINUTE_IN_SECONDS * 10 ) );
			$news_items = NewsStore::get_last_one_hour_instagram_news();
			$ids        = array();
			foreach ( $news_items as $news ) {
				$news->send_to_sites();
				$ids[] = $news->get_id();
			}
			if ( count( $ids ) ) {
				InstagramAttemptLog::success( 'Sync instagram news with remote sites.', $ids );
			}
		}
	}

	/**
	 * Send instagram news to remove sites
	 *
	 * @return void
	 */
	private static function send_twitter_news() {
		$is_lock = get_transient( 'send_twitter_news_log' );
		if ( false === $is_lock ) {
			set_transient( 'send_twitter_news_log', 'yes', ( MINUTE_IN_SECONDS * 10 ) );
			$news_items = NewsStore::get_last_one_hour_twitter_news();
			$ids        = array();
			foreach ( $news_items as $news ) {
				$news->send_to_sites();
				$ids[] = $news->get_id();
			}
			if ( count( $ids ) ) {
				InstagramAttemptLog::success(
					'Sync twitter news with remote sites.',
					$ids,
					InstagramAttemptLog::LOG_FOR_TWITTER
				);
			}
		}
	}

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_filter( 'cron_schedules', array( self::$instance, 'cron_schedules' ) );
			add_action( 'wp', array( self::$instance, 'schedule_cron_event' ) );
			add_action( 'event_registry_news_api/sync', array( self::$instance, 'sync' ) );
			add_action( 'stackonet_news_generator_send_news_to_site', array( self::$instance, 'send_news_to_site' ) );
			add_action( 'stackonet_news_generator_clear_garbage', array( self::$instance, 'clear_garbage' ) );
			add_action( 'stackonet_news_generator_bg_tasks_dispatcher', array( self::$instance, 'dispatcher' ) );
			add_action( 'shutdown', array( self::$instance, 'dispatcher' ) );
			add_action( 'admin_notices', array( self::$instance, 'admin_notices' ) );
		}

		return self::$instance;
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param  array  $schedules  array of available schedules.
	 *
	 * @return array
	 */
	public function cron_schedules( array $schedules ): array {
		$interval = Setting::get_news_sync_interval();

		$schedules['news_sync_interval'] = array(
			'interval' => $interval * MINUTE_IN_SECONDS,
			'display'  => __( 'News Sync Interval', 'stackonet-news-generator' ),
		);

		$schedules['background_progress_checker'] = array(
			'interval' => 2 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 2 minute', 'stackonet-news-generator' ),
		);

		$schedules['stackonet_news_generator_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minute', 'stackonet-news-generator' ),
		);

		return $schedules;
	}

	/**
	 * Schedule cron event
	 *
	 * @return void
	 */
	public static function schedule_cron_event() {
		if ( ! wp_next_scheduled( 'event_registry_news_api/sync' ) ) {
			wp_schedule_event( time(), 'news_sync_interval', 'event_registry_news_api/sync' );
		}
		if ( ! wp_next_scheduled( 'stackonet_news_generator_bg_tasks_dispatcher' ) ) {
			wp_schedule_event( time(), 'background_progress_checker', 'stackonet_news_generator_bg_tasks_dispatcher' );
		}
		if ( ! wp_next_scheduled( 'stackonet_news_generator_send_news_to_site' ) ) {
			wp_schedule_event( time(), 'stackonet_news_generator_five_minutes',
				'stackonet_news_generator_send_news_to_site' );
		}
		if ( ! wp_next_scheduled( 'stackonet_news_generator_clear_garbage' ) ) {
			wp_schedule_event( time(), 'daily', 'stackonet_news_generator_clear_garbage' );
		}
	}

	/**
	 * Admin notice for next cron event run
	 *
	 * @return void
	 */
	public function admin_notices() {
		$event = wp_get_scheduled_event( 'event_registry_news_api/sync' );
		if ( false === $event ) {
			?>
            <div class="notice notice-error is-dismissible">
                <p>Scheduled event (to sync news from newsapi.ai) is not running.</p>
            </div>
			<?php
			return;
		}
		$dif     = human_time_diff( time(), $event->timestamp );
		$message = sprintf( 'Next cron event will run to sync news in %s.', $dif );
		if ( false === Setting::is_auto_sync_enabled() ) {
			$message .= ' Auto sync news is disabled. No news will be synced.';
		}
		$event2 = wp_get_scheduled_event( 'stackonet_news_generator_bg_tasks_dispatcher' );
		if ( false !== $event2 ) {
			$dif     = human_time_diff( time(), $event2->timestamp );
			$message .= '<br>' . sprintf( 'Background process dispatcher cron event will run in %s.', $dif );
		}
		$max_exe_time = Utils::max_execution_time();
		$message      .= '<br>' . sprintf( 'Current max execution time is %s seconds.', $max_exe_time );
		$message      .= ' It takes more than 90 seconds to sync a news from OpenAI.';
		if ( $max_exe_time < 60 ) {
			$message .= ' Current max execution time is not enough. It should be 60 or higher.';
		}

		$mem_limit = Utils::bytes_to_human_size( Utils::get_memory_limit() );
		$message   .= sprintf( ' Current max memory limit is %s', $mem_limit );

		$message .= '<br>' . OpenAIApiClient::daily_status_message();
		$message .= '<br>' . NewsCompletion::rate_limit_message();
		?>
        <div class="notice notice-warning is-dismissible">
            <p><?php printf( $message ); ?></p>
        </div>
		<?php
		$sleep_end = (int) get_option( 'sync_openai_api_sleep_end' );

		if ( time() < $sleep_end ) {
			$human_time = human_time_diff( time(), $sleep_end );
			$message    = get_option( 'sync_openai_api_sleep_message' );
			?>
            <div class="notice notice-error is-dismissible">
                <p>OpenAI Background task is in sleep mode. The task will start again
                    after <strong><?php echo $human_time; ?></strong>. Check OpenAi logs to find the issue.</p>
				<?php
				if ( ! empty( $message ) ) {
					echo '<p><strong>OpenAI Message:</strong> ' . esc_html( $message ) . '</p>';
				}
				?>
            </div>
			<?php
		}
	}

	/**
	 * Sync data
	 */
	public function sync() {
		if ( Setting::is_auto_sync_enabled() ) {
			SyncEventRegistryNews::sync();
			Setting::clear_previous_daily_request_count();
		}
	}

	/**
	 * Dispatch background tasks
	 *
	 * @return void
	 */
	public function dispatcher() {
		$step1 = SyncEventRegistryNews::init();
		if ( $step1->has_pending_items() ) {
			$step1->dispatch();

			return;
		}
		$step2 = OpenAiFindInterestingNews::init();
		if ( $step2->has_pending_items() ) {
			$step2->dispatch();

			return;
		}
		$step3 = OpenAiReCreateNewsTitle::init();
		if ( $step3->has_pending_items() ) {
			$step3->dispatch();

			return;
		}
		$step3a = OpenAiReCreateFocusKeyphrase::init();
		if ( $step3a->has_pending_items() ) {
			$step3a->dispatch();

			return;
		}
		$step3b = OpenAiReCreateNewsBody::init();
		if ( $step3b->has_pending_items() ) {
			$step3b->dispatch();

			return;
		}
		$step3c = CopyNewsImage::init();
		if ( $step3c->has_pending_items() ) {
			$step3c->dispatch();
		}
		$step5 = OpenAiSyncNews::init();
		if ( $step5->has_pending_items() ) {
			$pending  = $step5->get_pending_background_tasks();
			$in_cache = get_transient( 'openai_news_in_sync' );
			if ( false === $in_cache ) {
				set_transient( 'openai_news_in_sync', $pending, HOUR_IN_SECONDS );
			}

			$step5->dispatch();
		} else {
			$in_cache = get_transient( 'openai_news_in_sync' );
			if ( is_array( $in_cache ) ) {
				delete_transient( 'openai_news_in_sync' );
				$this->find_important_news( $in_cache, true );
			}
		}
		$step5b = ProcessNewsTag::init();
		if ( $step5b->has_pending_items() ) {
			$step5b->dispatch();
		}
		$step6 = BackgroundSendNewsToSite::init();
		if ( $step6->has_pending_items() ) {
			$step6->dispatch();
		}
		$step7 = OpenAiSyncInstagramFields::init();
		if ( $step7->has_pending_items() ) {
			$step7->dispatch();
		}
		$step4 = OpenAiSyncTwitterFields::init();
		if ( $step4->has_pending_items() ) {
			$step4->dispatch();
		}
	}

	/**
	 * Send news to site
	 *
	 * @return void
	 */
	public function send_news_to_site() {
		$news_items = NewsStore::get_unsent_news();
		foreach ( $news_items as $news ) {
			$news->send_to_sites();
		}

		$step5 = OpenAiSyncNews::init();
		if ( ! $step5->has_pending_items() ) {
			$items = NewsStore::get_news_for_resync();
			if ( count( $items ) ) {
				foreach ( $items as $news ) {
					OpenAiSyncNews::add_to_sync( array( 'news_id' => $news->get_id() ) );
				}
			}
		}
	}

	/**
	 * Clear garbage
	 *
	 * @return void
	 */
	public function clear_garbage() {
		ArticleStore::delete_old_articles();
		ApiResponseLog::delete_old_logs();
		InterestingNews::delete_old_logs();
		InstagramAttemptLog::delete_old_logs();
		ClientResponseLog::delete_old_logs();

		DeleteDuplicateImages::run();
	}

	/**
	 * Find important news
	 *
	 * @param  array  $ids
	 * @param  bool  $force
	 *
	 * @return void
	 */
	public function find_important_news( array $ids = array(), bool $force = false ) {
		$transient_name = sprintf( 'important_news_for_instagram_last_running_%s', ( $force ? 'force' : 'not_force' ) );
		if ( false !== get_transient( $transient_name ) ) {
			InstagramAttemptLog::error(
				sprintf( 'Another task is already running in %s mode.', ( $force ? 'force' : 'no force' ) )
			);

			return;
		}
		set_transient( $transient_name, 'yes', 30 );
		$last_run = (int) get_transient( 'important_news_for_instagram_last_run' );
		if ( false === $force ) {
			// Run in every 5 minutes.
			if ( ( $last_run + ( MINUTE_IN_SECONDS * 4 ) ) > time() ) {
				InstagramAttemptLog::error(
					sprintf(
						'Cannot run in less than 5 minutes. Last Run: %s',
						gmdate( \DateTime::COOKIE, $last_run )
					)
				);

				return;
			}
			if ( OpenAiSyncNews::init()->has_pending_items() ) {
				InstagramAttemptLog::error(
					sprintf(
						'Waiting to finish pending news sync. Last Run: %s',
						gmdate( \DateTime::COOKIE, $last_run )
					)
				);

				return;
			}
		}
		if ( $force ) {
			OpenAiSyncTwitterFields::find_important_news( $ids );
		}
		$news_array = NewsStore::get_news_for_instagram( $force, $ids );
		$news_ids   = OpenAIApiClient::find_important_news_for_instagram( $news_array, $force );
		if ( is_array( $news_ids ) && count( $news_ids ) ) {
			foreach ( $news_ids as $news_id ) {
				$news = NewsStore::find_by_id( $news_id );
				if ( $news->has_instagram_content() ) {
					$news->send_to_sites();
					if ( $force ) {
						$message = sprintf( 'Sending news #%s(%s) to sites.', $news->get_id(), $news->get_title() );
					} else {
						$message = sprintf( 'Syncing news #%s(%s) with sites.', $news->get_id(), $news->get_title() );
					}
					InstagramAttemptLog::success( $message );
				}
			}
		}
		set_transient( 'important_news_for_instagram_last_run', time() );
	}
}

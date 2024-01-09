<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\News;
use Stackonet\WP\Framework\Supports\Logger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * BackgroundProcessBase class
 */
abstract class BackgroundProcessBase extends BackgroundProcessWithUiHelper {
	/**
	 * Default time limit in seconds
	 *
	 * @var int
	 */
	protected $default_time_limit = 90;

	/**
	 * Queue lock time
	 *
	 * @var int
	 */
	protected $queue_lock_time = 30;

	/**
	 * Get item running unique cache name
	 *
	 * @param int|mixed $item_unique_id Item unique id.
	 * @param string    $group Group.
	 *
	 * @return string
	 */
	public function item_running_cache_name( $item_unique_id, string $group = 'any' ): string {
		return $this->action . '_running_' . md5(
			wp_json_encode(
				[
					'unique_id' => $item_unique_id,
					'group'     => $group,
				]
			)
		);
	}

	/**
	 * Check if it is running or not
	 *
	 * @param int|mixed $item_unique_id Item unique id.
	 * @param string    $group Group.
	 *
	 * @return bool
	 */
	public function is_item_running( $item_unique_id, string $group = 'any' ): bool {
		return false !== get_transient( $this->item_running_cache_name( $item_unique_id, $group ) );
	}

	/**
	 * Check if it is running or not
	 *
	 * @param int|mixed $item_unique_id Item unique id.
	 * @param string    $group Group.
	 * @param int       $expiration Cache expiration.
	 *
	 * @return bool
	 */
	public function set_item_running( $item_unique_id, string $group = 'any', int $expiration = 10 ): bool {
		$transient_name = $this->item_running_cache_name( $item_unique_id, $group );

		return set_transient( $transient_name, 'yes', $expiration );
	}

	/**
	 * Get article fail attempt
	 *
	 * @param Article $article The article object.
	 * @param string  $field The field name.
	 *
	 * @return int
	 */
	public static function get_article_fail_attempt( Article $article, string $field = 'any' ): int {
		$cache_key = sprintf( 'open_ai_sync_fail_attempt_%s_%s', $field, $article->get_id() );
		$attempt   = get_transient( $cache_key );

		return is_numeric( $attempt ) ? intval( $attempt ) : 0;
	}

	/**
	 * Increase article fail attempt
	 *
	 * @param Article $article The article object.
	 * @param string  $field The field name.
	 *
	 * @return void
	 */
	public static function increase_article_fail_attempt( Article $article, string $field = 'any' ) {
		$cache_key = sprintf( 'open_ai_sync_fail_attempt_%s_%s', $field, $article->get_id() );
		$attempt   = static::get_article_fail_attempt( $article ) + 1;
		set_transient( $cache_key, $attempt, DAY_IN_SECONDS );
	}

	/**
	 * Handle too many request.
	 *
	 * @param WP_Error $error The error object.
	 *
	 * @return void
	 */
	protected function handle_too_many_requests( WP_Error $error ) {
		$rest_error = $error->get_error_data( 'rest_error' );
		if ( isset( $rest_error['error']['type'] ) && 'insufficient_quota' === $rest_error['error']['type'] ) {
			$sleep_end = time() + ( MINUTE_IN_SECONDS * 10 );
			update_option( 'sync_openai_api_sleep_message', $rest_error['error']['message'] ?? '', true );
		} else {
			$sleep_end = time() + MINUTE_IN_SECONDS;
		}
		update_option( 'sync_openai_api_sleep_end', $sleep_end, true );
		Logger::log( 'Too Many Requests: Background task in sleep mode.' );
	}

	/**
	 * Process WP_Error object from OpenAI response.
	 *
	 * @param WP_Error   $error The WP_Error object.
	 * @param Article    $article The Article object.
	 * @param false|News $news News object.
	 * @param array      $item Data for background process.
	 *
	 * @return false
	 */
	public function process_wp_error_response( WP_Error $error, Article $article, $news, array $item ): bool {
		Logger::log( 'Fail to sync news from OpenAI. #' . $article->get_id() );
		Logger::log( $error->get_error_message() );

		if ( 'exceeded_max_token' === $error->get_error_code() ) {
			$article->update_field( 'openai_error', $error->get_error_message() );
			if ( $news instanceof News ) {
				$news->update_field( 'sync_status', 'fail' );
				$news->update_field( 'openai_error', $error->get_error_message() );
			}

			return false;
		}

		if ( 'Too Many Requests' === $error->get_error_message() ) {
			$this->handle_too_many_requests( $error );

			// Push the item to bottom of queue to try later.
			OpenAiReCreateNewsTitle::init()->push_to_queue( $item );

			return false;
		}

		// Remove item from task list if more than 10 fail attempt.
		$attempt = static::get_article_fail_attempt( $article );
		if ( $attempt >= 2 ) {
			if ( $news instanceof News ) {
				$news->update_field( 'sync_status', 'fail' );
				$news->update_field( 'openai_error', $error->get_error_message() );
			}

			$article->update_field( 'openai_error', $error->get_error_message() );

			Logger::log( '3 fail attempt to sync with OpenAI #' . $article->get_id() . '. Removing from sync list.' );

			return false;
		}

		static::increase_article_fail_attempt( $article );

		// Push the item to bottom of queue to try later.
		OpenAiReCreateNewsTitle::init()->push_to_queue( $item );

		return false;
	}

	/**
	 * Can send more OpenAI request
	 *
	 * @return bool
	 */
	public function can_send_more_openai_request(): bool {
		$sleep_end = (int) get_option( 'sync_openai_api_sleep_end' );

		return NewsCompletion::can_send_more_request() && time() > $sleep_end;
	}
}

<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\Client;
use StackonetNewsGenerator\OpenAIApi\Models\InstagramAttemptLog;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * GetImportantNewsForTweet class
 */
class OpenAiSyncTwitterFields extends BackgroundProcessBase {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	public static $instance = null;

	/**
	 * Action
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'find_important_news_for_tweet';

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Perform task
	 *
	 * @param  array|mixed  $item  The data to be used for background process.
	 *
	 * @return false|mixed
	 */
	protected function task( $item ) {
		if ( ! static::can_send_more_openai_request() ) {
			return $item;
		}

		$field = isset( $item['field'] ) ? sanitize_text_field( $item['field'] ) : '';
		if ( 'important_news_for_tweet' === $field ) {
			return $this->find_important_news_for_tweet( $item );
		} elseif ( in_array( $field, array( 'tweet', 'send_to_sites' ), true ) ) {
			return $this->sync_field( $item );
		}

		return false;
	}

	public function find_important_news_for_tweet( $item ) {
		if ( $this->is_item_running( 1234567890, 'find_important_news_for_tweet' ) ) {
			InstagramAttemptLog::error(
				'Another background task is running to find important news for tweet.',
				InstagramAttemptLog::LOG_FOR_TWITTER
			);

			return false;
		}
		$this->set_item_running( 1234567890, 'find_important_news_for_tweet' );

		$news_ids   = isset( $item['ids'] ) && is_array( $item['ids'] ) ? $item['ids'] : array();
		$news_items = NewsStore::find_by_ids( $news_ids );
		$results    = Client::find_important_news_for_tweet( $news_items );
		if ( is_wp_error( $results ) ) {
			return $item;
		}

		return false;
	}

	private function sync_field( $item ) {
		$news_id = isset( $item['news_id'] ) ? intval( $item['news_id'] ) : 0;
		$attempt = isset( $item['attempt'] ) ? intval( $item['attempt'] ) : 0;
		$field   = isset( $item['field'] ) ? sanitize_text_field( $item['field'] ) : 'tweet';

		if ( $this->is_item_running( $news_id, $field ) ) {
			return false;
		}
		$this->set_item_running( $news_id, $field );

		$news = NewsStore::find_by_id( $news_id );
		if ( ! $news instanceof News ) {
			return false;
		}

		if ( 'tweet' === $field ) {
			NewsCompletion::generate_tweet( $news );
		}
		if ( 'send_to_sites' === $field ) {
			if ( ! empty( $news->get_tweet() ) ) {
				$news->send_to_sites( true );
				InstagramAttemptLog::success(
					sprintf(
						'News #%s is being sent to remote sites.',
						$news->get_id()
					),
					array( $news->get_id() ),
					InstagramAttemptLog::LOG_FOR_TWITTER
				);
			} elseif ( $attempt < 3 ) {
				$item['attempt'] = $attempt + 1;
				InstagramAttemptLog::error(
					sprintf(
						'%s fail attempt to send news #%s to remote sites.',
						$item['attempt'],
						$news->get_id()
					),
					InstagramAttemptLog::LOG_FOR_TWITTER
				);

				return $item;
			}
		}

		return false;
	}

	/**
	 * Add to queue
	 *
	 * @param  News  $news
	 *
	 * @return void
	 */
	public static function add_to_queue( News $news ) {
		$fields = array( 'tweet', 'send_to_sites' );
		foreach ( $fields as $field ) {
			static::init()->push_to_queue(
				array(
					'news_id' => $news->get_id(),
					'field'   => $field,
				)
			);
		}
	}

	public static function find_important_news( array $ids ) {
		$last_run = (int) get_transient( 'important_news_for_twitter_last_run' );
		if ( ( $last_run + ( MINUTE_IN_SECONDS * 15 ) ) > time() ) {
			return;
		}
		static::init()->push_to_queue(
			array(
				'ids'   => $ids,
				'field' => 'important_news_for_tweet',
			)
		);

		InstagramAttemptLog::success(
			'A background task is running to find important news for twitter.',
			$ids,
			InstagramAttemptLog::LOG_FOR_TWITTER
		);
	}
}

<?php

namespace StackonetNewsGenerator\BackgroundProcess;

use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\Models\InstagramAttemptLog;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * OpenAiSyncInstagramFields class
 */
class OpenAiSyncInstagramFields extends BackgroundProcessBase {

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
	protected $action = 'sync_openai_instagram_fields';

	protected $admin_notice_heading = 'A background task is running to complete syncing for {{total_items}} news with OpenAI api.';

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
	 * @param  array  $item  Lists of data to process.
	 *
	 * @return array|false
	 */
	protected function task( $item ) {
		$news_id = isset( $item['news_id'] ) ? intval( $item['news_id'] ) : 0;
		$attempt = isset( $item['attempt'] ) ? intval( $item['attempt'] ) : 0;
		$field   = isset( $item['field'] ) ? sanitize_text_field( $item['field'] ) : 'instagram_fields';

		if ( ! $this->can_send_more_openai_request() ) {
			return $item;
		}

		if ( $this->is_item_running( $news_id, $field ) ) {
			return false;
		}
		$this->set_item_running( $news_id, $field );

		$news = NewsStore::find_by_id( $news_id );
		if ( ! $news instanceof News ) {
			return false;
		}

		if ( 'instagram_heading' === $field ) {
			NewsCompletion::generate_instagram_heading( $news );
		}
		if ( 'instagram_body' === $field ) {
			NewsCompletion::generate_instagram_body( $news );
		}
		if ( 'instagram_hashtag' === $field ) {
			NewsCompletion::generate_instagram_hashtag( $news );
		}
		if ( 'instagram_subheading' === $field ) {
			NewsCompletion::generate_instagram_subheading( $news );
		}
		if ( 'linkedin_text' === $field ) {
			NewsCompletion::generate_linkedin_text( $news );
		}
		if ( 'send_to_sites' === $field ) {
			if ( $news->has_instagram_content() ) {
				$news->send_to_sites( true );
				InstagramAttemptLog::success(
					sprintf(
						'News #%s is being sent to remote sites.',
						$news->get_id()
					),
					array( $news->get_id() )
				);
				if ( ! $news->get_linkedin_text() ) {
					InstagramAttemptLog::error(
						array(
							'message'    => sprintf(
								'News #%s need to resend as linkedin text is not there.',
								$news->get_id()
							),
							'suggestion' => array( $news->get_id() ),
							'log_for'    => InstagramAttemptLog::LOG_FOR_LINKEDIN,
						)
					);

					return $item;
				} else {
					InstagramAttemptLog::success(
						sprintf(
							'News #%s is being sent to remote sites.',
							$news->get_id()
						),
						array( $news->get_id() ),
						InstagramAttemptLog::LOG_FOR_LINKEDIN
					);
				}
			} elseif ( $attempt < 3 ) {
				$item['attempt'] = $attempt + 1;
				InstagramAttemptLog::error(
					sprintf(
						'%s fail attempt to send news #%s to remote sites.',
						$item['attempt'],
						$news->get_id()
					)
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
		$fields = array(
			'instagram_heading',
			'instagram_subheading',
			'instagram_body',
			'instagram_hashtag',
			'linkedin_text',
			'send_to_sites',
		);
		foreach ( $fields as $field ) {
			static::init()->push_to_queue(
				array(
					'news_id' => $news->get_id(),
					'field'   => $field,
				)
			);
		}
	}
}

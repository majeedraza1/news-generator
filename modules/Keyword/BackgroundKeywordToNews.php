<?php

namespace StackonetNewsGenerator\Modules\Keyword;

use StackonetNewsGenerator\BackgroundProcess\BackgroundProcessWithUiHelper;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNews;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\Modules\Keyword\Models\Keyword;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * BackgroundKeywordToNews
 */
class BackgroundKeywordToNews extends BackgroundProcessWithUiHelper {
	private static $instance;

	/**
	 * Action
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'keyword_to_news';

	/**
	 * Admin notice heading
	 *
	 * @var string
	 */
	protected $admin_notice_heading = 'A background task is running to generate {{total_items}} news from keywords.';

	/**
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function task( $item ) {
		$id      = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
		$keyword = Keyword::find_single( $id );
		if ( ! $keyword instanceof Keyword ) {
			return false;
		}
		$response = OpenAiClient::generate_news( $keyword );
		if ( ! is_string( $response ) ) {
			return false;
		}
		$response       = OpenAiClient::sanitize_response( $response );
		$openai_news_id = static::create_news( $response );
		if ( is_numeric( $openai_news_id ) ) {
			$keyword->set_prop( 'news_id', $openai_news_id );
			$keyword->update();
		}

		return false;
	}

	/**
	 * Create news
	 *
	 * @param  array  $data  The data.
	 *
	 * @return int|\WP_Error
	 */
	public static function create_news( array $data ) {
		$title_words_count = str_word_count( $data['title'] );
		if ( $title_words_count < 3 ) {
			return new \WP_Error(
				'news_title_length_error',
				'News title is too short. Add least 3 words required.'
			);
		}
		$body_words_count = str_word_count( $data['body'] );
		if ( $body_words_count < 100 ) {
			return new \WP_Error(
				'news_content_length_error',
				sprintf(
					'News content is too short (%s words). Add least 100 words required. Recommender words 300 or more.',
					$body_words_count
				)
			);
		}

		$article_store = new ArticleStore();

		$slug       = sanitize_title_with_dashes( $data['title'], '', 'save' );
		$article_id = $article_store->create(
			array(
				'title'             => $data['title'],
				'slug'              => mb_substr( $slug, 0, 250 ),
				'body'              => $data['body'],
				'title_words_count' => $title_words_count,
				'body_words_count'  => $body_words_count,
				'image_id'          => 0,
				'news_datetime'     => current_time( 'mysql', true ),
				'primary_category'  => '',
			)
		);

		$data = array(
			'source_id'        => $article_id,
			'title'            => $data['title'],
			'body'             => $data['body'],
			'meta'             => $data['meta'],
			'sync_status'      => 'in-progress',
			'sync_setting_id'  => '',
			'live_news'        => 0,
			'image_id'         => 0,
			'created_via'      => 'manual',
			'primary_category' => '',
		);
		$id   = ( new NewsStore() )->create( $data );
		if ( $id ) {
			$article_store->update(
				array(
					'id'             => $article_id,
					'openai_news_id' => $id,
				)
			);

			OpenAiReCreateNews::init()->push_to_queue( array( 'news_id' => $article_id ) );
		}

		return $id;
	}

	public static function sync() {
		$query = Keyword::get_query_builder();
		$query->where( 'news_id', 0 );
		$query->order_by( 'id', 'ASC' );
		$query->limit( Setting::get_item_per_sync() );

		$items = $query->get();
		$self  = self::init();
		foreach ( $items as $item ) {
			$self->push_to_queue( array( 'id' => intval( $item['id'] ) ) );
		}
		$self->save()->dispatch();
	}
}

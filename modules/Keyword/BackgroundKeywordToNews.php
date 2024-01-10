<?php

namespace StackonetNewsGenerator\Modules\Keyword;

use StackonetNewsGenerator\BackgroundProcess\BackgroundProcessWithUiHelper;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsTitle;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\Modules\Keyword\Models\Keyword;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\Supports\Utils;

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

	/**
	 * @param  array  $item
	 *
	 * @return false|array
	 */
	protected function task( $item ) {
		$id      = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
		$keyword = Keyword::find_single( $id );
		if ( ! $keyword instanceof Keyword ) {
			return false;
		}
		if ( ! $keyword->has_body() ) {
			$news_body = OpenAiClient::generate_news_body( $keyword );
			if ( ! is_string( $news_body ) ) {
				return false;
			}
			$body = OpenAiClient::sanitize_openai_response( $news_body, true );
			if ( Utils::str_word_count_utf8( $body ) > 100 ) {
				$keyword->set_prop( 'body', $body );
				$keyword->update();
			}

			return $item;
		}
		if ( ! $keyword->has_title() ) {
			// Generate title && push to openAI queue.
			$news_title = OpenAiClient::generate_news_title( $keyword );
			if ( ! is_string( $news_title ) ) {
				return false;
			}
			$news_title = OpenAiClient::sanitize_openai_response( $news_title, false );
			if ( Utils::str_word_count_utf8( $news_title ) > 3 ) {
				$keyword->set_prop( 'title', $news_title );
				$keyword->update();
			}

			return $item;
		}
		$openai_news_id = static::create_news( $keyword );
		if ( is_numeric( $openai_news_id ) ) {
			$keyword->set_prop( 'news_id', $openai_news_id );
			$keyword->update();
		}

		return false;
	}

	/**
	 * Create news
	 *
	 * @param  Keyword  $keyword  The data.
	 *
	 * @return int|\WP_Error
	 */
	public static function create_news( Keyword $keyword ) {
		$title_words_count = Utils::str_word_count_utf8( $keyword->get_title() );
		if ( $title_words_count < 3 ) {
			return new \WP_Error(
				'news_title_length_error',
				'News title is too short. Add least 3 words required.'
			);
		}
		$body_words_count = Utils::str_word_count_utf8( $keyword->get_body() );
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

		$slug       = sanitize_title_with_dashes( $keyword->get_title(), '', 'save' );
		$article_id = $article_store->create(
			array(
				'title'             => $keyword->get_title(),
				'slug'              => mb_substr( $slug, 0, 250 ),
				'body'              => $keyword->get_body(),
				'title_words_count' => $title_words_count,
				'body_words_count'  => $body_words_count,
				'image_id'          => 0,
				'news_datetime'     => current_time( 'mysql', true ),
				'primary_category'  => '',
			)
		);

		$data = array(
			'source_id'        => $article_id,
			'title'            => $keyword->get_title(),
			'body'             => $keyword->get_body(),
			'sync_status'      => 'in-progress',
			'sync_setting_id'  => $keyword->get_id(),
			'live_news'        => 0,
			'image_id'         => 0,
			'created_via'      => 'keyword',
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

			OpenAiReCreateNewsTitle::init()->push_to_queue( array( 'news_id' => $article_id ) );
		}

		return $id;
	}

	/**
	 * Add keyword to sync
	 *
	 * @return void
	 */
	public static function sync() {
		$items = static::get_next_keywords_to_sync();
		$self  = self::init();
		foreach ( $items as $item ) {
			$self->push_to_queue( array( 'id' => $item->get_id() ) );
		}
		$self->save()->dispatch();
	}

	/**
	 * Get next keywords to sync
	 *
	 * @return Keyword[]
	 */
	public static function get_next_keywords_to_sync(): array {
		$query = Keyword::get_query_builder();
		$query->where( 'news_id', 0 );
		$query->order_by( 'id', 'ASC' );
		$query->limit( Setting::get_item_per_sync() );

		$items    = $query->get();
		$keywords = array();
		foreach ( $items as $item ) {
			$keywords[] = new Keyword( $item );
		}

		return $keywords;
	}
}

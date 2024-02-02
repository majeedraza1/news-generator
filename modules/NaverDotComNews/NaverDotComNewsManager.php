<?php

namespace StackonetNewsGenerator\Modules\NaverDotComNews;

use StackonetNewsGenerator\OpenAIApi\Client;
use StackonetNewsGenerator\OpenAIApi\Models\InterestingNews;
use StackonetNewsGenerator\Supports\Utils;

/**
 * NaverDotComNewsManager class
 */
class NaverDotComNewsManager {
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

			add_action( 'wp_ajax_stackonet_news_crawl', array( self::$instance, 'news_crawl' ) );
		}

		return self::$instance;
	}

	/**
	 * Doing some news crawl test
	 *
	 * @return void
	 */
	public function news_crawl() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'Sorry. This link only for developer to do some testing.',
					'stackonet-news-generator'
				)
			);
		}

		$interesting = InterestingNews::find_single( 2 );
		$instruction = $interesting->get_openai_api_instruction();
		$response    = ( new Client() )->_chat_completions( $instruction );

		if ( $response instanceof \WP_Error ) {
			preg_match( '/resulted in (?P<token>\d+) tokens/', $response->get_error_message(), $matches );
			$total_words = Utils::str_word_count_utf8( $instruction );
			$token       = ! empty( $matches['token'] ) ? intval( $matches['token'] ) : 0;
			if ( $token > 0 ) {
				set_transient( 'words_to_token_multiplier', round( $token / $total_words, 1 ) );
			}
		}
		var_dump(
			array(
				'strlen'             => strlen( 'Sayful' ),
				'mb_strlen'          => mb_strlen( 'Sayful' ),
				'strlen2'            => strlen( 'আমি তোমাকে ভালোবাসি' ),
				'mb_strlen2'         => mb_strlen( 'আমি তোমাকে ভালোবাসি' ),
				'Total Characters'   => mb_strlen( $instruction, 'UTF-8' ),
				'Total Words'        => Utils::str_word_count_utf8( $instruction ),
				'Approximate Tokens' => ceil( mb_strlen( $instruction, 'UTF-8' ) / 4 ),
				'Total Bytes'        => strlen( $instruction ),
				'OpenAi Response'    => $response->get_error_message(),
			)
		);
		wp_die();
	}
}

<?php

namespace TeraPixelNewsGenerator\Modules\Keyword;

use TeraPixelNewsGenerator\Modules\Keyword\Models\Keyword;
use TeraPixelNewsGenerator\OpenAIApi\ApiConnection\OpenAiRestClient;

/**
 * OpenAiClient is a child class of OpenAiRestClient.
 * It provides additional functionality specific to OpenAI.
 */
class OpenAiClient extends OpenAiRestClient {
	/**
	 * Generate news based on a keyword.
	 *
	 * @param  Keyword  $keyword  The keyword object used to generate news.
	 *
	 * @return string|\WP_Error The generated news or an error if it occurs.
	 */
	public static function generate_news( Keyword $keyword ) {
		$cache_key = sprintf( 'keyword_%s', $keyword->get_id() );

		// Check if it is available on transient cache.
		$result = get_transient( $cache_key );
		if ( ! empty( $result ) ) {
			return $result;
		}

		if ( $keyword->has_instruction() ) {
			$prompt = $keyword->get_instruction();
		} else {
			$prompt = Setting::get_global_instruction();
		}
		$prompt = str_replace( '{{keyword}}', $keyword->get_keyword(), $prompt );

		$result = ( new static() )->completions(
			$prompt,
			array(
				'percentage'  => 70,
				'group'       => 'keyword',
				'source_type' => 'keyyword',
				'source_id'   => $keyword->get_id(),
			)
		);
		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		}

		return $result;
	}

	public static function sanitize_response( $result ) {
		$data = array(
			'title'            => '',
			'meta_description' => '',
			'content'          => '',
		);
		if ( is_string( $result ) ) {
			preg_match( '/\[Title:\](.*?)\[Meta Description:\]/s', $result, $matches );
			$data['title'] = sanitize_text_field( trim( $matches[1] ) );

			preg_match( '/\[Meta Description:\](.*?)\[Content:\]/s', $result, $matches );
			$data['meta_description'] = sanitize_text_field( trim( $matches[1] ) );

			preg_match( '/\[Content:\](.*)/s', $result, $matches );
			$content = sanitize_textarea_field( trim( $matches[1] ) );
			preg_replace( '/\[Word Count: \d+\]/', '', $content );
			$data['content'] = $content;

		}

		return $data;
	}
}

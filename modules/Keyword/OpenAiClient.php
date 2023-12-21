<?php

namespace StackonetNewsGenerator\Modules\Keyword;

use StackonetNewsGenerator\Modules\Keyword\Models\Keyword;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\OpenAiRestClient;
use WP_Error;

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
	 * @return string|WP_Error The generated news or an error if it occurs.
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

	/**
	 * Sanitize response
	 *
	 * @param  string|WP_Error  $result  The data to be sanitized.
	 *
	 * @return string[]
	 */
	public static function sanitize_response( $result ): array {
		$data = array(
			'title' => '',
			'meta'  => '',
			'body'  => '',
		);
		if ( is_string( $result ) ) {
			preg_match( '/\[Title:\](.*?)\[Meta Description:\]/s', $result, $matches );
			if ( $matches ) {
				$data['title'] = static::sanitize_openai_response( $matches[1] );
			}

			preg_match( '/\[Meta Description:\](.*?)\[Content:\]/s', $result, $matches );
			if ( $matches ) {
				$data['meta'] = static::sanitize_openai_response( $matches[1] );
			}

			preg_match( '/\[Content:\](.*)/s', $result, $matches );
			if ( $matches ) {
				$data['body'] = static::sanitize_openai_response( $matches[1], true );
			} else {
				$data['body'] = static::sanitize_openai_response( $result );
			}
		}

		return $data;
	}
}

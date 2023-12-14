<?php

namespace TeraPixelNewsGenerator\Modules\TweetToArticle;

/**
 * Settings class
 */
class Settings {

	/**
	 * Get instruction for important tweets
	 *
	 * @return string
	 */
	public static function get_instruction_for_important_tweets(): string {
		$default = '{{list_of_tweets}}' . PHP_EOL;
		$default .= PHP_EOL;
		$default .= 'Based on the above tweets, could you select three tweets that the audience would find interesting?' . PHP_EOL;
		$default .= 'Please reply with only the tweets enclosed in square brackets. We don\'t need any descriptions. Thank you!';

		$instruction = get_option( '_openai_instruction_for_important_tweets' );

		return ! empty( $instruction ) ? $instruction : $default;
	}

	/**
	 * Set instruction
	 *
	 * @param string $instruction The value.
	 *
	 * @return string
	 */
	public static function set_instruction_for_important_tweets( string $instruction ): string {
		$instruction = sanitize_textarea_field( $instruction );
		update_option( '_openai_instruction_for_important_tweets', $instruction, false );

		return $instruction;
	}

	/**
	 * Get instruction for tweet to article
	 *
	 * @return string
	 */
	public static function get_instruction_for_tweet_to_article(): string {
		$default = '{{tweet}}' . PHP_EOL;
		$default .= 'by {{user:name}}, {{user:designation}}' . PHP_EOL;
		$default .= PHP_EOL;
		$default .= 'Based on the above tweet, can you write a news article with title and body?' . PHP_EOL;

		$default .= 'title should be around 50-60 characters. ';
		$default .= 'It should clearly convey the main news point without complex language or jargon. ';
		$default .= 'It should be engaging yet easy to comprehend. ';
		$default .= PHP_EOL;
		$default .= PHP_EOL;

		$default .= 'body should be between 300 words to 1000 words. ';
		$default .= 'Ensure the article is SEO-friendly and written in a conversational tone that appeals to human readers. ';
		$default .= 'It should be engaging yet easy to comprehend. ';
		$default .= PHP_EOL;
		$default .= PHP_EOL;

		$default .= 'Please reply with only title and body. Do not include tags, meta descriptions, or any indication that the article is auto-generated from OpenAI. Thank you!';

		$instruction = get_option( '_openai_instruction_for_tweet_to_article' );

		return ! empty( $instruction ) ? $instruction : $default;
	}

	/**
	 * Set instruction
	 *
	 * @param string $instruction The value.
	 *
	 * @return string
	 */
	public static function set_instruction_for_tweet_to_article( string $instruction ): string {
		$instruction = sanitize_textarea_field( $instruction );
		update_option( '_openai_instruction_for_tweet_to_article', $instruction, false );

		return $instruction;
	}

	/**
	 * Get supported languages
	 *
	 * @return int
	 */
	public static function get_sync_interval(): int {
		$interval = (int) get_option( '_twitter_sync_interval', 60 );

		return max( 1, $interval );
	}

	/**
	 * Get supported languages
	 *
	 * @param int $interval Sync interval.
	 *
	 * @return int
	 */
	public static function set_sync_interval( int $interval ): int {
		$interval = min( max( 15, $interval ), 360 );
		update_option( '_twitter_sync_interval', $interval );

		return $interval;
	}

	/**
	 * Get supported languages
	 *
	 * @return array
	 */
	public static function get_supported_languages(): array {
		$languages = get_option( '_twitter_supported_languages', [ 'en' ] );

		return is_array( $languages ) ? $languages : [ 'en' ];
	}

	/**
	 * Get supported languages
	 *
	 * @param mixed $languages Array of supported languages.
	 *
	 * @return array
	 */
	public static function set_supported_languages( $languages ): array {
		$valid_languages = array_keys( static::twitter_supported_languages() );
		$lan             = [ 'en' ];
		if ( is_array( $languages ) && count( $languages ) ) {
			foreach ( $languages as $language ) {
				if ( in_array( $language, $valid_languages, true ) ) {
					$lan[] = $language;
				}
			}
		}
		$sanitized_languages = array_values( array_unique( $lan ) );

		update_option( '_twitter_supported_languages', $sanitized_languages );

		return $sanitized_languages;
	}

	/**
	 * Should make batch based on username
	 *
	 * @return bool
	 */
	public static function is_username_batch_type(): bool {
		return 'username' === static::get_batch_type();
	}

	/**
	 * Get batch type
	 *
	 * @return string
	 */
	public static function get_batch_type(): string {
		$option = get_option( '_twitter_batch_type', 'all-users' );

		return in_array( $option, array_keys( static::get_batch_types() ), true ) ? $option : 'all-users';
	}

	/**
	 * Set batch type
	 *
	 * @param mixed $value Set batch type.
	 *
	 * @return string
	 */
	public static function set_batch_type( $value ): string {
		$value = in_array( $value, array_keys( static::get_batch_types() ), true ) ? $value : 'all-users';
		update_option( '_twitter_batch_type', $value );

		return $value;
	}

	/**
	 * Get batch types for select field
	 *
	 * @return array[]
	 */
	public static function get_batch_types(): array {
		return [
			'all-users' => 'All Users into single batch',
			'username'  => 'One batch for each user',
		];
	}

	/**
	 * Available language
	 *
	 * @return string[]
	 */
	public static function twitter_supported_languages(): array {
		return [
			'en' => 'English',
			'ar' => 'Arabic',
			'bn' => 'Bengali',
			'cs' => 'Czech',
			'da' => 'Danish',
			'de' => 'German',
			'el' => 'Greek',
			'es' => 'Spanish',
			'fa' => 'Persian',
			'fi' => 'Finnish',
			'fr' => 'French',
			'he' => 'Hebrew',
			'hi' => 'Hindi',
			'hu' => 'Hungarian',
			'id' => 'Indonesian',
			'it' => 'Italian',
			'ja' => 'Japanese',
			'ko' => 'Korean',
			'nl' => 'Dutch',
			'no' => 'Norwegian',
			'pl' => 'Polish',
			'pt' => 'Portuguese',
			'ro' => 'Romanian',
			'ru' => 'Russian',
			'sv' => 'Swedish',
			'th' => 'Thai',
			'tr' => 'Turkish',
			'uk' => 'Ukrainian',
			'ur' => 'Urdu',
			'vi' => 'Vietnamese',
		];
	}
}

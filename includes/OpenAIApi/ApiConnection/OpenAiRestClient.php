<?php

namespace StackonetNewsGenerator\OpenAIApi\ApiConnection;

use Stackonet\WP\Framework\Supports\RestClient;
use StackonetNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use StackonetNewsGenerator\OpenAIApi\Models\BlackListWords;
use StackonetNewsGenerator\OpenAIApi\Setting;
use StackonetNewsGenerator\Supports\Utils;
use WP_Error;

/**
 * OpenAiRestClient class
 */
class OpenAiRestClient extends RestClient {

	/**
	 * Default model
	 */
	const DEFAULT_MODEL = 'gpt-3.5-turbo-0125';

	/**
	 * Supported models
	 */
	const MODELS = array( 'gpt-3.5-turbo', 'gpt-3.5-turbo-0125' );

	/**
	 * Maximum allowed token
	 */
	const GPT_35_TURBO_MAX_TOKEN = 4096;

	/**
	 * Max requests per minute
	 * for Free trial users, RPM 3
	 * for Pay-as-you-go users (first 48 hours), RPM 60
	 * for Pay-as-you-go users (after 48 hours), RPM 3500
	 */
	const GPT_35_TURBO_MAX_RPM = 3500;

	/**
	 * Max requests per day
	 * for Free trial users, RPD 200
	 * for Pay-as-you-go users (first 48 hours), RPD 200
	 * for Pay-as-you-go users (after 48 hours), RPD 5,040,000 (3500 * 60 * 24)
	 */
	const GPT_35_TURBO_MAX_RPD = 5040000;

	/**
	 * Max tokens per minute
	 * for Free trial users, TPM 40,000
	 * for Pay-as-you-go users (first 48 hours), TPM 60,000
	 * for Pay-as-you-go users (after 48 hours), TPM 90,000
	 */
	const GPT_35_TURBO_MAX_TPM = 90000;

	/**
	 * Get openAI chat model
	 *
	 * @return string
	 */
	public static function get_model(): string {
		$model = get_option( 'openai_chat_model' );
		if ( ! empty( $model ) && in_array( $model, static::MODELS, true ) ) {
			return $model;
		}

		return static::DEFAULT_MODEL;
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		$setting = Setting::get_api_setting();
		$this->add_auth_header( $setting['api_key'], 'Bearer' );
		$this->add_headers( 'OpenAI-Organization', $setting['organization'] );
		$this->add_headers( 'Content-Type', 'application/json' );
		parent::__construct( 'https://api.openai.com/v1' );
	}

	/**
	 * Rate limit message for admin notice
	 *
	 * @return string
	 */
	public static function rate_limit_message(): string {
		return sprintf(
			'OpenAI rate limit status (per minute). Request sent: %s of %s; Token used: %s of %s',
			self::get_rpm_count(),
			static::GPT_35_TURBO_MAX_RPM,
			self::get_tpm_count(),
			static::GPT_35_TURBO_MAX_TPM
		);
	}

	/**
	 * Get request per minute count
	 *
	 * @return int
	 */
	public static function get_rpm_count(): int {
		$name = sprintf( '_chat_gpt_rpm_count_%s_%s', static::get_model(), gmdate( 'Ymd-Hi', time() ) );
		$rpm  = get_transient( $name );

		return is_numeric( $rpm ) ? (int) $rpm : 0;
	}

	/**
	 * Get token per minute count
	 *
	 * @return int
	 */
	public static function get_tpm_count(): int {
		$name = sprintf( '_chat_gpt_tpm_count_%s_%s', static::get_model(), gmdate( 'Ymd-Hi', time() ) );
		$rpm  = get_transient( $name );

		return is_numeric( $rpm ) ? (int) $rpm : 0;
	}

	/**
	 * Get daily status info
	 *
	 * @return string
	 */
	public static function daily_status_message(): string {
		$string = sprintf(
			'OpenAI daily status: %s request sent today; %s token used today; ',
			number_format( static::get_rpd_count() ),
			number_format( static::get_tpd_count() )
		);

		$last_request_times = static::get_last_few_requests_times();

		$string .= sprintf(
			'Request time (based on last 20 request): Average %ss, Max %ss, Min %ss; ',
			ceil( static::get_average_request_time() ),
			ceil( max( $last_request_times ) ),
			round( min( $last_request_times ), 1 )
		);

		return $string;
	}

	/**
	 * Get daily request count
	 *
	 * @return int
	 */
	public static function get_rpd_count(): int {
		$option_name   = sprintf( '_chat_gpt_daily_request_count_%s', gmdate( 'Ymd', time() ) );
		$request_count = get_option( $option_name );

		return is_numeric( $request_count ) ? (int) $request_count : 0;
	}

	/**
	 * Get daily request count
	 *
	 * @return int
	 */
	public static function get_tpd_count(): int {
		$option_name   = sprintf( '_chat_gpt_daily_token_count_%s', gmdate( 'Ymd', time() ) );
		$request_count = get_option( $option_name );

		return is_numeric( $request_count ) ? (int) $request_count : 0;
	}

	/**
	 * Get last ten request times
	 *
	 * @return array
	 */
	public static function get_last_few_requests_times(): array {
		$last_ten = get_option( 'openai_last_few_request_time', array() );

		return is_array( $last_ten ) && count( $last_ten ) ? $last_ten : array( 8.5, 18.75, .5 );
	}

	/**
	 * Get average request time
	 *
	 * @return float
	 */
	public static function get_average_request_time(): float {
		return (float) get_option( 'openai_average_request_time', 0 );
	}

	/**
	 * Can send more request
	 *
	 * @return bool
	 */
	public static function can_send_more_request(): bool {
		if (
			static::get_rpm_count() < ( static::GPT_35_TURBO_MAX_RPM * .9 ) &&
			static::get_tpm_count() < ( static::GPT_35_TURBO_MAX_TPM * .9 ) &&
			static::get_rpd_count() < ( static::GPT_35_TURBO_MAX_RPD * .98 )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Use completions api
	 *
	 * @param  string  $instruction  Instruction.
	 * @param  array  $extra_args  Extra arguments.
	 *
	 * @return string|WP_Error
	 */
	public function completions( string $instruction, array $extra_args = array() ) {
		$start_time = microtime( true );
		$args       = wp_parse_args(
			$extra_args,
			array(
				'percentage'      => 48,
				'group'           => 'undefined',
				'source_type'     => '',
				'source_id'       => 0,
				'check_blacklist' => true,
			)
		);
		if ( empty( $instruction ) ) {
			return new WP_Error( 'empty_instruction', 'Instruction cannot be empty.' );
		}

		$total_words = Utils::str_word_count_utf8( $instruction );
		$percentage  = intval( $args['percentage'] ) + 5;
		if ( ! static::is_valid_for_max_token( $total_words, $percentage ) ) {
			return new WP_Error(
				'exceeded_max_token',
				sprintf(
					'It is going to exceed max token. Total words: %s; Approximate token: %s; Percentage %s',
					$total_words,
					$total_words * 1.3,
					$percentage
				)
			);
		}

		$log_data = array(
			'belongs_to_group' => $args['group'],
			'model'            => static::get_model(),
			'instruction'      => $instruction,
			'source_type'      => $args['source_type'],
			'source_id'        => $args['source_id'],
		);

		$response = $this->_chat_completions( $instruction );

		static::increase_request_count();

		if ( is_wp_error( $response ) ) {
			$log_data['response_type'] = 'error';
			$log_data['total_time']    = microtime( true ) - $start_time;
			$rest_error                = $response->get_error_data( 'rest_error' );
			if ( empty( $rest_error ) ) {
				$rest_error = $this->debug_info['remote_response'];
			}
			$log_data['api_response'] = $rest_error;

			ApiResponseLog::create( $log_data );

			return $response;
		}

		$total_tokens = isset( $response['usage']['total_tokens'] ) ? intval( $response['usage']['total_tokens'] ) : 0;
		if ( $total_tokens ) {
			static::increase_token_count( $total_tokens );
			$log_data['total_tokens'] = $total_tokens;
		}

		$result           = static::filter_api_response( $response, $args['check_blacklist'], $extra_args );
		$time_to_complete = microtime( true ) - $start_time;
		static::update_average_request_time( $time_to_complete );

		$log_data['response_type'] = 'success';
		$log_data['total_time']    = $time_to_complete;
		$log_data['api_response']  = $response;
		ApiResponseLog::create( $log_data );

		return $result;
	}

	/**
	 * Filter api response
	 *
	 * @param  mixed  $response  Raw response.
	 * @param  bool  $check_blacklist  If it needs to check blacklist words/phrase.
	 *
	 * @return string|WP_Error
	 */
	public static function filter_api_response( $response, bool $check_blacklist = true, array $extra_args = array() ) {
		if ( ! ( is_array( $response ) && isset( $response['choices'][0]['message']['content'] ) ) ) {
			return new WP_Error( 'empty_response_from_api', 'Empty response from api.' );
		}

		$result = wp_filter_post_kses( trim( $response['choices'][0]['message']['content'] ) );

		if ( false !== strpos( $result, 'characters)' ) && false !== strpos( $result, '"' ) ) {
			preg_match( '/"(.*?)"/', $result, $matches );
			$result = isset( $matches[1] ) ? stripslashes( $matches[1] ) : $result;
		}

		$result = stripslashes( $result );
		$result = str_replace( '"', '', $result );
		if ( $check_blacklist ) {
			$result = BlackListWords::remove_blacklist_phrase( $result, $extra_args );
		}

		return $result;
	}

	/**
	 * Is it valid for maximum token
	 *
	 * @param  int  $total_words  Total number of words.
	 * @param  int  $percentage  Acceptable percentage.
	 *
	 * @return bool
	 */
	public static function is_valid_for_max_token( int $total_words, int $percentage = 48 ): bool {
		$percentage = min( 100, max( 1, $percentage ) ) / 100;
		/**
		 * Approximate value
		 * 1 word = 1.33 tokens as (100 tokens / 75 words)
		 * 1 token ~= 4 chars in English
		 * 1 token ~= ¾ words
		 * 100 tokens ~= 75 words
		 */
		$words_to_token_multiplier = get_transient( 'words_to_token_multiplier' );
		if ( ! is_numeric( $words_to_token_multiplier ) ) {
			$words_to_token_multiplier = 1.33;
		}
		$words_to_token = ceil( $total_words * max( 1.33, $words_to_token_multiplier ) );

		/**
		 * Maximum allowed tokens are shared between prompt and completion
		 * Limit prompt token withing half of maximum allowed token
		 */
		$max_prompt_token = self::GPT_35_TURBO_MAX_TOKEN * $percentage;

		return $words_to_token < $max_prompt_token;
	}

	/**
	 * Increase daily request and request per minute count
	 *
	 * @return void
	 */
	public static function increase_request_count() {
		$current_value = static::get_rpm_count();
		$name          = sprintf( '_chat_gpt_rpm_count_%s_%s', static::get_model(), gmdate( 'Ymd-Hi', time() ) );
		set_transient( $name, ( $current_value + 1 ), MINUTE_IN_SECONDS * 5 );

		$current_value = static::get_rpd_count();
		$daily_name    = sprintf( '_chat_gpt_daily_request_count_%s', gmdate( 'Ymd', time() ) );
		update_option( $daily_name, ( $current_value + 1 ), false );
	}

	/**
	 * Increase daily token and token per minute count
	 *
	 * @param  int  $token_used  Total token used.
	 *
	 * @return void
	 */
	public static function increase_token_count( int $token_used ) {
		$current_value = static::get_tpm_count();
		$name          = sprintf( '_chat_gpt_tpm_count_%s_%s', static::get_model(), gmdate( 'Ymd-Hi', time() ) );
		set_transient( $name, ( $current_value + $token_used ), MINUTE_IN_SECONDS * 5 );

		$daily_token = static::get_tpd_count();
		$option_name = sprintf( '_chat_gpt_daily_token_count_%s', gmdate( 'Ymd', time() ) );
		update_option( $option_name, ( $daily_token + $token_used ) );
	}

	/**
	 * Update average request time
	 *
	 * @param  float  $new_time_in_seconds  New time in seconds.
	 *
	 * @return void
	 */
	public function update_average_request_time( float $new_time_in_seconds ) {
		$last_ten = static::get_last_few_requests_times();
		if ( count( $last_ten ) > 19 ) {
			array_shift( $last_ten );
		}
		$last_ten[] = round( $new_time_in_seconds, 2 );
		update_option( 'openai_last_few_request_time', $last_ten, true );

		$average_time = array_sum( $last_ten ) / count( $last_ten );
		update_option( 'openai_average_request_time', $average_time, true );
	}

	/**
	 * Internal helper function to sanitize a string from user input or from the db
	 *
	 * @param  string|mixed  $string  String to sanitize.
	 * @param  bool  $keep_newlines  Optional. Whether to keep newlines. Default: false.
	 *
	 * @return string Sanitized string.
	 */
	public static function sanitize_openai_response( $string, bool $keep_newlines = false ): string {
		if ( $keep_newlines ) {
			$string = sanitize_textarea_field( $string );
		} else {
			$string = sanitize_text_field( $string );
		}

		$string = preg_replace( '/\[Word Count: \d+\]/', '', $string );
		$string = str_replace( array( '[', ']' ), '', $string );

		return trim( $string );
	}

	/**
	 * @param  string  $instruction
	 *
	 * @return array|WP_Error
	 */
	public function _chat_completions( string $instruction ) {
		$response = $this->post(
			'chat/completions',
			wp_json_encode(
				array(
					'model'    => static::get_model(),
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => $instruction,
						),
					),
				),
				\JSON_UNESCAPED_UNICODE
			)
		);
		if ( is_wp_error( $response ) ) {
			$error_data = $response->get_error_data();
			$error      = $error_data['error'] ?? array();
			if ( 'context_length_exceeded' === ( $error['code'] ?? '' ) ) {
				return new WP_Error(
					( $error['code'] ?? '' ),
					$error['message'] ?? '',
					$response->get_error_data( 'debug_info' )
				);
			}
		}

		return $response;
	}
}

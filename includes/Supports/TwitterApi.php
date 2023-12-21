<?php

namespace StackonetNewsGenerator\Supports;

use Abraham\TwitterOAuth\TwitterOAuth;
use Exception;
use Stackonet\WP\Framework\Supports\RestClient;
use WP_Error;

/**
 * TwitterApi class
 */
class TwitterApi extends RestClient {
	const MAX_TWEET_CHAR_LENGTH = 280;

	/**
	 * @var TwitterOAuth
	 */
	protected static $connection;

	/**
	 * Api base url
	 *
	 * @var string
	 */
	protected $api_base_url = 'https://api.twitter.com';

	/**
	 * Sanitize tweet
	 *
	 * @param string $tweet The tweet string.
	 *
	 * @return string
	 */
	public static function sanitize_tweet( string $tweet ): string {
		$tweet_text = sanitize_text_field( $tweet );
		if ( strlen( $tweet_text ) > self::MAX_TWEET_CHAR_LENGTH ) {
			$tweet_text = substr( $tweet_text, 0, self::MAX_TWEET_CHAR_LENGTH - 3 );
			$pieces     = explode( ' ', $tweet_text );
			array_pop( $pieces ); // Remove last word as it may be broken.
			$tweet_text = implode( ' ', $pieces ) . '...';
		}

		return str_replace( '&amp;', '&', $tweet_text );
	}

	/**
	 * Get latest tweets by username.
	 *
	 * @param string $username Twitter username.
	 * @param array $args Additional arguments.
	 *
	 * @return array|WP_Error
	 */
	public static function get_user_tweets( string $username, array $args = [] ) {
		$user = static::get_user( $username );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$args           = wp_parse_args(
			$args,
			[
				'max_results'  => 100,
				'tweet.fields' => 'id,text,lang,created_at',
				'exclude'      => 'retweets,replies',
			]
		);
		$endpoint       = "users/{$user['id']}/tweets";
		$transient_name = sprintf( '%s/%s', $endpoint, md5( wp_json_encode( $args ) ) );
		$tweets         = get_transient( $transient_name );
		if ( false === $tweets ) {
			$tweets = static::get_connection()->get( $endpoint, $args );
			set_transient( $transient_name, $tweets, HOUR_IN_SECONDS );
		}

		return $tweets;
	}

	/**
	 * Get user tweets
	 *
	 * @param string $username Twitter user id.
	 *
	 * @return array|WP_Error
	 */
	public static function get_user( string $username ) {
		try {
			$endpoint = "/2/users/by/username/$username";
			$user     = get_transient( $endpoint );
			if ( false === $user ) {
				$user = ( new static() )->get( $endpoint );
				if ( is_array( $user ) && isset( $user['data'] ) ) {
					set_transient( $endpoint, $user['data'], MONTH_IN_SECONDS );

					return $user['data'];
				}
			}

			return $user;
		} catch ( Exception $exception ) {
			return new WP_Error( 'unexpected_error', $exception->getMessage() );
		}
	}

	public static function get_connection() {
		if ( ! static::$connection instanceof TwitterOAuth ) {
			static::$connection = new TwitterOAuth(
				static::get_setting( 'consumer_key' ),
				static::get_setting( 'consumer_secret' ),
				static::get_setting( 'access_token' ),
				static::get_setting( 'access_token_secret' )
			);
			static::$connection->setApiVersion( 2 );
			static::$connection->setDecodeJsonAsArray( true );
		}

		return static::$connection;
	}

	/**
	 * Get setting
	 *
	 * @param string $key The setting key.
	 * @param mixed $default The default value.
	 *
	 * @return false|mixed
	 * @throws Exception Throw exception if constant not found.
	 */
	public static function get_setting( string $key, $default = false ) {
		$settings = static::get_settings();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Get settings
	 *
	 * @return array The settings.
	 * @throws Exception Throw exception if constant not found.
	 */
	public static function get_settings(): array {
		if ( defined( 'TWITTER_AUTH_SETTINGS' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$settings = unserialize( TWITTER_AUTH_SETTINGS );

			return array_merge( static::defaults(), $settings );
		}

		throw new Exception( 'Settings are not available. Define TWITTER_AUTH_SETTINGS constant in wp-config.php file.' );
	}

	/**
	 * Get default settings
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return [
			'consumer_key'        => '',
			'consumer_secret'     => '',
			'access_token'        => '',
			'access_token_secret' => '',
		];
	}

	/**
	 * Get tweet id
	 *
	 * @param string $tweet_id Tweet id.
	 *
	 * @return array|WP_Error
	 */
	public static function get_tweet( string $tweet_id ) {
		return ( new static() )->get( "/2/tweets/$tweet_id" );
	}


	/**
	 * Performs an HTTP request and returns its response.
	 *
	 * @param string $method Request method. Support GET, POST, PUT, DELETE.
	 * @param string $endpoint The rest endpoint.
	 * @param null|string|array $request_body Request body or additional parameters for GET method.
	 *
	 * @return array|WP_Error The response array or a WP_Error on failure.
	 */
	public function request( string $method = 'GET', string $endpoint = '', $request_body = null ) {
		$url = $this->get_endpoint_url( $method, $endpoint, $request_body );
		$this->add_headers( 'Content-Type', 'application/json' );
		try {
			$this->add_headers(
				'Authorization',
				$this->get_authorization_header( $url, $method, is_array( $request_body ) ? $request_body : [] )
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'authorization_error', $e->getMessage() );
		}
		$args            = $this->get_arguments( $method, $request_body );
		$remote_response = wp_remote_request( $url, $args );

		$this->debug_info = [
			'request_url'     => $url,
			'request_args'    => $args,
			'remote_response' => $remote_response,
		];

		return $this->filter_remote_response( $url, $args, $remote_response );
	}


	/**
	 * Build authorization header for HTTP/HTTPS request
	 *
	 * @param string $request_url Twitter endpoint to send the request to
	 * @param string $request_method HTTP request method.
	 * @param array $query_args Additional arguments for GET/DELETE request.
	 *
	 * @return string
	 * @throws Exception
	 */
	private function get_authorization_header(
		string $request_url,
		string $request_method,
		array $query_args = []
	): string {
		$header = 'OAuth ';

		$oauth_params = [];
		foreach ( $this->get_oauth_credentials( $request_url, $request_method, $query_args ) as $key => $value ) {
			$oauth_params[] = "$key=\"" . rawurlencode( $value ) . '"';
		}

		$header .= implode( ', ', $oauth_params );

		return $header;
	}


	/**
	 * Build, generate and include the OAuth signature to the OAuth credentials
	 *
	 * @param string $request_url Twitter endpoint to send the request to
	 * @param string $request_method HTTP request method.
	 * @param array $query_args Additional arguments for GET/DELETE request.
	 *
	 * @return array
	 * @throws Exception
	 */
	private function get_oauth_credentials(
		string $request_url,
		string $request_method,
		array $query_args = []
	): array {
		$oauth_credentials = [
			'oauth_consumer_key'     => static::get_setting( 'consumer_key' ),
			'oauth_token'            => static::get_setting( 'access_token' ),
			'oauth_nonce'            => uniqid(),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => time(),
			'oauth_version'          => '1.0',
		];

		if ( 'GET' === strtoupper( $request_method ) ) {
			foreach ( $query_args as $key => $value ) {
				// $oauth_credentials[ $key ] = $value;
			}
		}

		// convert the oauth credentials (including the GET QUERY if it is used) array to query string.
		$signature = $this->build_signature_base_string( $request_url, $request_method, $oauth_credentials );

		$oauth_credentials['oauth_signature'] = $this->generate_oauth_signature( $signature );

		return $oauth_credentials;
	}


	/**
	 * Create a signature base string from list of arguments
	 *
	 * @param string $request_url request url or endpoint
	 * @param string $method HTTP verb
	 * @param array $oauth_params Twitter's OAuth parameters
	 *
	 * @return string
	 */
	private function build_signature_base_string( string $request_url, string $method, array $oauth_params ): string {
		ksort( $oauth_params );

		// save the parameters as key value pair bounded together with '&'
		$string_params = [];
		foreach ( $oauth_params as $key => $value ) {
			// convert oauth parameters to key-value pair
			$string_params[] = "$key=$value";
		}

		return "$method&" . rawurlencode( $request_url ) . '&' . rawurlencode( implode( '&', $string_params ) );
	}

	/**
	 * @param string $data The data string for making hash
	 *
	 * @return string
	 * @throws Exception
	 */
	private function generate_oauth_signature( string $data ): string {

		// encode consumer and token secret keys and subsequently combine them using & to a query component
		$hash_hmac_key = rawurlencode( static::get_setting( 'consumer_secret' ) ) . '&' .
		                 rawurlencode( static::get_setting( 'access_token_secret' ) );

		return base64_encode( hash_hmac( 'sha1', $data, $hash_hmac_key, true ) );
	}
}

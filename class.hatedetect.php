<?php

class HateDetect {
	const API_HOST = 'hateapi';
	const API_PORT = 80;

    private static $initiated = false;

	private static $auto_allow = true;

	private static bool $notify_user = true;

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}
	}

	private static function init_hooks() {
		self::$initiated = true;

		add_action( 'wp_insert_comment', array( 'HateDetect', 'check_comment' ), 10, 2 );
	}

	public static function check_comment( int $id, WP_Comment $comment ) {
		self::log( 'Checking comment: ' . strval( $id ) . ' comment: ' . $comment->comment_content . PHP_EOL );
		$request_args = array(
			'comment_content' => $comment->comment_content,
			'comment_id'      => $comment->comment_ID,
			'comment_author'  => $comment->comment_author
		);

		$response = self::http_post( $request_args, 'ishate' );

		if ( is_array( $response[1] ) ) {
			if ( array_key_exists( 'ishate', $response[1] ) ) {
				$ishate = $response[1]['ishate'];
				self::log( 'Comment_id: ' . $id . " other_id: " . $comment->comment_ID . "  ishate: " . $ishate . PHP_EOL );
				if ( is_bool( $ishate ) ) {
					if ( $ishate ) {
						update_comment_meta( $comment->comment_ID, 'hatedetect_result', 'true');
						// comment contains hate - discard
						wp_set_comment_status( $comment->comment_ID, 'trash' );
						// TODO send mail to user
                        if ( self::$notify_user ) {
                            $mail_message = "The owner of ".strval(get_the_permalink($comment->comment_post_ID))." would like to inform you that your comment was blocked due to hate detection. \n You have tired to send the following comment content: \n".strval($comment->comment_content)."\n to the post: \n".strval(get_post_permalink($comment->comment_post_ID))."";
                            $headers = array('Content-Type: text/html; charset=UTF-8');
                            wp_mail(strval($comment->comment_author_email), "Post rejected", $mail_message, $headers );
                        }
                        return new WP_Error( 'hatedetect_hateful_comment_api', __( 'Comment discarded.', 'Hatedetect' ) );
					} else {
						if ( self::$auto_allow ) {
							wp_set_comment_status( $comment->comment_ID, 'approve' );
						}
					}
				} else {
					# TODO invalid response -> postpone validation
					wp_set_comment_status( $comment->comment_ID, 'hold' );
				}
			} else {
				# TODO no response -> postpone validation
				# Otherwise, do not approve automatically comment, because it may be spam
				wp_set_comment_status( $comment->comment_ID, 'hold' );
			}
		} else {
			# TODO empty response -> postpone validation
			# Otherwise, do not approve automatically comment, because it may be spam
			wp_set_comment_status( $comment->comment_ID, 'hold' );
		}
	}

	public static function plugin_activation() {
		self::log( 'Initialized plugin' . PHP_EOL );
	}

	public static function plugin_deactivation() {
		self::log( 'Deinitialized plugin' . PHP_EOL );
	}

	private static function get_api_key() {
		return '';
	}

	/**
	 * Make a POST request to the HateDetect API.
	 *
	 * @param array $args The arguments of the request (Array key => value pairs).
	 * @param string $path The path for the request.
	 * @param string|null $ip The specific IP address to hit.
	 *
	 * @return array A two-member array consisting of the headers and the response body, both empty in the case of a failure.
	 */
	private static function http_post( array $args, string $path, string $ip = null ) {

		$hatedetect_ua = sprintf( 'WordPress/%s | HateDetect/%s', $GLOBALS['wp_version'], constant( 'HATEDETECT_VERSION' ) );
		$hatedetect_ua = apply_filters( 'hatedetect_ua', $hatedetect_ua ); # Optional in future

		$api_key = self::get_api_key();
		$host    = self::API_HOST;
		$port    = self::API_PORT;

		if ( ! empty( $api_key ) ) {
			$host = $api_key . '.' . $host;
		}

		$http_host = $host;
		// use a specific IP if provided
		// needed by Hatedetect_Admin::check_server_connectivity()
		if ( $ip && long2ip( ip2long( $ip ) ) ) {
			$http_host = $ip;
		}

		$http_args = array(
			'body'        => json_encode( $args ),
			'headers'     => array(
				'Content-Type' => 'application/json; charset=utf-8',
				'Host'         => $host,
				'User-Agent'   => $hatedetect_ua,
			),
			'timeout'     => 15,
			'method'      => 'POST',
			'data_format' => 'body'
		);

		$hatedetect_url = $http_hatedetect_url = "http://{$http_host}:{$port}/{$path}";

		/**
		 * Try SSL first; if that fails, try without it and don't try it again for a while.
		 */

		$ssl = $ssl_failed = false;

		// Check if SSL requests were disabled fewer than X hours ago.
		$ssl_disabled = get_option( 'hatedetect_ssl_disabled' );

		if ( $ssl_disabled && $ssl_disabled < ( time() - 60 * 60 * 24 ) ) { // 24 hours
			$ssl_disabled = false;
			delete_option( 'hatedetect_ssl_disabled' );
		} else if ( $ssl_disabled ) {
			do_action( 'hatedetect_ssl_disabled' );
		}

		if ( ! $ssl_disabled && ( $ssl = wp_http_supports( array( 'ssl' ) ) ) ) {
			$hatedetect_url = set_url_scheme( $hatedetect_url, 'https' );

			do_action( 'hatedetect_https_request_pre' );
		}

		$response = wp_remote_post( $hatedetect_url, $http_args );

		if ( $ssl && is_wp_error( $response ) ) {
			do_action( 'hatedetect_https_request_failure', $response );

			// Intermittent connection problems may cause the first HTTPS
			// request to fail and subsequent HTTP requests to succeed randomly.
			// Retry the HTTPS request once before disabling SSL for a time.
			$response = wp_remote_post( $hatedetect_url, $http_args );

			if ( is_wp_error( $response ) ) {
				$ssl_failed = true;

				do_action( 'hatedetect_https_request_failure', $response );

				do_action( 'hatedetect_http_request_pre' );

				// Try the request again without SSL.
				$response = wp_remote_post( $http_hatedetect_url, $http_args );
			}
		}

		if ( is_wp_error( $response ) ) {
			do_action( 'hatedetect_request_failure', $response );

			return array( '', '' );
		}

		if ( $ssl_failed ) {
			// The request failed when using SSL but succeeded without it. Disable SSL for future requests.
			update_option( 'hatedetect_ssl_disabled', time() );

			do_action( 'hatedetect_https_disabled' );
		}

		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		return array( $response['headers'], $data );
	}


	public static function check_key_status( $key, $ip = null ) {
		$request_args =  array(
			'key' => $key,
			'blog' => get_option( 'home' )
		);
		return self::http_post( $request_args, 'verify-key' );
	}

	public static function verify_key( $key, $ip = null ) {
		// Shortcut for obviously invalid keys.
		if ( strlen( $key ) != 12 ) {
			return 'invalid';
		}

		$response = self::check_key_status( $key, $ip );

		if ( $response[1] != 'valid' && $response[1] != 'invalid' )
			return 'failed';

		return $response[1];
	}

	public static function deactivate_key( $key ) {
		$response = self::http_post(array( 'key' => $key, 'blog' => get_option( 'home' ) ) , 'deactivate' );

		if ( $response[1] != 'deactivated' )
			return 'failed';

		return $response[1];
	}

	/**
	 * Log debugging info to the error log.
	 *
	 * Enabled when WP_DEBUG_LOG is enabled (and WP_DEBUG, since according to
	 * core, "WP_DEBUG_DISPLAY and WP_DEBUG_LOG perform no function unless
	 * WP_DEBUG is true), but can be disabled via the hatedetect_debug_log filter.
	 *
	 * @param mixed $hatedetect_debug The data to log.
	 */
	public static function log( $hatedetect_debug ) {
		if ( apply_filters( 'hatedetect_debug_log', defined( 'WP_DEBUG' ) &&
		                                            WP_DEBUG ) ) {
			error_log( print_r( compact( 'hatedetect_debug' ), true ) );
		}
	}
}
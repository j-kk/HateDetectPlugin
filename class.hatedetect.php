<?php

class HateDetect {
    const API_HOST = 'modera-backend.endpoints.opcode-327611.cloud.goog';
    const API_PORT = 80;
	const MAX_DELAY_BEFORE_MODERATION_EMAIL = 86400; // One day in seconds

	private static bool $activated = false;

	/**
     * Plugin activation. Currently, empty stub.
	 *
	 */
	public static function plugin_activation() {
	}

	/**
     * Plugin deactivation, cleans plugin settings and removes cron jobs.
	 *
	 */
	public static function plugin_deactivation() {
		HateDetect::log( 'HateDetect deactivated' );
		delete_option( 'hatedetect_api_key' );
		delete_option( 'hatedetect_alert_code' );
		delete_option( 'hatedetect_alert_msg' );
		delete_option( 'hatedetect_key_status' );

		// Remove any scheduled cron jobs.
		$timestamp = wp_next_scheduled( 'hatedetect_schedule_cron_recheck', [ 'cron' ] );

		if ( $timestamp ) {
			HateDetect::log( 'HateDetect deactivated cron' );
			wp_unschedule_event( $timestamp, 'hatedetect_schedule_cron_recheck', [ 'cron' ] );
		}
		self::$activated = false;
	}

	/**
     * Initializes all plugin hooks.
	 *
	 */
	public static function init() {
		if ( ! self::$activated ) {
			self::$activated = true;
			HateDetect::log( 'HateDetect activated' );

			add_action( 'wp_insert_comment', [ 'HateDetect', 'check_comment' ], 10, 2 );
			add_action( 'comment_form_after', [ 'HateDetect', 'display_comment_form_privacy_notice' ] );
			add_filter( 'comment_text', [ 'HateDetect', 'display_after_posting_comment' ], 10, 2 );
			add_action( 'edit_comment', [ 'HateDetect', 'check_edited_comment' ], 10, 2 );

			add_action( 'hatedetect_schedule_cron_recheck', [ 'HateDetect', 'cron_recheck' ] );
			$timestamp = wp_next_scheduled( 'hatedetect_schedule_cron_recheck', [ 'cron' ] );

			if ( ! $timestamp ) {
				wp_schedule_event( current_time( 'timestamp' ), 'daily', 'hatedetect_schedule_cron_recheck', [ 'cron' ] );
				HateDetect::log( 'HateDetect activated cron' );
			}
		}
	}


	/**
     * If an option is enabled, displays a button after posting comment to check why comment was marked as hateful
	 * (only if marked).
	 *
	 * @param string $comment_text comment content
	 * @param WP_COMMENT|null $comment data
	 *
	 * @return string comment text.
	 */
	public static function display_after_posting_comment( string $comment_text, $comment ): string {
		if ( is_null( $comment ) ) {
			return $comment_text;
		}
		$id           = $comment->comment_ID;
		$comment_meta = get_comment_meta( $id );
        $form_name = "explain_form_".$id;
		if ( array_key_exists( 'hatedetect_result', $comment_meta ) && $comment_meta['hatedetect_result'][0] == 1 ) {
			if ( $comment->comment_approved == 1 ) {
				return $comment_text;
			} elseif ( get_option( 'hatedetect_show_comment_field_message', '0' ) === '1' && ! current_user_can( 'manage_options' ) ) {
				$message = __( 'Your comment was marked as a hateful by HateDetect plugin.', 'hatedetect' );
				echo wp_kses_data('<em> ' . $message . '</em>');
                $explain_button = ' <form  method="post"> 
                       <input type="submit" name='.$form_name.' value="Explain why the comment is hateful">
                       </form>';
                echo wp_kses_decode_entities($explain_button);
				if ( array_key_exists( $form_name, $_POST ) && $_POST[$form_name] && $_SERVER['REQUEST_METHOD'] == 'POST' ) {
                    HateDetect::explain_print( $comment );
				}
				return $comment_text;
			}
		}
		return $comment_text;
	}


    /**
     * Prints the explanation of the hate detection model reasoning.
     * Explanation is composed of four parts (detection of hateful parts, reasons,
     * potentially vulgar (or derogatory) words and the note).
     *
     * @param $wp_comment WP_Comment  comment marked as hateful
     */
    private static function explain_print( $wp_comment ) {
        $explanation = HateDetect::check_why_hate( $wp_comment->comment_ID, $wp_comment );
        if (is_string($explanation)){
            echo wp_kses_data('<em>' . __( 'The model explanation: ', 'hatedetect' ) . $explanation . ' </em> <br>');
        }
        elseif (is_array($explanation) && array_key_exists("Hatefull part", $explanation )
            && array_key_exists("Reasons", $explanation )
            && array_key_exists("Pottentialy vulgar (or derogatory) words", $explanation )
            && array_key_exists("Note", $explanation )  ) {

            $reason_text =  __("general hate", 'hatedetect');
            if (is_array($explanation['Reasons'])){
               if (array_key_exists('nbrs', $explanation['Reasons']) && array_key_exists('dtxfy', $explanation['Reasons'])) {
                $reason_text = strtolower(str_replace( array("<", ">", "_"), " ", implode(", ",$explanation['Reasons']['nbrs'])) ). ' '.  strtolower(str_replace( array("<", ">", "_"), " ",implode(" ",$explanation['Reasons']['dtxfy']))) ;
               }
               else{
                   $reason_text = strtolower(str_replace( array("<", ">", "_"), " ",implode(", ", $explanation['Reasons'])));
               }
            }
            $vulgar_words = '';
            if (is_array($explanation['Pottentialy vulgar (or derogatory) words'])){
                foreach($explanation['Pottentialy vulgar (or derogatory) words'] as $item){
                    if (is_array($item)){
                        $vulgar_words .= implode(", ", array_keys($item));
                    }
                }
            }
            if($vulgar_words == ''){
               $vulgar_words =  __("not detected", 'hatedetect');
            }
            $end = ' ';

            echo wp_kses_post('<em> <b> ' . __( 'The model detected following hateful parts of your comment: ', 'hatedetect' ) . '</b>'. $explanation["Hatefull part"] .
                ' <br> <b> ' . __( 'The model classified your comment as: ', 'hatedetect' ) . '</b>'. $reason_text .
                ' <br> <b> ' . __( 'The model detected following potentially vulgar or derogatory words: ', 'hatedetect' ) . '</b>'. $vulgar_words .
                ' <br>  <b> ' . __( 'Additional important note: ', 'hatedetect' ) . '</b>'. $explanation['Note'] . '</em>' .
                ' <br> ' . $end) ;

        }
    }


	/**
     * Checks comment for hate.
	 *
	 * @param int $id comment id
	 * @param WP_Comment $comment comment data
	 *
	 * @return bool True if operation was succeeded.
	 */
	public static function check_comment( int $id, WP_Comment $comment ): bool {
		HateDetect::log( 'Checking comment: ' . strval( $id ) . ' comment: ' . $comment->comment_content . PHP_EOL );

		$lang         = get_option( 'hatedetect_lang' );
		$request_args = [
			'text'     => $comment->comment_content,
			'language' => $lang,
		];

		$response = self::http_post( $request_args, 'predict' );

		return self::check_ishate_response( $id, $comment, $response );
	}

	/**
     * Checks comment for hate from the database.
	 *
	 * @param string $id comment id
	 *
	 * @return bool True if operation was succeeded.
	 */
	public static function check_db_comment( string $id ): bool {

		$retrieved_comment = get_comment( $id );

		if ( ! $retrieved_comment ) {
			return false;
		}
		$request_args = [
			'comment_content' => $retrieved_comment->comment_content,
			'comment_id'      => $retrieved_comment->comment_ID,
			'comment_author'  => $retrieved_comment->comment_author
		];

		$response = self::http_post( $request_args, 'predict' );

		$response_success = self::check_ishate_response( $id, $retrieved_comment, $response );
		if ( $response_success ) {
			delete_comment_meta( $id, 'hatedetect_error' );
		}

		return $response_success;
	}


	/**
     * Checks why comment has been flagged as hate.
	 *
	 * @param int $id comment id
	 * @param WP_Comment $comment commend data
	 *
	 * @return false|string Explanation or False if unable to check explanation.
	 */
	public static function check_why_hate( int $id, WP_Comment $comment ) {
		HateDetect::log( 'Checking why hate: ' . strval( $id ) . ' comment: ' . $comment->comment_content . PHP_EOL );
		if ( ! HateDetect_ApiKey::get_api_key() ) {
			return false;
		}
		$hate_explanation = get_comment_meta( $id, 'hatedetect_explanation' );
		if ( is_string( $hate_explanation ) ) {
			return $hate_explanation;
		}


		$lang         = get_option( 'hatedetect_lang' );
		$request_args = [
			'text'     => $comment->comment_content,
			'language' => $lang
		];

		$response = self::http_post( $request_args, 'explain' );
		if ( is_integer( $response[1] ) && 200 <= $response[1] && $response[1] < 300 ) {
			if ( is_array( $response[2] ) ) {
				if ( array_key_exists( 'explanation', $response[2] ) ) {
					$hate_explanation = $response[2]['explanation'];
					update_comment_meta( $id, 'hatedetect_explanation', $hate_explanation );

					return $hate_explanation;
				}
			}
		} else {
			update_option( 'hatedetect_key_status', 'Failed' );
		}

		return false;

	}


	/**
     * Reads response from the hatedetect api and updates comment status.
	 * Basing on the plugin settings it may notify user or moderator.
	 *
	 * @param string $id comment id
	 * @param WP_Comment $comment comment data
	 * @param array $response received from the hatedetect api
	 *
	 * @return bool True if message was correctly read, false if there was a problem with response or api key.
	 */
	private static function check_ishate_response( string $id, WP_Comment $comment, array $response ): bool {
		if ( is_integer( $response[1] ) && 200 <= $response[1] && $response[1] < 300 ) {
			if ( is_array( $response[2] ) ) {
				if ( array_key_exists( 'prediction', $response[2] ) ) {
					$ishate = $response[2]['prediction'];
					self::log( 'Comment_id: ' . $id . ' other_id: ' . $comment->comment_ID . '  prediction: ' . $ishate . PHP_EOL );
					if ( is_bool( $ishate ) ) {
						if ( $ishate ) {
							update_comment_meta( $comment->comment_ID, 'hatedetect_result', 1 );

							// comment contains hate - discard
							if ( self::auto_discard() ) {
								wp_set_comment_status( $comment->comment_ID, 'trash' );
							} else {
								wp_set_comment_status( $comment->comment_ID, 'hold' );
								if ( self::notify_moderator() ) {
									$last_moderation_email_timestamp = get_option( 'hatedetect_last_moderation_email' );
									HateDetect::log( 'Last moderation email: ' . $last_moderation_email_timestamp . ' Actual time: ' . time() );
									if ( $last_moderation_email_timestamp && ( $last_moderation_email_timestamp + self::MAX_DELAY_BEFORE_MODERATION_EMAIL >= time() ) ) {
										if ( self::get_comments_hate_moderator() == 1 ) {
											HateDetect::log( 'There has been an email sent in 24h, but queue has been cleared in the meantime. Comment id: ' . $id );
											wp_notify_moderator( $id );
											update_option( 'hatedetect_last_moderation_email', time() );
										}
									} else {
										HateDetect::log( 'No recent email sent. Sending now! Comment id: ' . $id );
										wp_notify_moderator( $id );
										update_option( 'hatedetect_last_moderation_email', time() );
									}
								}
							}
							if ( self::notify_user() ) {
								$hate_explanation = self::check_why_hate( $id, $comment );
								$mail_message     = __( 'The owner of ', 'hatedetect' ) .
								                    strval( get_the_permalink( $comment->comment_post_ID ) ) .
								                    __( " would like to inform you that your comment was blocked due to hate detection. \n You have tired to send the following comment content: \n", 'hatedetect' ) .
								                    strval( $comment->comment_content ) .
								                    "\n " .
								                    __( 'to the post:', 'hatedetect' ) .
								                    " \n" .
								                    strval( get_post_permalink( $comment->comment_post_ID ) );
								if ( $hate_explanation ) {
									$mail_message = $mail_message . "\n" . __( 'Reason: ', 'hatedetect' ) . $hate_explanation;
								}
								HateDetect::log( 'Hate explanation: ' . $hate_explanation . '   comment id: ' . $id );
								$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
								wp_mail( strval( $comment->comment_author_email ), __( 'Comment rejected', 'hatedetect' ), $mail_message, $headers );
							}


							delete_comment_meta( $comment->comment_ID, 'hatedetect_error' );

						} else {
							update_comment_meta( $comment->comment_ID, 'hatedetect_result', 0 );
							delete_comment_meta( $comment->comment_ID, 'hatedetect_error' );
							if ( self::auto_allow() ) {
								wp_set_comment_status( $comment->comment_ID, 'approve' );
							}

						}

						return true;
					} else {
						HateDetect::log( 'Comment_id: ' . $id . ' other_id: ' . $comment->comment_ID . ' IsHate is not a bool' );
						update_comment_meta( $comment->comment_ID, 'hatedetect_error', 1 );
						delete_comment_meta( $comment->comment_ID, 'hatedetect_result' );
						wp_set_comment_status( $comment->comment_ID, 'hold' );
					}
				} else {
					HateDetect::log( 'Comment_id: ' . $id . ' other_id: ' . $comment->comment_ID . ' No response field' );
					update_comment_meta( $comment->comment_ID, 'hatedetect_error', 1 );
					delete_comment_meta( $comment->comment_ID, 'hatedetect_result' );
					# Otherwise, do not approve automatically comment, because it may be hate
					wp_set_comment_status( $comment->comment_ID, 'hold' );
				}
			} else {
				HateDetect::log( 'Comment_id: ' . $id . ' other_id: ' . $comment->comment_ID . ' Did not connect' );
				update_comment_meta( $comment->comment_ID, 'hatedetect_error', 1 );
				delete_comment_meta( $comment->comment_ID, 'hatedetect_result' );
				# Otherwise, do not approve automatically comment, because it may be hate
				wp_set_comment_status( $comment->comment_ID, 'hold' );
			}
		} else {
			update_option( 'hatedetect_key_status', 'Failed' );
		}

		return false;
	}


	/**
     * Registered as action to launch after editing comment. Rechecks new comment text.
	 *
	 * @param int $comment_ID comment id.
	 * @param array $data wp comment data.
	 */
	public static function check_edited_comment( int $comment_ID, array $data ) {
		HateDetect::check_db_comment( $comment_ID );
	}

	/**
     * Performs a cron recheck. Looks for submitted, unapproved comments without hate detection result. Maximum 100
     * comments per operation, rest will be scheduled within short time.
	 *
	 * @param string $reason behind scheduling a recheck.
	 *
	 * @return void
	 */
	public static function cron_recheck( string $reason = 'unknown' ) {
		HateDetect::log( 'Performing cron_recheck, reason: ' . $reason );
		global $wpdb;
		$api_key = HateDetect_ApiKey::get_api_key();

		if ( ! $api_key ) {
			// since there is currently a problem with the key, reschedule a check for 6 hours hence
			return;
		}

		$comment_errors = $wpdb->get_col( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = 'hatedetect_error' LIMIT 100" );

		load_plugin_textdomain( 'hatedetect' );

		foreach ( (array) $comment_errors as $comment_id ) {
			// if the comment no longer exists, or is too old, remove the meta entry from the queue to avoid getting stuck
			$comment = get_comment( $comment_id );

			if (
				! $comment // Comment has been deleted
				|| strtotime( $comment->comment_date_gmt ) < strtotime( '-15 days' ) // Comment is too old.
				|| $comment->comment_approved !== '0' // Comment is no longer in the Pending queue
			) {
				delete_comment_meta( $comment_id, 'hatedetect_error' );
				continue;
			}
			HateDetect::log( 'Checking comment id: ' . $comment_id );
			add_comment_meta( $comment_id, 'hatedetect_rechecking', true );
			$check_status = self::check_db_comment( $comment_id, 'retry' );
			delete_comment_meta( $comment_id, 'hatedetect_rechecking' );
			if ( $check_status ) {
				# OK - do nothing
			} else {
				if ( ( intval( gmdate( 'U' ) ) - strtotime( $comment->comment_date_gmt ) ) < self::MAX_DELAY_BEFORE_MODERATION_EMAIL ) {
					wp_notify_moderator( $comment_id );
				}

				return;
			}

		}

		$remaining = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = 'hatedetect_error'" );
		if ( $remaining ) {
			self::manual_schedule_cron_recheck( 10 );
		}
	}

	/**
	 * Make a POST request to the HateDetect API.
	 *
	 * @param array $args The arguments of the request (Array key => value pairs).
	 * @param string $path The path for the request.
	 * @param string|null $ip The specific IP address to hit.
	 * @param bool $decode_response Should response be decoded into associate array (json format).
	 * @param string|null $api_key Should api key be overridden with other one. If null, use the default one
	 *
	 * @return array A three-member array consisting of the headers, response code and the response body, both empty
     *               in the case of a failure.
	 */
	public static function http_post( array $args, string $path, string $ip = null, bool $decode_response = true, string $api_key = null ): array {

		$hatedetect_ua = sprintf( 'WordPress/%s | HateDetect/%s', $GLOBALS['wp_version'], constant( 'HATEDETECT_VERSION' ) );
		$hatedetect_ua = apply_filters( 'hatedetect_ua', $hatedetect_ua ); # Optional in future

		$host = self::API_HOST;
		$port = self::API_PORT;

		if ( is_null( $api_key ) ) {
			$api_key = HateDetect_ApiKey::get_api_key();
			if ( $api_key == false ) {
				HateDetect::log('HTTP_POST cancelled, no api key provided');
				return [ '', '', '' ];
			}
		}

		$http_host = $host;
		// use a specific IP if provided
		// needed by Hatedetect_Admin::check_server_connectivity()
		if ( $ip && long2ip( ip2long( $ip ) ) ) {
			$http_host = $ip;
		}

		$http_args = [
			'body'        => json_encode( $args ),
			'headers'     => [
				'Content-Type' => 'application/json; charset=utf-8',
				'Host'         => $host,
				'User-Agent'   => $hatedetect_ua,
			],
			'timeout'     => 15,
			'method'      => 'POST',
			'data_format' => 'body'
		];


		$hatedetect_url = $http_hatedetect_url = "http://{$http_host}:{$port}/{$path}?key={$api_key}";

		$ssl = $ssl_failed = false;

		// Check if SSL requests were disabled fewer than X hours ago.
		$ssl_disabled = get_option( 'hatedetect_ssl_disabled' );

		if ( $ssl_disabled && $ssl_disabled < ( time() - 60 * 60 * 24 ) ) { // 24 hours
			$ssl_disabled = false;
			delete_option( 'hatedetect_ssl_disabled' );
		} elseif ( $ssl_disabled ) {
			do_action( 'hatedetect_ssl_disabled' );
		}

		if ( ! $ssl_disabled && ( $ssl = wp_http_supports( [ 'ssl' ] ) ) ) {
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
			HateDetect::log( 'HTTP Request failure, response: ' . json_encode($response) );
			update_option('hatedetect_connection', 'HTTP Request failure');

			return [ '', '', '' ];
		}

		if ( $ssl_failed ) {
			// The request failed when using SSL but succeeded without it. Disable SSL for future requests.
			update_option( 'hatedetect_ssl_disabled', time() );

			do_action( 'hatedetect_https_disabled' );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( $decode_response ) {
			$data = json_decode( $body, true );
		} else {
			$data = $body;
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$headers = $response['headers'];
		if (200 <= $code && $code < 300) {
			HateDetect::log( 'HTTP Request success, response: ' . json_encode($response) );
			delete_option( 'hatedetect_connection' );
		} else {
			update_option('hatedetect_connection', 'Got error code: '.$code);
			HateDetect::log( 'HTTP Request fail (error code: '.$code.' ), response: ' . json_encode($response) );

		}
		return [ $headers, $code, $data ];
	}


	/**
     * Manually schedule comment recheck. Ensures that job scheduling hasn't been duplicated.
	 *
	 * @param int|null $delay in seconds to start comment recheck.
	 */
	public static function manual_schedule_cron_recheck( int $delay = null ) {
		$future_check = wp_next_scheduled( 'hatedetect_schedule_cron_recheck', [ 'recheck' ] );
		HateDetect::log( 'future_check: ' . $future_check . ' actual_time: ' . time() . '  delay:  ' . $delay );
		if ( is_null( $delay ) ) {
			HateDetect::log( 'delay is null' );
			$delay = 1200;
		}
		$time = time() + $delay;
		if ( $future_check ) {
			if ( $future_check <= $time && $future_check > time() ) {
				return;
			} else {
				wp_clear_scheduled_hook( 'hatedetect_schedule_cron_recheck', [ 'recheck' ] );
			}
		}
		HateDetect::log( 'Cron recheck scheduled at: ' . $time . ' Current time: ' . time() );
		wp_schedule_single_event( $time, 'hatedetect_schedule_cron_recheck', [ 'recheck' ] );
	}

	/**
	 * Log debugging info to the error log.
	 *
	 * Enabled when WP_DEBUG_LOG is enabled (and WP_DEBUG, since according to
	 * core, "WP_DEBUG_DISPLAY and WP_DEBUG_LOG perform no function unless
	 * WP_DEBUG is true), but can be disabled via the hatedetect_debug_log filter.
	 *
	 * @param string $hatedetect_debug The data to log.
	 */
	public static function log( string $hatedetect_debug ) {
		if ( apply_filters( 'hatedetect_debug_log', defined( 'WP_DEBUG' ) &&
		                                            WP_DEBUG ) ) {
			error_log( print_r( compact( 'hatedetect_debug' ), true ) );
		}
	}

	/**
     * Display view.
	 *
	 * @param string $name of the view to display.
	 * @param array $args args to pass to the view.
	 */
	public static function view( string $name, array $args = [] ) {
		$args = apply_filters( 'hatedetect_view_arguments', $args, $name );

		foreach ( $args as $key => $val ) {
			$$key = $val;
		}

		load_plugin_textdomain( 'hatedetect' );

		$file = HATEDETECT__PLUGIN_DIR . 'views/' . $name . '.php';

		include( $file );
	}


	/**
	 * Controls the display of a privacy related notice underneath the comment form using
     * the `hatedetect_comment_form_privacy_notice` option and filter respectively.
	 * Default is top not display the notice, leaving the choice to site admins, or integrators.
	 */
	public static function display_comment_form_privacy_notice() {
		if ( 'display' !== apply_filters( 'hatedetect_comment_form_privacy_notice', get_option( 'hatedetect_comment_form_privacy_notice', 'hide' ) ) ) {
			return;
		}
		# TODO privacy policy
		echo wp_kses_post_deep(apply_filters(
			'hatedetect_comment_form_privacy_notice_markup',
			'<p class="hatedetect_comment_form_privacy_notice">' . sprintf(
				__( 'This site uses HateDetect to reduce hate. <a href="%s" target="_blank" rel="nofollow noopener">Learn how your comment data is processed</a>.', 'hatedetect' ),
				'https://codeagainsthate.eu'
			) . '</p>'
		));
	}


	/**
     * Checks either if automatic comment allow option is selected.
	 *
	 * @return bool option setting.
	 */
	public static function auto_allow(): bool {
		return ( get_option( 'hatedetect_auto_allow' ) === '1' );
	}

	/**
     * Checks either if automatic comment discard option is selected.
	 *
	 * @return bool option setting.
	 */
	public static function auto_discard(): bool {
		return ( get_option( 'hatedetect_auto_discard' ) === '1' );
	}

	/**
     * Checks either if notify user (email author) option is selected.
	 *
	 * @return bool option setting.
	 */
	public static function notify_user(): bool {
		return ( get_option( 'hatedetect_notify_user' ) === '1' );
	}

	/**
     * Checks either if notify moderator (email moderator) option is selected.
	 *
	 * @return bool option setting.
	 */
	public static function notify_moderator(): bool {
		return ( get_option( 'hatedetect_notify_moderator' ) === '1' );
	}

	/**
     * Retrieves plugin language (in which hate should be detected).
	 *
	 * @return string selected language.
	 */
	public static function get_language(): string {
		return get_option( 'hatedetect_lang' );
	}

	/**
     * Retrieves amount of hateful comments awaiting review.
	 *
	 * @return int amount of the comments.
	 */
	public static function get_comments_hate_moderator(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta} NATURAL JOIN {$wpdb->comments} WHERE meta_key='hatedetect_result' AND meta_value='1' AND comment_approved ='0'" );
	}
}
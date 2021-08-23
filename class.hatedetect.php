<?php

class HateDetect
{
    const API_HOST = 'hateapi';
    const API_PORT = 80;

    private static bool $initiated = false;

    private static bool $auto_discard = true;

    public static function init()
    {
        if (!self::$initiated) {
            self::init_hooks();
        }
    }

    private static function init_hooks()
    {
        self::$initiated = true;

        add_action('wp_insert_comment', array('HateDetect', 'check_comment'), 10, 2);
        add_action('comment_form_after', array('HateDetect', 'display_comment_form_privacy_notice'));
    }

    public static function auto_allow()
    {
        return (get_option('hatedetect_auto_allow') === '1');
    }

    public static function auto_discard()
    {
        return (get_option('hatedetect_auto_discard') === '1');
    }

    public static function check_comment(int $id, WP_Comment $comment)
    {
        self::log('Checking comment: ' . strval($id) . ' comment: ' . $comment->comment_content . PHP_EOL);
        if (!self::get_api_key()) {
            return $comment;
        }

        $request_args = array(
            'comment_content' => $comment->comment_content,
            'comment_id' => $comment->comment_ID,
            'comment_author' => $comment->comment_author
        );

        $response = self::http_post($request_args, 'ishate');

        self::check_ishate_response($id, (array) $comment, $response);
    }

    public static function plugin_activation()
    {
        self::log('Initialized plugin' . PHP_EOL);
    }

    public static function plugin_deactivation()
    {
        self::log('Deinitialized plugin' . PHP_EOL);
    }

    public static function get_api_key()
    {
        return apply_filters('hatedetect_get_api_key', defined('WPCOM_API_KEY') ? constant('WPCOM_API_KEY') : get_option('hatedetect_api_key'));
    }

    public static function predefined_api_key()
    {
        if (defined('WPCOM_API_KEY')) {
            return true;
        }

        return apply_filters('hatedetect_predefined_api_key', false);
    }

    private static function check_ishate_response ( $id, $comment, $response ) {

        if (is_array($response[1])) {
            if (array_key_exists('ishate', $response[1])) {
                $ishate = $response[1]['ishate'];
                self::log('Comment_id: ' . $id . " other_id: " . $comment['comment_ID'] . "  ishate: " . $ishate . PHP_EOL);
                if (is_bool($ishate)) {
                    if ($ishate) {
                        update_comment_meta($comment['comment_ID'], 'hatedetect_result', 1);

                        // comment contains hate - discard
                        if (self::auto_discard()) {
                            wp_set_comment_status($comment['comment_ID'], 'trash');
                        } else {
                            wp_set_comment_status($comment['comment_ID'], 'hold');
                        }

                        // TODO send mail to user
                        return true;
                    } else {
                        update_comment_meta($comment['comment_ID'], 'hatedetect_result', 0);
                        if (self::auto_allow()) {
                            wp_set_comment_status($comment['comment_ID'], 'approve');
                        }
                        return true;
                    }
                } else {
                    self::log('Comment_id: ' . $id . " other_id: " . $comment['comment_id'] . " IsHate is not a bool");
                    update_comment_meta($comment['comment_ID'], 'hatedetect_error', 1);
                    # TODO invalid response -> postpone validation
                    wp_set_comment_status($comment['comment_ID'], 'hold');
                }
            } else {
                self::log('Comment_id: ' . $id . " other_id: " . $comment['comment_id'] . " No response field");
                update_comment_meta($comment['comment_ID'], 'hatedetect_error', 1);
                # TODO no response -> postpone validation
                # Otherwise, do not approve automatically comment, because it may be spam
                wp_set_comment_status($comment['comment_ID'], 'hold');
            }
        } else {
            self::log('Comment_id: ' . $id . " other_id: " . $comment['comment_id'] . " Did not connect");
            update_comment_meta($comment['comment_ID'], 'hatedetect_error', 1);
            # TODO empty response -> postpone validation
            # Otherwise, do not approve automatically comment, because it may be spam
            wp_set_comment_status($comment['comment_id'], 'hold');
        }
        return false;
    }

    public static function check_db_comment( $id, $recheck_reason = 'recheck_queue' ) {
        global $wpdb;

        if ( ! self::get_api_key() ) {
            return new WP_Error( 'hatedetect-not-configured', __( 'HateDetect is not configured. Please enter an API key.', 'hatedetect' ) );
        }

        $retrieved_comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID = %d", $id ), ARRAY_A );

        if ( ! $retrieved_comment ) {
            return new WP_Error( 'invalid-comment-id', __( 'Comment not found.', 'hatedetect' ) );
        }
        $request_args = array(
            'comment_content' => $retrieved_comment['comment_content'],
            'comment_id' => $retrieved_comment['comment_ID'],
            'comment_author' => $retrieved_comment['comment_author']
        );

        $response = self::http_post($request_args, 'ishate');

        self::check_ishate_response($id, (array) $retrieved_comment, $response);

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
    public static function http_post(array $args, string $path, string $ip = null, bool $decode_response = true)
    {

        $hatedetect_ua = sprintf('WordPress/%s | HateDetect/%s', $GLOBALS['wp_version'], constant('HATEDETECT_VERSION'));
        $hatedetect_ua = apply_filters('hatedetect_ua', $hatedetect_ua); # Optional in future

        $host = self::API_HOST;
        $port = self::API_PORT;

        if (!empty($api_key)) {
            $args['api_key'] = self::get_api_key();
        }

        $http_host = $host;
        // use a specific IP if provided
        // needed by Hatedetect_Admin::check_server_connectivity()
        if ($ip && long2ip(ip2long($ip))) {
            $http_host = $ip;
        }

        $http_args = array(
            'body' => json_encode($args),
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Host' => $host,
                'User-Agent' => $hatedetect_ua,
            ),
            'timeout' => 15,
            'method' => 'POST',
            'data_format' => 'body'
        );

        $hatedetect_url = $http_hatedetect_url = "http://{$http_host}:{$port}/{$path}";

        self::log('REQUEST TO: ' . $hatedetect_url);

        /**
         * Try SSL first; if that fails, try without it and don't try it again for a while.
         */

        $ssl = $ssl_failed = false;

        // Check if SSL requests were disabled fewer than X hours ago.
        $ssl_disabled = get_option('hatedetect_ssl_disabled');

        if ($ssl_disabled && $ssl_disabled < (time() - 60 * 60 * 24)) { // 24 hours
            $ssl_disabled = false;
            delete_option('hatedetect_ssl_disabled');
        } else if ($ssl_disabled) {
            do_action('hatedetect_ssl_disabled');
        }

        if (!$ssl_disabled && ($ssl = wp_http_supports(array('ssl')))) {
            $hatedetect_url = set_url_scheme($hatedetect_url, 'https');

            do_action('hatedetect_https_request_pre');
        }

        $response = wp_remote_post($hatedetect_url, $http_args);

        if ($ssl && is_wp_error($response)) {
            do_action('hatedetect_https_request_failure', $response);

            // Intermittent connection problems may cause the first HTTPS
            // request to fail and subsequent HTTP requests to succeed randomly.
            // Retry the HTTPS request once before disabling SSL for a time.
            $response = wp_remote_post($hatedetect_url, $http_args);

            if (is_wp_error($response)) {
                $ssl_failed = true;

                do_action('hatedetect_https_request_failure', $response);

                do_action('hatedetect_http_request_pre');

                // Try the request again without SSL.
                $response = wp_remote_post($http_hatedetect_url, $http_args);
            }
        }

        if (is_wp_error($response)) {
            do_action('hatedetect_request_failure', $response);

            return array('', '');
        }

        if ($ssl_failed) {
            // The request failed when using SSL but succeeded without it. Disable SSL for future requests.
            update_option('hatedetect_ssl_disabled', time());

            do_action('hatedetect_https_disabled');
        }

        $body = wp_remote_retrieve_body($response);
        if ($decode_response) {
            $data = json_decode($body, true);
        } else {
            $data = $body;
        }

        return array($response['headers'], $data);
    }


    public static function check_key_status($key, $ip = null)
    {
        $request_args = array(
            'api_key' => $key,
            'blog' => get_option('home')
        );

        return self::http_post($request_args, 'verify-key');
    }

    public static function verify_key($key, $ip = null)
    {

        $response = self::check_key_status($key, $ip);

        if (!empty($response[1])) {
            if (array_key_exists("api_key", $response[1]) && array_key_exists("status", $response[1])) {
                if ($response[1]['status'] == "OK") {
                    return $response[1]['status'];
                }
            }
        }
        HateDetect::log("Failed to verify key: " . $response);
        return 'failed';

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
    public static function log($hatedetect_debug)
    {
        if (apply_filters('hatedetect_debug_log', defined('WP_DEBUG') &&
            WP_DEBUG)) {
            error_log(print_r(compact('hatedetect_debug'), true));
        }
    }


    public static function view($name, array $args = array())
    {
        $args = apply_filters('hatedetect_view_arguments', $args, $name);

        foreach ($args as $key => $val) {
            $$key = $val;
        }

        load_plugin_textdomain('akismet');

        $file = HATEDETECT__PLUGIN_DIR . 'views/' . $name . '.php';

        include($file);
    }

    # TODO
    // how many approved comments does this author have?
    public static function get_user_comments_approved($user_id, $comment_author_email, $comment_author, $comment_author_url)
    {
        global $wpdb;

        if (!empty($user_id)) {
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND hatedetect_result = 1", $user_id));
        }

        if (!empty($comment_author_email)) {
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_author_email = %s AND comment_author = %s AND comment_author_url = %s AND hatedetect_result = 1", $comment_author_email, $comment_author, $comment_author_url));
        }

        return 0;
    }


    /**
     * Controls the display of a privacy related notice underneath the comment form using the `hatedetect_comment_form_privacy_notice` option and filter respectively.
     * Default is top not display the notice, leaving the choice to site admins, or integrators.
     */
    public static function display_comment_form_privacy_notice()
    {
        if ('display' !== apply_filters('hatedetect_comment_form_privacy_notice', get_option('hatedetect_comment_form_privacy_notice', 'hide'))) {
            return;
        }
        # TODO privacy policy
        echo apply_filters(
            'hatedetect_comment_form_privacy_notice_markup',
            '<p class="hatedetect_comment_form_privacy_notice">' . sprintf(
                __('This site uses Hatedetect to reduce spam. <a href="%s" target="_blank" rel="nofollow noopener">Learn how your comment data is processed</a>.', 'hatedetect'),
                'https://hatedetect.com/privacy/'
            ) . '</p>'
        );
    }
}
<?php

class HateDetect
{
    const API_HOST = 'hateapi';
    const API_PORT = 80;
    const MAX_DELAY_BEFORE_MODERATION_EMAIL = 86400; // One day in seconds

    private static bool $activated = false;

    public static function plugin_activation()
    {
    }

    /**
     * Removes all connection options
     * @static
     */
    public static function plugin_deactivation()
    {
        HateDetect::log('HateDetect deactivated');
        delete_option('hatedetect_api_key');


        // Remove any scheduled cron jobs.
        $timestamp = wp_next_scheduled('hatedetect_schedule_cron_recheck', array('cron'));

        if ($timestamp) {
            HateDetect::log('HateDetect deactivated cron');
            wp_unschedule_event($timestamp, 'hatedetect_schedule_cron_recheck', array('cron'));
        }
        self::$activated = false;
    }

    public static function init()
    {
        if (!self::$activated) {
            self::$activated = true;
            HateDetect::log('HateDetect activated');

            add_action('wp_insert_comment', array('HateDetect', 'check_comment'), 10, 2);
            add_action('comment_form_after', array('HateDetect', 'display_comment_form_privacy_notice'));
            add_filter('comment_text', array('HateDetect', 'display_after_posting_comment'), 10, 2);

            add_action('hatedetect_schedule_cron_recheck', array('HateDetect', 'cron_recheck'));
            $timestamp = wp_next_scheduled('hatedetect_schedule_cron_recheck', array('cron'));

            if (!$timestamp) {
                wp_schedule_event(current_time('timestamp'), 'daily', 'hatedetect_schedule_cron_recheck', array('cron'));
                HateDetect::log('HateDetect activated cron');
            }
        }
    }


    public static function display_after_posting_comment(string $comment_text, $comment)
    {
        if (is_null($comment)) {
            return $comment_text;
        }
        $id = $comment->comment_ID;
        $comment_meta = get_comment_meta($id);
        if (array_key_exists('hatedetect_result', $comment_meta)) {
            if ($comment_meta['hatedetect_result'][0] == 1) {
                if ($comment->comment_approved == 1) {
                    return $comment_text;
                } else if (get_option('hatedetect_show_comment_field_message', 'show') === 'show') {
                    $message = "Your comment was marked as a hateful by HateDetect plugin.";
                    echo "<em> " . $message . "</em>";

                    function explain_print($wp_comment)
                    {
                        $explanation = HateDetect::check_why_hate($wp_comment->comment_ID, $wp_comment);
                        echo "<em> The model explanation: " . $explanation . " </em> <br> <br>";
                    }

                    echo ' <form  method="post">
                           <input type="submit" name="explain" value="Explain why the comment is hateful">
                           </form>';
                    if (array_key_exists('explain', $_POST) and $_POST['explain'] and $_SERVER['REQUEST_METHOD'] == "POST") {
                        explain_print($comment);
                    }
                    return $comment_text;
                }
            }
        }
        return $comment_text;
    }


    public static function auto_allow()
    {
        return (get_option('hatedetect_auto_allow') === '1');
    }

    public static function auto_discard()
    {
        return (get_option('hatedetect_auto_discard') === '1');
    }

    public static function notify_user()
    {
        return (get_option('hatedetect_notify_user') === '1');
    }

    public static function get_language()
    {
        return get_option('hatedetect_lang');
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

        self::check_ishate_response($id, $comment, $response);
    }

    public static function check_why_hate(int $id, WP_Comment $comment)
    {
        self::log('Checking why hate: ' . strval($id) . ' comment: ' . $comment->comment_content . PHP_EOL);
        if (!self::get_api_key()) {
            return false;
        }
        $hate_explanation = get_comment_meta($id, 'hatedetect_explanation');
        if (is_string($hate_explanation)) {
            return $hate_explanation;
        }


        $lang = get_option('hatedetect_lang');
        $request_args = array(
            'text' => $comment->comment_content,
            'language' => $lang
        );

        $response = self::http_post($request_args, 'explain');

        if (is_array($response[1])) {
            if (array_key_exists('explanation', $response[1])) {
                $hate_explanation = $response[1]['explanation'];
                update_comment_meta($id, 'hatedetect_explanation', $hate_explanation);
                return $hate_explanation;
            }
        }

        return false;

    }

    public static function get_api_key()
    {
        return get_option('hatedetect_api_key');
    }

    private static function check_ishate_response(string $id, WP_Comment $comment, $response)
    {

        if (is_array($response[1])) {
            if (array_key_exists('ishate', $response[1])) {
                $ishate = $response[1]['ishate'];
                self::log('Comment_id: ' . $id . " other_id: " . $comment->comment_ID . "  ishate: " . $ishate . PHP_EOL);
                if (is_bool($ishate)) {
                    if ($ishate) {
                        update_comment_meta($comment->comment_ID, 'hatedetect_result', 1);

                        // comment contains hate - discard
                        if (self::auto_discard()) {
                            wp_set_comment_status($comment->comment_ID, 'trash');
                        } else {
                            wp_set_comment_status($comment->comment_ID, 'hold');
                        }
                        if (self::notify_user()) {
                            $hate_explanation = self::check_why_hate($id, $comment);
                            $mail_message = "The owner of " . strval(get_the_permalink($comment->comment_post_ID)) . " would like to inform you that your comment was blocked due to hate detection. \n You have tired to send the following comment content: \n" . strval($comment->comment_content) . "\n to the post: \n" . strval(get_post_permalink($comment->comment_post_ID));
                            if ( $hate_explanation ) {
                                $mail_message = $mail_message . "\n Reason: " . $hate_explanation;
                            }
                            self::log('Hate explanation: '.$hate_explanation.'   comment id: '.$id);
                            $headers = array('Content-Type: text/html; charset=UTF-8');
                            wp_mail(strval($comment->comment_author_email), "Comment rejected", $mail_message, $headers);
                        }
                        return true;
                    } else {
                        update_comment_meta($comment->comment_ID, 'hatedetect_result', 0);
                        if (self::auto_allow()) {
                            wp_set_comment_status($comment->comment_ID, 'approve');
                        }
                        return true;
                    }
                } else {
                    self::log('Comment_id: ' . $id . " other_id: " . $comment->comment_ID . " IsHate is not a bool");
                    update_comment_meta($comment->comment_ID, 'hatedetect_error', 1);
                    wp_set_comment_status($comment->comment_ID, 'hold');
                }
            } else {
                self::log('Comment_id: ' . $id . " other_id: " . $comment->comment_ID . " No response field");
                update_comment_meta($comment->comment_ID, 'hatedetect_error', 1);
                # Otherwise, do not approve automatically comment, because it may be hate
                wp_set_comment_status($comment->comment_ID, 'hold');
            }
        } else {
            self::log('Comment_id: ' . $id . " other_id: " . $comment->comment_ID . " Did not connect");
            update_comment_meta($comment->comment_ID, 'hatedetect_error', 1);
            # Otherwise, do not approve automatically comment, because it may be hate
            wp_set_comment_status($comment->comment_ID, 'hold');
        }
        return false;
    }

    public static function check_db_comment($id)
    {
        if (!self::get_api_key()) {
            return new WP_Error('hatedetect-not-configured', __('HateDetect is not configured. Please enter an API key.', 'hatedetect'));
        }

        $retrieved_comment = get_comment($id);

        if (!$retrieved_comment) {
            return new WP_Error('invalid-comment-id', __('Comment not found.', 'hatedetect'));
        }
        $request_args = array(
            'comment_content' => $retrieved_comment->comment_content,
            'comment_id' => $retrieved_comment->comment_ID,
            'comment_author' => $retrieved_comment->comment_author
        );

        $response = self::http_post($request_args, 'ishate');

        $response_success = self::check_ishate_response($id, $retrieved_comment, $response);
        if ($response_success) {
            delete_comment_meta($id, 'hatedetect_error');
        }
        return $response_success;
    }


    public static function cron_recheck(string $reason = 'unknown')
    {
        self::log('Performing cron_recheck, reason: ' . $reason);
        global $wpdb;
        $api_key = self::get_api_key();

        $status = self::verify_key($api_key);
        if (get_option('hatedetect_alert_code') || $status == 'invalid') {
            // since there is currently a problem with the key, reschedule a check for 6 hours hence
            return false;
        }

        $comment_errors = $wpdb->get_col("SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = 'hatedetect_error'	LIMIT 100");

        load_plugin_textdomain('hatedetect');

        foreach ((array)$comment_errors as $comment_id) {
            // if the comment no longer exists, or is too old, remove the meta entry from the queue to avoid getting stuck
            $comment = get_comment($comment_id);

            if (
                !$comment // Comment has been deleted
                || strtotime($comment->comment_date_gmt) < strtotime("-15 days") // Comment is too old.
                || $comment->comment_approved !== "0" // Comment is no longer in the Pending queue
            ) {
                delete_comment_meta($comment_id, 'hatedetect_error');
                continue;
            }
            self::log('Checking comment id: ' . $comment_id);
            add_comment_meta($comment_id, 'hatedetect_rechecking', true);
            $check_status = self::check_db_comment($comment_id, 'retry');
            delete_comment_meta($comment_id, 'hatedetect_rechecking');
            if ($check_status) {
                # OK - do nothing
            } else {
                if ((intval(gmdate('U')) - strtotime($comment->comment_date_gmt)) < self::MAX_DELAY_BEFORE_MODERATION_EMAIL) {
                    wp_notify_moderator($comment_id);
                }
                return;
            }

        }

        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = 'hatedetect_error'");
        if ($remaining) {
            self::manual_schedule_cron_recheck(10);
        }
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

    public static function manual_schedule_cron_recheck(int $delay = null)
    {
        $future_check = wp_next_scheduled('hatedetect_schedule_cron_recheck', array('recheck'));
        self::log('future_check: ' . $future_check . " actual_time: " . time() . '  delay:  ' . $delay);
        if (is_null($delay)) {
            self::log('delay is null');
            $delay = 1200;
        }
        $time = time() + $delay;
        if ($future_check) {
            if ($future_check <= $time && $future_check > time()) {
                return;
            } else {
                wp_clear_scheduled_hook('hatedetect_schedule_cron_recheck', array('recheck'));
            }
        }
        self::log('Cron recheck scheduled at: ' . $time . ' Current time: ' . time());
        wp_schedule_single_event($time, 'hatedetect_schedule_cron_recheck', array('recheck'));
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
        HateDetect::log("Failed to verify key: " . wp_json_encode($response));
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

    public static function get_user_comments_approved()
    {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key='hatedetect_result' AND meta_value=1");
    }

    public static function get_user_comments_rejected()
    {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key='hatedetect_result' AND meta_value=0");
    }

    public static function get_user_comments_queued()
    {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = 'hatedetect_error'");
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
                __('This site uses HateDetect to reduce hate. <a href="%s" target="_blank" rel="nofollow noopener">Learn how your comment data is processed</a>.', 'hatedetect'),
                'https://hatedetect.com/privacy/'
            ) . '</p>'
        );
    }
}
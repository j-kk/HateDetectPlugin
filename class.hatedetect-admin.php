<?php

class HateDetect_Admin
{
    const NONCE = 'hatedetect-update-key';
    const SUPPORTED_LANGS = array('en', 'es');

    private static $initiated = false;
    private static $key_status = null;
    private static $notices = array();
    private static $allowed = array(
        'a' => array(
            'href' => true,
            'title' => true,
        ),
        'b' => array(),
        'code' => array(),
        'del' => array(
            'datetime' => true,
        ),
        'em' => array(),
        'i' => array(),
        'q' => array(
            'cite' => true,
        ),
        'strike' => array(),
        'strong' => array(),
    );

    public static function init()
    {
        if (!self::$initiated) {
            self::init_hooks();
        }

        if (isset($_POST['action']) && $_POST['action'] == 'enter-key') {
            self::enter_api_key();
        }
    }

    public static function init_hooks()
    {
        self::$initiated = true;

        add_action('admin_init', array('HateDetect_Admin', 'admin_init'));
        add_action('admin_menu', array(
            'HateDetect_Admin',
            'admin_menu'
        ), 5);
        add_action('admin_notices', array('HateDetect_Admin', 'display_notice'));
        add_action('admin_enqueue_scripts', array('HateDetect_Admin', 'load_resources'));
        add_action('manage_comments_nav', array('HateDetect_Admin', 'check_for_hate_button'));
        add_action('admin_action_hatedetect_recheck_queue', array('HateDetect_Admin', 'recheck_queue'));
        add_action('wp_ajax_hatedetect_recheck_queue', array('HateDetect_Admin', 'recheck_queue'));

        add_filter('plugin_action_links', array('HateDetect_Admin', 'plugin_action_links'), 10, 2);
        add_filter('comment_row_actions', array('HateDetect_Admin', 'comment_row_action'), 10, 2);

        add_filter('plugin_action_links_' . plugin_basename(plugin_dir_path(__FILE__) . 'HateDetect.php'), array(
            'HateDetect_Admin',
            'admin_plugin_settings_link'
        ));


    }

    public static function admin_init()
    {
        if (get_option('Activated_HateDetect')) {
            delete_option('Activated_HateDetect');
            if (!headers_sent()) {
                wp_redirect(add_query_arg(array(
                    'page' => 'hatedetect-key-config',
                    'view' => 'start'
                ), admin_url('options-general.php')));
            }
        }

        load_plugin_textdomain('hatedetect');

        if (function_exists('wp_add_privacy_policy_content')) {
            wp_add_privacy_policy_content(
                __('HateDetect', 'hatedetect'),
                __('We collect information about visitors who comment on Sites that use our HateDetect anti-hate service. The information we collect depends on how the User sets up HateDetect for the Site, but it includes the commenter\'s name, email address, and the comment itself).', 'hatedetect')
            );
        }
    }

    public static function admin_menu()
    {
        self::load_menu();
    }

    public static function admin_head()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
    }

    public static function admin_plugin_settings_link($links)
    {
        $settings_link = '<a href="' . esc_url(self::get_page_url()) . '">' . __('Settings', 'hatedetect') . '</a>';
        array_unshift($links, $settings_link);
        HateDetect::log("admin_plugin_settings_links links:  " . wp_json_encode($links));

        return $links;
    }

    public static function load_menu()
    {
        $hook = add_options_page(__('HateDetect', 'hatedetect'), __('HateDetect', 'hatedetect'), 'manage_options', 'hatedetect-key-config', array(
            'HateDetect_Admin',
            'display_page'
        ));

        if ($hook) {
            add_action("load-$hook", array('HateDetect_Admin', 'admin_help'));
        }
    }

    public static function load_resources()
    {
        global $hook_suffix;

        if (in_array($hook_suffix, apply_filters('hatedetect_admin_page_hook_suffixes', array(
            'index.php', # dashboard
            'edit-comments.php',
            'comment.php',
            'post.php',
            'settings_page_hatedetect-key-config',
            'plugins.php',
        )))) {
            wp_register_style('hatedetect.css', plugin_dir_url(__FILE__) . '_inc/hatedetect.css', array(), HATEDETECT_VERSION);
            wp_enqueue_style('hatedetect.css');
        }
    }

    /**
     * Add help to the HateDetect page
     *
     * @return false if not the HateDetect page
     */
    public static function admin_help()
    {
        $current_screen = get_current_screen();

        // Screen Content
        if (current_user_can('manage_options')) {
            if (!HateDetect::get_api_key() || (isset($_GET['view']) && $_GET['view'] == 'start')) {
                //setup page
                $current_screen->add_help_tab(
                    array(
                        'id' => 'overview',
                        'title' => __('Overview', 'hatedetect'),
                        'content' =>
                            '<p><strong>' . esc_html__('HateDetect Setup', 'hatedetect') . '</strong></p>' .
                            '<p>' . esc_html__('HateDetect filters out hate.', 'hatedetect') . '</p>' .
                            '<p>' . esc_html__('On this page, you are able to set up the HateDetect plugin.', 'hatedetect') . '</p>',
                    )
                );


                $current_screen->add_help_tab(
                    array(
                        'id' => 'setup-manual',
                        'title' => __('Enter an API Key', 'hatedetect'),
                        'content' =>
                            '<p><strong>' . esc_html__('HateDetect Setup', 'hatedetect') . '</strong></p>' .
                            '<p>' . esc_html__('If you already have an API key', 'hatedetect') . '</p>' .
                            '<ol>' .
                            '<li>' . esc_html__('Copy and paste the API key into the text field.', 'hatedetect') . '</li>' .
                            '<li>' . esc_html__('Click the Use this Key button.', 'hatedetect') . '</li>' .
                            '</ol>',
                    )
                );
            } else {
                //configuration page
                $current_screen->add_help_tab(
                    array(
                        'id' => 'overview',
                        'title' => __('Overview', 'hatedetect'),
                        'content' =>
                            '<p><strong>' . esc_html__('HateDetect Configuration', 'hatedetect') . '</strong></p>' .
                            '<p>' . esc_html__('HateDetect filters out hate.', 'hatedetect') . '</p>' .
                            '<p>' . esc_html__('On this page, you are able to update your HateDetect settings.', 'hatedetect') . '</p>',
                    )
                );

                $current_screen->add_help_tab(
                    array(
                        'id' => 'settings',
                        'title' => __('Settings', 'hatedetect'),
                        'content' =>
                            '<p><strong>' . esc_html__('HateDetect Configuration', 'hatedetect') . '</strong></p>' .
                            '<p><strong>' . esc_html__('API Key', 'hatedetect') . '</strong> - ' . esc_html__('Enter/remove an API key.', 'hatedetect') . '</p>' .
                            '<p><strong>' . esc_html__('Auto allow', 'hatedetect') . '</strong> - ' . esc_html__('Should plugin auto allow all comments which does not contain hate. (skips moderation verification)', 'hatedetect') . '</p>' .
                            '<p><strong>' . esc_html__('Auto discard', 'hatedetect') . '</strong> - ' . esc_html__('Choose to either discard hateful comments automatically or to hold them for moderator interaction.', 'hatedetect') . '</p>',
                    )
                );

            }
        }
    }

    public static function enter_api_key()
    {
        if (!current_user_can('manage_options')) {
            die(__('Cheatin&#8217; uh?', 'hatedetect'));
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            return false;
        }

        foreach (array('hatedetect_auto_allow',
                     'hatedetect_auto_discard',
                     'hatedetect_notify_user') as $option) {
            update_option($option, isset($_POST[$option]) && (int)$_POST[$option] == 1 ? '1' : '0');
            HateDetect::log("Updated option: " . $option);
        }
        if (in_array($_POST['hatedetect_lang'], HateDetect_Admin::SUPPORTED_LANGS)) {
            add_option('hatedetect_lang', $_POST['hatedetect_lang']);
        }

        if (!empty($_POST['hatedetect_comment_form_privacy_notice'])) {
            self::set_form_privacy_notice_option($_POST['hatedetect_comment_form_privacy_notice']);
        } else {
            self::set_form_privacy_notice_option('hide');
        }

        $new_key = $_POST['key'];
        $old_key = HateDetect::get_api_key();


        if (empty($new_key)) {
            if (!empty($old_key)) {
                delete_option('hatedetect_api_key');
                self::$notices[] = 'new-key-empty';
            }
        } elseif ($new_key != $old_key) {
            self::save_key($new_key);
        }

        return true;
    }

    public static function save_key($api_key)
    {
        HateDetect::log("Verifying key: " . $api_key);
        $key_status = HateDetect::verify_key($api_key);
        self::$key_status = $key_status;

        if ($key_status == 'OK') {
            update_option('hatedetect_api_key', $api_key);
            self::$notices['status'] = 'new-key-valid';
            HateDetect::manual_schedule_cron_recheck(15);
        } elseif (in_array($key_status, array('invalid', 'failed'))) {
            self::$notices['status'] = 'new-key-' . $key_status;
        }
    }

    public static function check_for_hate_button($comment_status) # TODO
    {
        // The "Check for Hate" button should only appear when the page might be showing
        // a comment with comment_approved=0, which means an un-trashed, un-hate,
        // not-yet-moderated comment.
        if ('all' != $comment_status && 'moderated' != $comment_status) {
            return;
        }

        $link = '';

        $comments_count = wp_count_comments();

        echo '</div>';
        echo '<div class="alignleft actions">';
        # TODO
        $classes = array(
            'button-secondary',
            'checkforhate',
            'button-disabled'    // Disable button until the page is loaded
        );

        if ($comments_count->moderated > 0) {
            $classes[] = 'enable-on-load';

            if (!HateDetect::get_api_key()) {
                $link = add_query_arg(array('page' => 'hatedetect-key-config'), admin_url('options-general.php'));
                $classes[] = 'ajax-disabled';
            }
        }

        echo '<a
				class="' . esc_attr(implode(' ', $classes)) . '"' .
            (!empty($link) ? ' href="' . esc_url($link) . '"' : '') .
            /* translators: The placeholder is for showing how much of the process has completed, as a percent. e.g., "Checking for hate (40%)" */
            ' data-progress-label="' . esc_attr(__('Checking for hate (%1$s%)', 'hatedetect')) . '"
				data-success-url="' . esc_attr(remove_query_arg(array(
                'hatedetect_recheck',
                'hatedetect_recheck_error'
            ), add_query_arg(array(
                'hatedetect_recheck_complete' => 1,
                'recheck_count' => urlencode('__recheck_count__'),
                'hate_count' => urlencode('__hate_count__')
            )))) . '"
				data-failure-url="' . esc_attr(remove_query_arg(array(
                'hatedetect_recheck',
                'hatedetect_recheck_complete'
            ), add_query_arg(array('hatedetect_recheck_error' => 1)))) . '"
				data-pending-comment-count="' . esc_attr($comments_count->moderated) . '"
				data-nonce="' . esc_attr(wp_create_nonce('hatedetect_check_for_hate')) . '"
				' . (!in_array('ajax-disabled', $classes) ? 'onclick="return false;"' : '') . '
				>' . esc_html__('Check for Hate', 'hatedetect') . '</a>';
        echo '<span class="checkforhate-spinner"></span>';
    }

    public static function recheck_queue()
    {
        global $wpdb;

        if (!(isset($_GET['recheckqueue']) || (isset($_REQUEST['action']) && 'hatedetect_recheck_queue' == $_REQUEST['action']))) {
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'], 'hatedetect_check_for_hate')) {
            wp_send_json(array(
                'error' => __("You don't have permission to do that."),
            ));

            return;
        }

        $result_counts = self::recheck_queue_portion(empty($_POST['offset']) ? 0 : $_POST['offset'], empty($_POST['limit']) ? 100 : $_POST['limit']);

        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json(array(
                'counts' => $result_counts,
            ));
        } else {
            $redirect_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : admin_url('edit-comments.php');
            wp_safe_redirect($redirect_to);
            exit;
        }
    }

    # TODO
    public static function recheck_queue_portion($start = 0, $limit = 100)
    {
        global $wpdb;

        $paginate = '';

        if ($limit <= 0) {
            $limit = 100;
        }

        if ($start < 0) {
            $start = 0;
        }

        $moderation = $wpdb->get_col($wpdb->prepare("SELECT * FROM {$wpdb->comments} WHERE comment_approved = '0' LIMIT %d OFFSET %d", $limit, $start));

        $result_counts = array(
            'processed' => count($moderation),
            'hate' => 0,
            'ham' => 0,
            'error' => 0,
        );

        foreach ($moderation as $comment_id) {
            $api_response = HateDetect::recheck_comment($comment_id, 'recheck_queue');

            if ('true' === $api_response) {
                ++$result_counts['hate'];
            } elseif ('false' === $api_response) {
                ++$result_counts['ham'];
            } else {
                ++$result_counts['error'];
            }
        }

        return $result_counts;
    }

    # TODO
    public static function comment_row_action($a, WP_Comment $comment)
    {
        $hatedetect_result = get_comment_meta($comment->comment_ID, 'hatedetect_result', true);
        $hatedetect_error = get_comment_meta($comment->comment_ID, 'hatedetect_error', true);
        $comment_status = wp_get_comment_status($comment->comment_ID);
        $desc = null;
        $desc_on_hover = null;
        HateDetect::log('Comment row action, id: ' . $comment->comment_ID . ' result:' . $hatedetect_result . " error: " . $hatedetect_error);
        if ($hatedetect_error) {
            $desc = __('Awaiting hate check', 'hatedetect');
            $desc_on_hover = __('Plugin was unable to connect to HateDetect servers. Comment will be checked again soon.', 'hatedetect');
        } elseif (!is_null($hatedetect_result)) {
            if ($hatedetect_result === '1') {
                $desc = __('Hate speech, reason: ' . HateDetect::check_why_hate($comment->comment_ID, $comment), 'hatedetect');
            } else {

                $desc = __('OK', 'hatedetect');
            }
            $desc_on_hover = $desc;
        }

        HateDetect::log('Comment row action, id: ' . $comment->comment_ID . ' desc:' . $desc);
        if ($desc) {
            echo '<span class="hatedetect-status" commentid="' . $comment->comment_ID . '"><a href="comment.php?action=editcomment&amp;c=' . $comment->comment_ID . '#hatedetect-status" title="' . esc_attr($desc_on_hover) . '">' . esc_html($desc) . '</a></span>';
        }

        return $a;
    }

    public static function plugin_action_links($links, $file)
    {
        HateDetect::log("plugin_action_links entry links:  " . wp_json_encode($links) . "   file:   " . wp_json_encode($file));
        if ($file == plugin_basename(plugin_dir_url(__FILE__) . '/hatedetect.php')) {
            $links[] = '<a href="' . esc_url(self::get_page_url()) . '">' . esc_html__('Settings', 'hatedetect') . '</a>';
        }

        HateDetect::log("plugin_action_links out links:  " . wp_json_encode($links) . "   file:   " . wp_json_encode($file));
        return $links;
    }


    // Simpler connectivity check
    public static function check_server_connectivity()
    {
        $debug = array();
        $debug['PHP_VERSION'] = PHP_VERSION;
        $debug['WORDPRESS_VERSION'] = $GLOBALS['wp_version'];
        $debug['HATEDETECT_VERSION'] = HATEDETECT_VERSION;
        $debug['HATEDETECT__PLUGIN_DIR'] = HATEDETECT__PLUGIN_DIR;
        $debug['SITE_URL'] = site_url();
        $debug['HOME_URL'] = home_url();

        $response = HateDetect::http_post(array(), 'test', null, false);

        $debug['gethostbynamel'] = function_exists('gethostbynamel') ? 'exists' : 'not here';
        $debug['Test Connection'] = $response;

        HateDetect::log($debug);

        if ($response && 'OK' == $response[1]) {
            return true;
        }

        return false;
    }


    public static function get_page_url($page = 'config')
    {

        $args = array('page' => 'hatedetect-key-config');

        if ($page == 'delete_key') {
            $args = array(
                'page' => 'hatedetect-key-config',
                'view' => 'start',
                'action' => 'delete-key',
                '_wpnonce' => wp_create_nonce(self::NONCE)
            );
        }

        return add_query_arg($args, admin_url('options-general.php'));
    }

    public static function display_alert()
    {
        HateDetect::view('notice', array(
            'type' => 'alert',
            'code' => (int)get_option('hatedetect_alert_code'),
            'msg' => get_option('hatedetect_alert_msg')
        ));
    }

    public static function display_api_key_warning()
    {
        HateDetect::view('notice', array('type' => 'plugin'));
    }

    public static function display_page()
    {
        HateDetect::log('display page');
        if (!HateDetect::get_api_key() || (isset($_GET['view']) && $_GET['view'] == 'start')) {

            HateDetect::log('start?');
            self::display_start_page();
        } else {
            HateDetect::log('config');
            self::display_configuration_page();
        }
    }

    public static function display_start_page()
    {
        if (isset($_GET['action'])) {
            if ($_GET['action'] == 'delete-key') {
                if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], self::NONCE)) {
                    delete_option('hatedetect_api_key');
                }
            }
        }

        if ($api_key = HateDetect::get_api_key() && (empty(self::$notices['status']) || 'existing-key-invalid' != self::$notices['status'])) {
            self::display_configuration_page();

            return;
        }

        //the user can choose to auto connect their API key by clicking a button on the hatedetect done page
        //get verified api key by using an hatedetect token


        if (isset($_GET['token'])) {
            $api_key_status = HateDetect::verify_key($_GET['token']);
        }
        if (isset($_GET['action'])) {
            if ($_GET['action'] == 'save-key') {
                if ($api_key_status == 'OK') {
                    self::save_key($_GET['token']);
                    self::display_configuration_page();

                    return;
                }
            }
        }

        HateDetect::view('start');
    }

    public static function display_configuration_page()
    {
        $api_key = HateDetect::get_api_key();
        // Set default setting values
        if (get_option('hatedetect_auto_discard') === false) {
            add_option('hatedetect_auto_discard', '0');
        }
        if (get_option('hatedetect_auto_allow') === false) {
            add_option('hatedetect_auto_allow', '0');
        }
        if (get_option('hatedetect_notify_user') === false) {
            add_option('hatedetect_notify_user', '0');
        }
        if (get_option('hatedetect_lang') === false) {
            add_option('hatedetect_lang', 'en');
        }

        HateDetect::view('config', compact('api_key'));
    }

    public static function get_status()
    {
        if (self::$key_status === null) {
            self::$key_status = HateDetect::verify_key(HateDetect::get_api_key());
            HateDetect::log("Key status is null! Received: " . self::$key_status); # TODO misia
        }
        return self::$key_status;
    }

    public static function display_notice() # TODO
    {
        global $hook_suffix;

        if (in_array($hook_suffix, array(
            'settings_page_hatedetect-key-config'
        ))) {
            // This page manages the notices and puts them inline where they make sense.
            return;
        }

        if (in_array($hook_suffix, array('edit-comments.php')) && (int)get_option('hatedetect_alert_code') > 0) {
            HateDetect::verify_key(HateDetect::get_api_key()); //verify that the key is still in alert state

            if (get_option('hatedetect_alert_code') > 0) {
                self::display_alert();
            }
        } elseif (('plugins.php' === $hook_suffix || 'edit-comments.php' === $hook_suffix) && !HateDetect::get_api_key()) {
            // Show the "Set Up HateDetect" banner on the comments and plugin pages if no API key has been set.
            self::display_api_key_warning();
        }

        if (isset($_GET['hatedetect_recheck_complete'])) {
            $recheck_count = (int)$_GET['recheck_count'];
            $hate_count = (int)$_GET['hate_count'];

            if ($recheck_count === 0) {
                $message = __('There were no comments to check. HateDetect will only check comments awaiting moderation.', 'hatedetect');
            } else {
                $message = sprintf(_n('HateDetect checked %s comment.', 'HateDetect checked %s comments.', $recheck_count, 'hatedetect'), number_format($recheck_count));
                $message .= ' ';

                if ($hate_count === 0) {
                    $message .= __('No comments were caught as hate.', 'hatedetect');
                } else {
                    $message .= sprintf(_n('%s comment was caught as hate.', '%s comments were caught as hate.', $hate_count, 'hatedetect'), number_format($hate_count));
                }
            }

            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        } else if (isset($_GET['hatedetect_recheck_error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html(__('HateDetect could not recheck your comments for hate.', 'hatedetect')) . '</p></div>';
        }
    }

    public static function display_status()
    {
        HateDetect::log("Display status");
        if (!self::check_server_connectivity()) {
            HateDetect::log("Unable to connect, notice.");
            HateDetect::view('notice', array('type' => 'servers-be-down'));
        } else if (!empty(self::$notices)) {
            foreach (self::$notices as $index => $type) {
                HateDetect::log("Displaying notices: ");
                if (is_object($type)) {
                    HateDetect::log($type);
                    $notice_header = $notice_text = '';

                    if (property_exists($type, 'notice_header')) {
                        $notice_header = wp_kses($type->notice_header, self::$allowed);
                    }

                    if (property_exists($type, 'notice_text')) {
                        $notice_text = wp_kses($type->notice_text, self::$allowed);
                    }

                    if (property_exists($type, 'status')) {
                        $type = wp_kses($type->status, self::$allowed);
                        HateDetect::view('notice', compact('type', 'notice_header', 'notice_text'));

                        unset(self::$notices[$index]);
                    }
                } else {
                    HateDetect::view('notice', compact('type'));

                    unset(self::$notices[$index]);
                }
            }
        }
    }


    private static function set_form_privacy_notice_option($state)
    {
        if (in_array($state, array('display', 'hide'))) {
            update_option('hatedetect_comment_form_privacy_notice', $state);
        }
    }

}

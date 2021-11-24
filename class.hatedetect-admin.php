<?php

class HateDetect_Admin
{
    const NONCE = 'hatedetect-update-key';
    const SUPPORTED_LANGS = array('en' => 'English', 'es' => 'Spanish');

    private static bool $initiated = false;
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

	/**
	 * Initializes plugin admin actions.
	 */
	public static function init()
    {
        if (!self::$initiated) {
            self::init_hooks();
        }

        if (isset($_POST['action']) &&  sanitize_text_field($_POST['action'])  == 'enter-key') {
            self::process_settings_update();
        }
    }

    /**
     * Initialization of admin hooks for purpose of hatedetect WordPress plugin.
     */
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

        add_filter('comment_row_actions', array('HateDetect_Admin', 'modify_comments_list_row_actions'), 100, 2);
        add_action('wp_ajax_check_for_hate', array('HateDetect_Admin', 'admin_action_comment_check_for_hate'));
        add_action('wp_ajax_explain_hate', array('HateDetect_Admin', 'admin_action_comment_explain_hate'));


    }

    /**
     * Initialization of admin control page.
     */
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

    /**
     * Initialization admin menu.
     */
    public static function admin_menu()
    {
	    $hook = add_options_page(__('HateDetect', 'hatedetect'), __('HateDetect', 'hatedetect'), 'manage_options', 'hatedetect-key-config', array(
		    'HateDetect_Admin',
		    'display_page'
	    ));

	    if ($hook) {
		    add_action("load-$hook", array('HateDetect_Admin', 'admin_help'));
	    }    }

    /**
     * Creating admin head page.
     */
    public static function admin_head()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
    }

    /**
     * Creating plugin setting page.
     *
     * @param array $links base weblink
     *
     * @return array links
     */
    public static function admin_plugin_settings_link(array $links): array {
        $settings_link = '<a href="' . esc_url(self::get_page_url()) . '">' . __('Settings', 'hatedetect') . '</a>';
        array_unshift($links, $settings_link);
        HateDetect::log( 'admin_plugin_settings_links links:  ' . wp_json_encode($links));

        return $links;
    }

    /**
     *  Loading additional resources.
     */
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

            wp_register_script('hatedetect.js', plugin_dir_url(__FILE__) . '_inc/hatedetect.js', array('jquery'), HATEDETECT_VERSION);
            wp_enqueue_script('hatedetect.js');

            $inline_js = array();

            if (isset($_GET['hatedetect_recheck']) && wp_verify_nonce($_GET['hatedetect_recheck'], 'hatedetect_recheck')) {
                $inline_js['start_recheck'] = true;
            }

            wp_localize_script('hatedetect.js', 'WPHateDetect', $inline_js);
        }
    }

    /**
     * Add help to the HateDetect page.
     */
    public static function admin_help()
    {
        $current_screen = get_current_screen();
		// Screen Content
		if ( current_user_can( 'manage_options' ) ) {
			if ( ! HateDetect_ApiKey::get_api_key() || ( isset( $_GET['view'] ) && sanitize_text_field($_GET['view']) == 'start' ) ) {
				//setup page
				$current_screen->add_help_tab(
					array(
						'id'      => 'overview',
						'title'   => __( 'Overview', 'hatedetect' ),
						'content' =>
							'<p><strong>' . esc_html__( 'HateDetect Setup', 'hatedetect' ) . '</strong></p>' .
							'<p>' . esc_html__( 'HateDetect filters out hate.', 'hatedetect' ) . '</p>' .
							'<p>' . esc_html__( 'On this page, you are able to set up the HateDetect plugin.', 'hatedetect' ) . '</p>',
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
                            '<p><strong>' . esc_html__('Notify after submitting', 'hatedetect') . '</strong> - ' . esc_html__('Show user after submitting comment if it was marked as hate speech.', 'hatedetect') . '</p>' .
                            '<p><strong>' . esc_html__('Notify in email', 'hatedetect') . '</strong> - ' . esc_html__('Send an email to comment\'s author if her/his comment was rejected because of hate speech and why. Requires configured SMTP server (for example with WP Mail SMTP by WPForms plugin)', 'hatedetect') . '</p>',
                    )
                );

            }
        }
    }

    /**
     * Proceeds the data submitted in the settings form.
     *
     * @return bool|void result of updating options.
     */
    public static function process_settings_update()
    {
        if (!current_user_can('manage_options')) {
            die(__('not an admin', 'hatedetect'));
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            return false;
        }

		foreach (
			array(
				'hatedetect_auto_allow',
				'hatedetect_auto_discard',
				'hatedetect_notify_user',
				'hatedetect_notify_moderator',
				'hatedetect_show_comment_field_message'
			) as $option
		) {
			$new_value = isset( $_POST[ $option ] ) && intval( $_POST[ $option ])  == 1 ? '1' : '0';
			if ( update_option( $option, $new_value ) ) {
				HateDetect::log( 'Updated option: ' . $option . ' New value: ' . $new_value );
			}
		}
		if ( isset( $_POST['hatedetect_lang'] ) ) {
			$sanitized_lang = sanitize_text_field($_POST['hatedetect_lang']);
			if ( array_key_exists( $sanitized_lang, HateDetect_Admin::SUPPORTED_LANGS ) ) {
				update_option( 'hatedetect_lang', $sanitized_lang);
			}
		}
		if ( ! empty( $_POST['hatedetect_comment_form_privacy_notice'] ) ) {
			self::set_form_privacy_notice_option(  sanitize_text_field( $_POST['hatedetect_comment_form_privacy_notice']));
		} else {
			self::set_form_privacy_notice_option( 'hide' );
		}

		$new_key = sanitize_text_field($_POST['key']);
		$old_key = HateDetect_ApiKey::get_api_key();


		if ( empty( $new_key ) ) {
			if ( $old_key ) { # old key exists (otherwise false)
				delete_option( 'hatedetect_api_key' );
			}
			update_option( 'hatedetect_key_status', 'key_empty' );
		} elseif ( $new_key != $old_key ) {
			if (is_null(HateDetect_ApiKey::verify_key( $new_key ))) {
				return false;
			}
		}

        return true;
    }


    /**
     *  Creating button for checking if comment is hateful.
     *  Applied only to comment with comment_approved=0, which means an un-trashed, un-hate,
     *  not-yet-moderated comment.
     *
     * @param string $comment_status comment status.
     */
    public static function check_for_hate_button( string $comment_status)
    {
        // The "Check for Hate" button should only appear when the page might be showing
        // a comment with comment_approved=0, which means an un-trashed, un-hate,
        // not-yet-moderated comment.
        if ('all' != $comment_status && 'moderated' != $comment_status) {
            return;
        }

        $link = '';

        $comments_count = wp_count_comments();

        echo wp_kses_decode_entities('</div>');
        echo wp_kses_decode_entities('<div class="alignleft actions">');

        $classes = array(
            'button-secondary',
            'checkforhate',
            'button-disabled'    // Disable button until the page is loaded
        );

        if ($comments_count->moderated > 0) {
            $classes[] = 'enable-on-load';

            if (!HateDetect_ApiKey::get_api_key()) {
                $link = add_query_arg(array('page' => 'hatedetect-key-config'), admin_url('options-general.php'));
                $classes[] = 'ajax-disabled';
            }
        }

        echo wp_kses_decode_entities('<a
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
				>' . esc_html__('Check awaiting comments for hate', 'hatedetect') . '</a>');
        echo wp_kses_decode_entities('<span class="checkforhate-spinner"></span>');
    }

    /**
     * Checking te queue of unchecked comments that await hate moderation.
     */
    public static function recheck_queue()
    {
        global $wpdb;

        if (!(isset($_GET['recheckqueue']) || (isset($_REQUEST['action']) && 'hatedetect_recheck_queue' == sanitize_text_field($_REQUEST['action'])))) {
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'], 'hatedetect_check_for_hate')) {
            wp_send_json(array(
                'error' => __("You don't have permission to do that."),
            ));

            return;
        }

        $result_counts = self::recheck_queue_portion(!empty($_POST['offset']) && is_numeric(sanitize_text_field($_POST['offset'])) ?  $_POST['offset'] : 0, !empty($_POST['limit']) && is_numeric(sanitize_text_field($_POST['limit'])? $_POST['limit'] : 100));

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


    /**
     * Processing of comments that await check for hate speech detection.
     *
     * @param int $start start index
     * @param int $limit maximum length of comments to check
     *
     * @return array count of processed comments, count of marked as hate comments and count of comments
     *               that encountered an error while processing.
     */
    public static function recheck_queue_portion(int $start = 0, int $limit = 100): array {
        global $wpdb;

        if ($limit <= 0) {
            $limit = 100;
        }

        if ($start < 0) {
            $start = 0;
        }

        $moderation = $wpdb->get_col($wpdb->prepare("SELECT * FROM {$wpdb->comments} WHERE comment_approved = 0 AND comment_ID not in (SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = 'hatedetect_result') LIMIT %d OFFSET %d", $limit, $start));

        $result_counts = array(
            'processed' => count($moderation),
            'hate' => 0,
            'error' => 0,
        );

        foreach ($moderation as $comment_id) {
            $api_response = HateDetect::check_db_comment($comment_id);

            if ($api_response) {
                if (get_comment_meta($comment_id, 'hatedetect_result', true) === '1') {
                    ++$result_counts['hate'];
                }
            } else {
                ++$result_counts['error'];
            }
        }

        return $result_counts;
    }

    /**
     * Add action to each comment which shows results of hate detection.
     *
     * @param array $a action
     * @param WP_Comment $comment comment to which add new action
     * @return array  action
     */
    public static function comment_row_action( array $a, WP_Comment $comment): array {
        $hatedetect_result = get_comment_meta($comment->comment_ID, 'hatedetect_result', true);
        $hatedetect_error = get_comment_meta($comment->comment_ID, 'hatedetect_error', true);
        $hatedetect_explanation = get_comment_meta($comment->comment_ID, 'hatedetect_explanation', true);
        $desc = null;
        $desc_on_hover = null;
        HateDetect::log('Comment row action, id: ' . $comment->comment_ID . ' result:' . $hatedetect_result . ' error: ' . $hatedetect_error);
        if ($hatedetect_error) {
            $desc = __('Awaiting hate check', 'hatedetect');
            $desc_on_hover = __('Plugin was unable to connect to HateDetect servers. Comment will be checked again soon.', 'hatedetect');
        } elseif (!is_null($hatedetect_result)) {
            if ($hatedetect_result === '1') {
                if (is_array($hatedetect_explanation)) {
                    $explanation_text =  __("general hate", 'hatedetect');
                    if (array_key_exists('Reasons', $hatedetect_explanation) && is_array($hatedetect_explanation['Reasons'])){
                        if (array_key_exists('nbrs', $hatedetect_explanation['Reasons']) && array_key_exists('dtxfy', $hatedetect_explanation['Reasons'])) {
                            $explanation_text = implode(", ",$hatedetect_explanation['Reasons']['nbrs']). ' '. implode(" ",$hatedetect_explanation['Reasons']['dtxfy']) ;
                        }
                        else{
                            $explanation_text = implode(", ", $hatedetect_explanation['Reasons']);
                        }
                        $explanation_text = strtolower(str_replace( array("<", ">", "_"), " ",  $explanation_text));
                    }
                    $desc = __('Hate speech, reason: ' .$explanation_text, 'hatedetect');
                } else {
                    $desc = __('Hate speech', 'hatedetect');
                }
            } else {

                $desc = __('OK', 'hatedetect');
            }
            $desc_on_hover = $desc;
        }

        HateDetect::log('Comment row action, id: ' . $comment->comment_ID . ' desc:' . $desc);
        if ($desc) {
            echo wp_kses_decode_entities('<span class="hatedetect-status" commentid="' . $comment->comment_ID . '"><a href="comment.php?action=editcomment&amp;c=' . $comment->comment_ID . '#hatedetect-status" title="' . esc_attr($desc_on_hover) . '">' . esc_html($desc) . '</a></span>');
        }

        return $a;
    }

    /**
     * Add plugin action links.
     *
     * @param array $links base website link
     * @param string $file additional files to add to plugin for a display.
     * @return array links
     */
    public static function plugin_action_links( array $links, string $file): array {
        HateDetect::log( 'plugin_action_links entry links:  ' . wp_json_encode($links) . '   file:   ' . wp_json_encode($file));
        if ($file == plugin_basename(plugin_dir_url(__FILE__) . '/hatedetect.php')) {
            $links[] = '<a href="' . esc_url(self::get_page_url()) . '">' . esc_html__('Settings', 'hatedetect') . '</a>';
        }

        HateDetect::log( 'plugin_action_links out links:  ' . wp_json_encode($links) . '   file:   ' . wp_json_encode($file));

        return $links;
    }


    /**
     * Checking connection to the server with hate detection model.
     *
     * @return bool is servers connected
     */
    public static function check_server_connectivity(): bool {
        $debug = array();
        $debug['PHP_VERSION'] = PHP_VERSION;
        $debug['WORDPRESS_VERSION'] = $GLOBALS['wp_version'];
        $debug['HATEDETECT_VERSION'] = HATEDETECT_VERSION;
        $debug['HATEDETECT__PLUGIN_DIR'] = HATEDETECT__PLUGIN_DIR;
        $debug['SITE_URL'] = site_url();
        $debug['HOME_URL'] = home_url();

        $response = HateDetect::http_post([], 'isalive', null, false);

        $debug['gethostbynamel'] = function_exists('gethostbynamel') ? 'exists' : 'not here';
        $debug['Test Connection'] = $response;

        HateDetect::log($debug);

        if ($response && is_int($response[1]) && 200 <= $response[1] && $response[1] < 300 && 'OK' == $response[2]) {
            return true;
        }

        return false;
    }

    /**
     * Displays hatedetect plugin configuration page.
     */
    public static function display_configuration_page()
    {
        // Set default setting values
        $api_key = HateDetect_ApiKey::get_api_key();
        if (get_option('hatedetect_auto_discard') === false) {
            add_option('hatedetect_auto_discard', '0');
        }
        if (get_option('hatedetect_auto_allow') === false) {
            add_option('hatedetect_auto_allow', '0');
        }
        if (get_option('hatedetect_notify_user') === false) {
            add_option('hatedetect_notify_user', '0');
        }
        if (get_option('hatedetect_notify_moderator') === false) {
            add_option('hatedetect_notify_moderator', '0');
        }
        if (get_option('hatedetect_lang') === false) {
            add_option('hatedetect_lang', 'en');
        }
        if (get_option('hatedetect_show_comment_field_message') === false) {
            add_option('hatedetect_show_comment_field_message', '1');
        }
        HateDetect::view('config', compact('api_key'));
    }

    /**
     * Getter of page url.
     *
     * @param string $page config page
     *
     * @return string New URL query string (unescaped).
     */
    public static function get_page_url( string $page = 'config'): string {
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

    /**
     * Displays warning in case of api key warning.
     */
    public static function display_api_key_warning()
    {
		$key_status = get_option('hatedetect_key_status', 'key_empty');
        if ($key_status == 'Failed') {
            HateDetect::view('notice', array('type' => 'new-key-invalid'));
			delete_option('hatedetect_key_status');
        } elseif ($key_status === 'Activated') {
            HateDetect::view('notice', array('type' => 'activated'));
            update_option('hatedetect_key_status', 'OK');
        } elseif ($key_status == 'key_empty') {
            HateDetect::view('notice', array('type' => 'new-key-empty'));
            delete_option('hatedetect_key_status');
        } elseif ($key_status == 'Unknown')
	        HateDetect::view('notice', array('type' => 'servers-be-down'));
    }

    /**
     * Displays page for hatedetect plugin.
     */
    public static function display_page()
    {
        if (!HateDetect_ApiKey::get_api_key() || (isset($_GET['view']) && sanitize_text_field($_GET['view']) == 'start')) {
            self::display_start_page();
        } else {
            self::display_configuration_page();
        }
    }

    /**
     * Displays start page for hatedetect plugin. Start page concerns api key entry.
     */
    public static function display_start_page()
    {
        if (isset($_GET['action'])) {
            if (sanitize_text_field($_GET['action']) == 'delete-key') {
                if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], self::NONCE)) {
                    delete_option('hatedetect_api_key');
                    delete_option('hatedetect_key_status');
                }
            }
        }
        HateDetect::view('start');
    }


    /**
     * Displays notice regarding hatedetect plugin usage.
     * Notices are displayed not only on the plugin's setting page.
     * They contain information about results of the scheduled manual recheck and reminders to set up configuration.
     */
    public static function display_notice()
    {
        global $hook_suffix;
		HateDetect::log( 'admin_notices hook called on hatedetect' );
		if ( in_array( $hook_suffix, array(
			'settings_page_hatedetect-key-config'
		) ) ) {
			HateDetect::log( 'admin_notices hatedetect settings page' );
			// This page manages the notices and puts them inline where they make sense.
			return;
		}
	    if (in_array($hook_suffix, apply_filters('hatedetect_admin_page_hook_suffixes', array(
		    'index.php', # dashboard
		    'edit-comments.php',
		    'plugins.php',
	    )))) {
		    if ( ! HateDetect_ApiKey::get_api_key() ) {
			    // Show the "Set Up HateDetect" banner on the comments and plugin pages if no API key has been set.
			    HateDetect::view( 'notice', array( 'type' => 'plugin' ) );
		    }
		    self::display_status();
		    if ( isset( $_GET['hatedetect_recheck_complete'] ) ) {
				$recheck_count = sanitize_key($_GET['recheck_count']);
				$hate_count = sanitize_key($_GET['hate_count']);

				if ( !is_numeric($recheck_count) || !is_numeric($hate_count)) {
					return;
				}
			    $recheck_count = (int) $recheck_count;
			    $hate_count    = (int) $hate_count;

			    if ( $recheck_count === 0 ) {
				    $message = __( 'There were no comments to check. HateDetect will only check comments awaiting moderation.', 'hatedetect' );
			    } else {
				    $message = sprintf( _n( 'HateDetect checked %s comment.', 'HateDetect checked %s comments.', $recheck_count, 'hatedetect' ), number_format( $recheck_count ) );
				    $message .= ' ';

				    if ( $hate_count === 0 ) {
					    $message .= __( 'No comments were caught as hate.', 'hatedetect' );
				    } else {
					    $message .= sprintf( _n( '%s comment was caught as hate.', '%s comments were caught as hate.', $hate_count, 'hatedetect' ), number_format( $hate_count ) );
				    }
			    }

			    echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
		    } elseif ( isset( $_GET['hatedetect_recheck_error'] ) ) {
			    echo '<div class="notice notice-error"><p>' . esc_html( __( 'HateDetect could not recheck your comments for hate.', 'hatedetect' ) ) . '</p></div>';
		    }
	    }
    }

    /**
     * Displays status during usage of plugin.
     * It is displayed only at the plugin's settings page.
     */
    public static function display_status()
    {
		HateDetect::log('Displaying status now!');
		self::display_api_key_warning();
		# In case
		$key_status = HateDetect_ApiKey::get_key_status();


		if (HateDetect_ApiKey::get_api_key()) {
			$hatedetect_connection = get_option('hatedetect_connection');
			if ($hatedetect_connection) {
				$type = 'connection-error';
				$notice_text = wp_kses( $hatedetect_connection, self::$allowed );
				HateDetect::view( 'notice', compact(    'type', 'notice_text' ) );
			}
			if (is_null($key_status)) {
				HateDetect::view( 'notice', array( 'type' => 'servers-be-down' ) );
			}
		}
    }


    /**
     * Setter for choosing option of displaying privacy notice option in setting form.
     *
     * @param string $state states how to display privacy notice (hide or show).
     */
    private static function set_form_privacy_notice_option( string $state)
    {
        if (in_array($state, array('display', 'hide'))) {
            update_option('hatedetect_comment_form_privacy_notice', $state);
        }
    }


    /**
     * Adding specified actions to comment list row.
     *
     * @param array|mixed $actions action to add
     * @param WP_Comment $comment selected comment
     * @return array new actions.
     */
    public static function modify_comments_list_row_actions($actions, WP_Comment $comment): array {
        $nonce = wp_create_nonce('check_for_hate');
        $args = array(
            'c' => $comment->comment_ID,
            'action' => 'check_for_hate',
            'another_query' => '1',
            '_wpnonce' => $nonce,
        );
        $link = esc_url(add_query_arg($args, admin_url('admin-ajax.php')));
        $actions['hatedetect_check_hate'] = sprintf('<a href="%s" style="color:orange">Check for hate</a>', $link);


        if (get_comment_meta($comment->comment_ID, 'hatedetect_result', true) === '1') {
            $nonce2 = wp_create_nonce('explain_hate');
            $args2 = array(
                'c' => $comment->comment_ID,
                'action' => 'explain_hate',
                'another_query' => '1',
                '_wpnonce' => $nonce2,
            );
            $link2 = esc_url(add_query_arg($args2, admin_url('admin-ajax.php')));
            $actions['hatedetect_explain_hate'] = sprintf('<a href="%s" style="color:orange">Explain why hate</a>', $link2);
        }

        return $actions;
    }


    /**
     * Handling admin action to check comment for hate speech on demand (in comments view).
     */
    public static function admin_action_comment_check_for_hate()
    {
        $refer = wp_get_referer();
        if (wp_verify_nonce($_REQUEST['_wpnonce'], 'check_for_hate')) {
            $id = sanitize_key($_REQUEST['c']);
            HateDetect::check_comment($id, get_comment($id));
        }
        wp_redirect($refer);
        exit;
    }


    /**
     * Handling admin action to explain hate speech source for comment marked as hateful (in comments view).
     */
    public static function admin_action_comment_explain_hate()
    {
        $refer = wp_get_referer();
        if (wp_verify_nonce($_REQUEST['_wpnonce'], 'explain_hate')) {
            $id = sanitize_key($_REQUEST['c']);
            HateDetect::check_why_hate($id, get_comment($id));
        }
        wp_redirect($refer);
        exit;
    }


    /**
     * Gets amount of comments marked as non-hateful.
     *
     * @return int number of non-hateful comments.
     */
    public static function get_user_comments_approved(): int
    {
        global $wpdb;

        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key='hatedetect_result' AND meta_value=1");
    }

    /**
     * Gets amount of comments marked as hateful.
     *
     * @return int number of hateful comments.
     */
    public static function get_user_comments_rejected(): int
    {
        global $wpdb;

        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key='hatedetect_result' AND meta_value=0");
    }

    /**
     * Gets amount of comments in database, which were encountered an error when checking for hate.
     *
     * @return int number of comments waiting for hate check.
     */
    public static function get_user_comments_queued(): int
    {
        global $wpdb;

        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = 'hatedetect_error'");
    }
}

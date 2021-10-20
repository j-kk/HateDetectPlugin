<?php

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.

?>
<?php if ( $type == 'plugin' ) : ?>
    <div class="updated" id="hatedetect_setup_prompt">
        <form name="hatedetect_activate" action="<?php echo esc_url( Hatedetect_Admin::get_page_url() ); ?>"
              method="POST">
            <div class="hatedetect_activate">
                <div class="aa_button_container">
                    <div class="aa_button_border">
                        <input type="submit" class="aa_button"
                               value="<?php esc_attr_e( 'Set up your HateDetect configuration', 'hatedetect' ); ?>"/>
                    </div>
                </div>
                <div class="aa_description"><?php _e( '<strong>Almost done</strong> - configure HateDetect and say goodbye to hate', 'hatedetect' ); ?></div>
            </div>
        </form>
    </div>
<?php elseif ( $type == 'hate-check' ) : ?>
    <div class="notice notice-warning">
        <p><strong><?php esc_html_e( 'HateDetect has detected a problem.', 'hatedetect' ); ?></strong></p>
        <p><?php esc_html_e( 'Some comments have not yet been checked for hate by HateDetect. They have been temporarily held for moderation and will automatically be rechecked later.', 'hatedetect' ); ?></p>
		<?php if ( !empty($link_text) ) { ?>
            <p><?php echo esc_url($link_text); ?></p>
		<?php } ?>
    </div>
<?php elseif ( $type == 'alert' ) : ?>
    <div class='error'>
        <p><strong><?php printf( esc_html__( 'HateDetect Error Code: %s', 'hatedetect' ), $code ); ?></strong></p>
        <p><?php echo esc_html( $msg ); ?></p>
        <p><?php

			/* translators: the placeholder is a clickable URL that leads to more information regarding an error code. */
			printf( esc_html__( 'For more information: %s', 'hatedetect' ), '<a href="https://hatedetect.com/errors/' . $code . '">https://hatedetect.com/errors/' . $code . '</a>' );

			?>
        </p>
    </div>
<?php elseif ( $type == 'notice' ) : ?>
    <div class="hatedetect-alert hatedetect-critical">
        <h3 class="hatedetect-key-status failed"><?php echo esc_attr($notice_header); ?></h3>
        <p class="hatedetect-description">
			<?php echo esc_attr($notice_text); ?>
        </p>
    </div>
<?php elseif ( $type == 'connection-error' ) : ?>
    <div class="hatedetect-alert hatedetect-critical">
        <h3 class="hatedetect-key-status failed"><?php esc_html_e( 'Your site encountered an error when connecting to the HateDetect servers.', 'hatedetect' ); ?></h3>
        <p class="hatedetect-description">
			<?php echo esc_attr($notice_text); ?>
        </p>
    </div>
<?php elseif ( $type == 'servers-be-down' ) : ?>
    <div class="hatedetect-alert hatedetect-critical">
        <h3 class="hatedetect-key-status failed"><?php esc_html_e( 'Your site can&#8217;t connect to the HateDetect servers.', 'hatedetect' ); ?></h3>
        <p class="hatedetect-description"><?php printf( __( 'Your firewall may be blocking HateDetect from connecting to its API.', 'hatedetect' ), ); ?></p>
    </div>
<?php elseif ( $type == 'missing' ) : ?>
    <div class="hatedetect-alert hatedetect-critical">
        <h3 class="hatedetect-key-status failed"><?php esc_html_e( 'There is a problem with your API key.', 'hatedetect' ); ?></h3>
    </div>
<?php elseif ( $type == 'activated' ) : ?>
    <div class="hatedetect-alert hatedetect-active">
        <h3 class="hatedetect-key-status"><?php esc_html_e( 'HateDetect is now protecting your site from hate. Happy blogging!', 'hatedetect' ); ?></h3>
    </div>
<?php elseif ( $type == 'new-key-invalid' ) : ?>
    <div class="hatedetect-alert hatedetect-critical">
        <h3 class="hatedetect-key-status"><?php esc_html_e( 'The key you entered is invalid. Please double-check it.', 'hatedetect' ); ?></h3>
    </div>
<?php elseif ( $type == 'new-key-empty' ) : ?>
    <div class="hatedetect-alert hatedetect-critical">
        <h3 class="hatedetect-key-status"><?php esc_html_e( 'Enter a key to proceed.', 'hatedetect' ); ?></h3>
    </div>
<?php endif; ?>

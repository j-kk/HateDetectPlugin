<?php

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.

?>
<?php if ( $type == 'plugin' ) :?>
<div class="updated" id="hatedetect_setup_prompt">
	<form name="hatedetect_activate" action="<?php echo esc_url( Hatedetect_Admin::get_page_url() ); ?>" method="POST">
		<div class="hatedetect_activate">
			<div class="aa_button_container">
				<div class="aa_button_border">
					<input type="submit" class="aa_button" value="<?php esc_attr_e( 'Set up your HateDetect account', 'hatedetect' ); ?>" />
				</div>
			</div>
			<div class="aa_description"><?php _e('<strong>Almost done</strong> - configure HateDetect and say goodbye to hate', 'hatedetect');?></div>
		</div>
	</form>
</div>
<?php elseif ( $type == 'hate-check' ) :?>
<div class="notice notice-warning">
	<p><strong><?php esc_html_e( 'HateDetect has detected a problem.', 'hatedetect' );?></strong></p>
	<p><?php esc_html_e( 'Some comments have not yet been checked for hate by HateDetect. They have been temporarily held for moderation and will automatically be rechecked later.', 'hatedetect' ); ?></p>
	<?php if ( $link_text ) { ?>
		<p><?php echo $link_text; ?></p>
	<?php } ?>
</div>
<?php elseif ( $type == 'alert' ) :?>
<div class='error'>
	<p><strong><?php printf( esc_html__( 'HateDetect Error Code: %s', 'hatedetect' ), $code ); ?></strong></p>
	<p><?php echo esc_html( $msg ); ?></p>
	<p><?php

	/* translators: the placeholder is a clickable URL that leads to more information regarding an error code. */
	printf( esc_html__( 'For more information: %s' , 'hatedetect'), '<a href="https://hatedetect.com/errors/' . $code . '">https://hatedetect.com/errors/' . $code . '</a>' );

	?>
	</p>
</div>
<?php elseif ( $type == 'notice' ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<h3 class="hatedetect-key-status failed"><?php echo $notice_header; ?></h3>
	<p class="hatedetect-description">
		<?php echo $notice_text; ?>
	</p>
</div>
<?php elseif ( $type == 'missing-functions' ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<h3 class="hatedetect-key-status failed"><?php esc_html_e('Network functions are disabled.', 'hatedetect'); ?></h3>
	<p class="hatedetect-description"><?php printf( __('Your web host or server administrator has disabled PHP&#8217;s <code>gethostbynamel</code> function.  <strong>HateDetect cannot work correctly until this is fixed.</strong>  Please contact your web host or firewall administrator and give them <a href="%s" target="_blank">this information about HateDetect&#8217;s system requirements</a>.', 'hatedetect'), 'https://blog.hatedetect.com/hatedetect-hosting-faq/'); ?></p>
</div>
<?php elseif ( $type == 'servers-be-down' ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<h3 class="hatedetect-key-status failed"><?php esc_html_e("Your site can&#8217;t connect to the HateDetect servers.", 'hatedetect'); ?></h3>
	<p class="hatedetect-description"><?php printf( __('Your firewall may be blocking HateDetect from connecting to its API.', 'hatedetect'), ); ?></p>
</div>
<?php elseif ( $type == 'active-notice' && $time_saved ) :?>
<div class="hatedetect-alert hatedetect-active">
	<h3 class="hatedetect-key-status"><?php echo esc_html( $time_saved ); ?></h3>
	<p class="hatedetect-description"><?php printf( __('You can help us fight hate and upgrade your account by <a href="%s" target="_blank">contributing a token amount</a>.', 'hatedetect'), 'https://hatedetect.com/account/upgrade/'); ?></p>
</div>
<?php elseif ( $type == 'missing' ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<h3 class="hatedetect-key-status failed"><?php esc_html_e( 'There is a problem with your API key.', 'hatedetect'); ?></h3>
	<p class="hatedetect-description"><?php printf( __('Please contact <a href="%s" target="_blank">HateDetect support</a> for assistance.', 'hatedetect'), 'https://hatedetect.com/contact/'); ?></p>
</div>
<?php elseif ( $type == 'no-sub' ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<h3 class="hatedetect-key-status failed"><?php esc_html_e( 'You don&#8217;t have an HateDetect plan.', 'hatedetect'); ?></h3>
	<p class="hatedetect-description">
		<?php printf( __( 'In 2012, HateDetect began using subscription plans for all accounts (even free ones). A plan has not been assigned to your account, and we&#8217;d appreciate it if you&#8217;d <a href="%s" target="_blank">sign into your account</a> and choose one.', 'hatedetect'), 'https://hatedetect.com/account/upgrade/' ); ?>
		<br /><br />
		<?php printf( __( 'Please <a href="%s" target="_blank">contact our support team</a> with any questions.', 'hatedetect' ), 'https://hatedetect.com/contact/' ); ?>
	</p>
</div>
<?php elseif ( $type == 'new-key-valid' ) :
	global $wpdb;
	
	$check_pending_link = false;
	
	$at_least_one_comment_in_moderation = !! $wpdb->get_var( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = '0' LIMIT 1" );
	
	if ( $at_least_one_comment_in_moderation)  {
		$check_pending_link = 'edit-comments.php?hatedetect_recheck=' . wp_create_nonce( 'hatedetect_recheck' );
	}
	
	?>
<div class="hatedetect-alert hatedetect-active">
	<h3 class="hatedetect-key-status"><?php esc_html_e( 'HateDetect is now protecting your site from hate. Happy blogging!', 'hatedetect' ); ?></h3>
	<?php if ( $check_pending_link ) { ?>
		<p class="hatedetect-description"><?php printf( __( 'Would you like to <a href="%s">check pending comments</a>?', 'hatedetect' ), esc_url( $check_pending_link ) ); ?></p>
	<?php } ?>
</div>
<?php elseif ( $type == 'new-key-invalid' ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<h3 class="hatedetect-key-status"><?php esc_html_e( 'The key you entered is invalid. Please double-check it.' , 'hatedetect'); ?></h3>
</div>
<?php elseif ( $type == 'existing-key-invalid' ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<h3 class="hatedetect-key-status"><?php echo esc_html( __( 'Your API key is no longer valid.' , 'hatedetect' ) ); ?></h3>
	<p class="hatedetect-description"><?php printf( __( 'Please enter a new key or <a href="%s" target="_blank">contact HateDetect support</a>.' , 'hatedetect' ), 'https://hatedetect.com/contact/' ); ?></p>
</div>
<?php elseif ( $type == 'new-key-failed' ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<h3 class="hatedetect-key-status"><?php esc_html_e( 'The API key you entered could not be verified.' , 'hatedetect'); ?></h3>
	<p class="hatedetect-description"><?php printf( __('The connection to hatedetect.com could not be established. Please refer to <a href="%s" target="_blank">our guide about firewalls</a> and check your server configuration.', 'hatedetect'), 'https://blog.hatedetect.com/hatedetect-hosting-faq/'); ?></p>
</div>
<?php elseif ( $type == 'limit-reached' && in_array( $level, array( 'yellow', 'red' ) ) ) :?>
<div class="hatedetect-alert hatedetect-critical">
	<?php if ( $level == 'yellow' ): ?>
	<h3 class="hatedetect-key-status failed"><?php esc_html_e( 'You&#8217;re using your HateDetect key on more sites than your Plus subscription allows.', 'hatedetect' ); ?></h3>
	<p class="hatedetect-description">
		<?php printf( __( 'Your Plus subscription allows the use of HateDetect on only one site. Please <a href="%s" target="_blank">purchase additional Plus subscriptions</a> or upgrade to an Enterprise subscription that allows the use of HateDetect on unlimited sites.', 'hatedetect' ), 'https://docs.hatedetect.com/billing/add-more-sites/' ); ?>
		<br /><br />
		<?php printf( __( 'Please <a href="%s" target="_blank">contact our support team</a> with any questions.', 'hatedetect' ), 'https://hatedetect.com/contact/'); ?>
	</p>
	<?php elseif ( $level == 'red' ): ?>
	<h3 class="hatedetect-key-status failed"><?php esc_html_e( 'You&#8217;re using HateDetect on far too many sites for your Plus subscription.', 'hatedetect' ); ?></h3>
	<p class="hatedetect-description">
		<?php printf( __( 'To continue your service, <a href="%s" target="_blank">upgrade to an Enterprise subscription</a>, which covers an unlimited number of sites.', 'hatedetect'), 'https://hatedetect.com/account/upgrade/' ); ?>
		<br /><br />
		<?php printf( __( 'Please <a href="%s" target="_blank">contact our support team</a> with any questions.', 'hatedetect' ), 'https://hatedetect.com/contact/'); ?>
	</p>
	<?php endif; ?>
</div>
<?php endif;?>

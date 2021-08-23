<div class="hatedetect-setup-instructions">
	<p><?php esc_html_e( 'Set up your Hatedetect account to enable hate filtering on this site.', 'hatedetect' ); ?></p>
	<?php Hatedetect::view( 'get', array( 'text' => __( 'Set up your Hatedetect account' , 'hatedetect' ), 'classes' => array( 'hatedetect-button', 'hatedetect-is-primary' ) ) ); ?>
</div>

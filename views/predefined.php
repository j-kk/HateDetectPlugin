<div class="hatedetect-box">
	<h2><?php esc_html_e( 'Manual Configuration', 'hatedetect' ); ?></h2>
	<p>
		<?php

		/* translators: %s is the wp-config.php file */
		echo sprintf( esc_html__( 'An Hatedetect API key has been defined in the %s file for this site.', 'hatedetect' ), '<code>wp-config.php</code>' );

		?>
	</p>
</div>
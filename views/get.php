<?php

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.

?>
<form name="hatedetect_activate" action="https://hatedetect.com/get/" method="POST" target="_blank">
	<input type="hidden" name="passback_url" value="<?php echo esc_url( Hatedetect_Admin::get_page_url() ); ?>"/>
	<input type="hidden" name="blog" value="<?php echo esc_url( get_option( 'home' ) ); ?>"/>
	<input type="hidden" name="redirect" value="<?php echo isset( $redirect ) ? $redirect : 'plugin-signup'; ?>"/>
	<input type="submit" class="<?php echo isset( $classes ) && count( $classes ) > 0 ? implode( ' ', $classes ) : 'hatedetect-button';?>" value="<?php echo esc_attr( $text ); ?>"/>
</form>
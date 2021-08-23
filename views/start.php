<?php

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.

?>
<div id="hatedetect-plugin-container">
    <div class="hatedetect-masthead">
        <div class="hatedetect-masthead__inside-container">
            <div class="hatedetect-masthead__logo-container">
                <img class="hatedetect-masthead__logo" src="<?php echo esc_url( plugins_url( '../_inc/img/logo-full-2x.png', __FILE__ ) ); ?>" alt="Hatedetect" />
            </div>
        </div>
    </div>
	<div class="hatedetect-lower">
		<?php Hatedetect_Admin::display_status();?>
		<div class="hatedetect-boxes">
			<?php
			if ( HateDetect::predefined_api_key() ) {
				HateDetect::view( 'predefined' );
			} else {
				HateDetect::view( 'activate' );
			}

			?>
		</div>
	</div>
</div>
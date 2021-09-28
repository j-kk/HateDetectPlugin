<?php

//phpcs:disable VariableAnalysis
// There are "undefined" variables here because they're defined in the code that includes this file as a template.

?>
<div id="hatedetect-plugin-container">
    <div class="hatedetect-masthead">
        <div class="hatedetect-masthead__inside-container">
            <div class="hatedetect-masthead__logo-container">
                HateDetect
            </div>
        </div>
    </div>
    <div class="hatedetect-lower">
		<?php Hatedetect_Admin::display_api_key_warning(); ?>
        <div class="hatedetect-boxes">
			<?php
			HateDetect::view( 'enter' );
			?>
        </div>
    </div>
</div>
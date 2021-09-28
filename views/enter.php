<div class="hatedetect-box">
    <div class="hatedetect-enter-api-key-box centered">
        <div class="enter-api-key">
            <form action="<?php echo esc_url( Hatedetect_Admin::get_page_url() ); ?>" method="post">
				<?php wp_nonce_field( Hatedetect_Admin::NONCE ) ?>
                <input type="hidden" name="action" value="enter-key">
                <p style="width: 100%; display: flex; flex-wrap: nowrap; box-sizing: border-box;">
                    <input id="key" name="key" type="text" size="15" value=""
                           placeholder="<?php esc_attr_e( 'Enter your API key', 'hatedetect' ); ?>"
                           class="regular-text code" style="flex-grow: 1; margin-right: 1rem;">
                    <input type="submit" name="submit" id="submit" class="hatedetect-button"
                           value="<?php esc_attr_e( 'Connect with API key', 'hatedetect' ); ?>">
                </p>
                <br>
                <p>
                    HateDetect plugin is currently in beta stage. Please contact us using the contact info on site:
                    <a href="https://www.codeagainsthate.eu/">CodeAgainstHate</a>
                    to obtain access key. There are no fees for using the plugin.
                </p>
            </form>
        </div>
    </div>
</div>
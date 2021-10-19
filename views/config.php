<div id="hatedetect-plugin-container">
    <div class="hatedetect-masthead">
        <div class="hatedetect-masthead__inside-container">
            <div class="hatedetect-masthead__logo-container">
                HateDetect
            </div>
        </div>
    </div>
    <div class="hatedetect-lower">
	    <?php Hatedetect_Admin::display_status(); ?>
		<?php if ( ! empty( $notices ) ) { ?>
			<?php foreach ( $notices as $notice ) { ?>
				<?php HateDetect::view( 'notice', $notice ); ?>
			<?php } ?>
		<?php } ?>

        <div class="hatedetect-card">
            <div class="hatedetect-section-header">
                <div class="hatedetect-section-header__label">
                    <span><?php esc_html_e( 'Settings', 'hatedetect' ); ?></span>
                </div>
            </div>

            <div class="inside">
                <form action="<?php echo esc_url( HateDetect_Admin::get_page_url() ); ?>" method="POST">
                    <table cellspacing="0" class="hatedetect-settings">
                        <tbody>
                        <tr>
                            <th class="hatedetect-api-key" width="10%" align="left"
                                scope="row"><?php esc_html_e( 'API Key', 'hatedetect' ); ?></th>
                            <td width="5%"/>
                            <td align="left">
                                    <span class="api-key"><input id="key" name="key" type="text" size="15"
                                                                 value="<?php echo esc_attr( get_option( 'hatedetect_api_key' ) ); ?>"
                                                                 class="<?php echo esc_attr( 'regular-text code ' ); ?>">
                                    </span>
                                <span class='hatedetect-key-status'>Status: <?php echo esc_attr( HateDetect_ApiKey::get_key_status() ); ?>
                                    </span>
                            </td>
                        </tr>
						<?php if ( isset( $_GET['ssl_status'] ) ) { ?>
                            <tr>
                                <th align="left" scope="row"><?php esc_html_e( 'SSL Status', 'hatedetect' ); ?></th>
                                <td></td>
                                <td align="left">
                                    <p>
										<?php

										if ( ! wp_http_supports( array( 'ssl' ) ) ) {
											?>
                                            <b><?php esc_html_e( 'Disabled.', 'hatedetect' ); ?></b> <?php esc_html_e( 'Your Web server cannot make SSL requests; contact your Web host and ask them to add support for SSL requests.', 'hatedetect' ); ?><?php
										} else {
											$ssl_disabled = get_option( 'hatedetect_ssl_disabled' );

											if ( $ssl_disabled ) {
												?>
                                                <b><?php esc_html_e( 'Temporarily disabled.', 'hatedetect' ); ?></b> <?php esc_html_e( 'HateDetect encountered a problem with a previous SSL request and disabled it temporarily. It will begin using SSL for requests again shortly.', 'hatedetect' ); ?><?php
											} else {
												?>
                                                <b><?php esc_html_e( 'Enabled.', 'hatedetect' ); ?></b> <?php esc_html_e( 'All systems functional.', 'hatedetect' ); ?><?php
											}
										}

										?>
                                    </p>
                                </td>
                            </tr>
						<?php } ?>
                        <tr>
                            <th align="left" scope="row"><?php esc_html_e( 'Comments', 'hatedetect' ); ?></th>
                            <td></td>
                            <td align="left">
                                <p>
                                    <label for="hatedetect_auto_allow"
                                           title="<?php esc_attr_e( 'Automatically allow comments without hate.', 'hatedetect' ); ?>">
                                        <input
                                                name="hatedetect_auto_allow"
                                                id="hatedetect_auto_allow"
                                                value="1"
                                                type="checkbox"
											<?php

											// If the option isn't set, or if it's enabled ('1'), or if it was enabled a long time ago ('true'), check the checkbox.
											checked( true, ( in_array( get_option( 'hatedetect_auto_allow' ), array(
												false,
												'1',
												'true'
											), true ) ) );

											?>
                                        />
										<?php esc_html_e( 'Automatically allow comments without hate.', 'hatedetect' ); ?>
                                        <br>
                                    </label>
                                    <label for="hatedetect_auto_discard"
                                           title="<?php esc_attr_e( 'Automatically discard to trash hateful comments.', 'hatedetect' ); ?>">
                                        <input
                                                name="hatedetect_auto_discard"
                                                id="hatedetect_auto_discard"
                                                value="1"
                                                type="checkbox"
											<?php

											// If the option isn't set, or if it's enabled ('1'), or if it was enabled a long time ago ('true'), check the checkbox.
											checked( true, ( in_array( get_option( 'hatedetect_auto_discard' ), array(
												false,
												'1',
												'true'
											), true ) ) );

											?>
                                        />
										<?php esc_html_e( 'Automatically discard to trash hateful comments.', 'hatedetect' ); ?>
                                        <br>
                                    </label>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th align="left" scope="row"><?php esc_html_e( 'Notifications', 'hatedetect' ); ?></th>
                            <td></td>
                            <td align="left">
                                <p>
                                    <label for="hatedetect_notify_user"
                                           title="<?php esc_attr_e( 'Notify users in email about comment rejection (because of hate speech) (additional setup required, see help).', 'hatedetect' ); ?>">
                                        <input
                                                name="hatedetect_notify_user"
                                                id="hatedetect_notify_user"
                                                value="1"
                                                type="checkbox"
											<?php

											// If the option isn't set, or if it's enabled ('1'), or if it was enabled a long time ago ('true'), check the checkbox.
											checked( true, ( in_array( get_option( 'hatedetect_notify_user' ), array(
												false,
												'1',
												'true'
											), true ) ) );

											?>
                                        />
										<?php esc_html_e( 'Notify users in email about comment rejection (because of hate speech).', 'hatedetect' ); ?>
                                        <br>
                                    </label>

                                    <label for="hatedetect_show_comment_field_message"
                                           title="<?php esc_attr_e( 'Notify users after submitting comment about rejection (because of hate speech).', 'hatedetect' ); ?>">
                                        <input
                                                name="hatedetect_show_comment_field_message"
                                                id="hatedetect_show_comment_field_message"
                                                value="1"
                                                type="checkbox"
											<?php

											// If the option isn't set, or if it's enabled ('1'), or if it was enabled a long time ago ('true'), check the checkbox.
											checked( true, ( in_array( get_option( 'hatedetect_show_comment_field_message' ), array(
												false,
												'1',
												'true'
											), true ) ) );

											?>
                                        />
										<?php esc_html_e( 'Notify users after submitting comment about rejection (because of hate speech).', 'hatedetect' ); ?>
                                        <br>
                                    </label>

                                    <label for="hatedetect_notify_moderator"
                                           title="<?php esc_attr_e( 'Send an email to moderator when hateful comment has been detected.', 'hatedetect' ); ?>">
                                        <input
                                                name="hatedetect_notify_moderator"
                                                id="hatedetect_notify_moderator"
                                                value="1"
                                                type="checkbox"
											<?php

											// If the option isn't set, or if it's enabled ('1'), or if it was enabled a long time ago ('true'), check the checkbox.
											checked( true, ( in_array( get_option( 'hatedetect_notify_moderator' ), array(
												false,
												'1',
												'true'
											), true ) ) );

											?>
                                        />
										<?php esc_html_e( 'Send an email to moderator when hateful comment has been detected.', 'hatedetect' ); ?>
                                        <br>
                                    </label>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th align="left" scope="row"><?php esc_html_e( 'Language', 'hatedetect' ); ?></th>
                            <td></td>
                            <td><select name="hatedetect_lang">
									<?php foreach ( HateDetect_Admin::SUPPORTED_LANGS as $lang => $langFullName ) { ?>
										<?php if ( get_option( 'hatedetect_lang' ) === $lang ) {
											echo wp_kses_decode_entities("<option value='$lang' selected='selected'>" . $langFullName . "</option>");
										} else {
											echo wp_kses_decode_entities("<option value='$lang'>" . $langFullName . "</option>");
										}
										?>
									<?php } ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="comment-form-privacy-notice" align="left"
                                scope="row"><?php esc_html_e( 'Privacy', 'hatedetect' ); ?></th>
                            <td></td>
                            <td align="left">
                                <fieldset>
                                    <legend class="screen-reader-text">
                                        <span><?php esc_html_e( 'HateDetect privacy notice', 'hatedetect' ); ?></span>
                                    </legend>
                                    <p><label for="hatedetect_comment_form_privacy_notice_display"><input type="radio"
                                                                                                          name="hatedetect_comment_form_privacy_notice"
                                                                                                          id="hatedetect_comment_form_privacy_notice_display"
                                                                                                          value="display" <?php checked( 'display', get_option( 'hatedetect_comment_form_privacy_notice' ) ); ?> /> <?php esc_html_e( 'Display a privacy notice under your comment forms.', 'hatedetect' ); ?>
                                        </label></p>
                                    <p><label for="hatedetect_comment_form_privacy_notice_hide"><input type="radio"
                                                                                                       name="hatedetect_comment_form_privacy_notice"
                                                                                                       id="hatedetect_comment_form_privacy_notice_hide"
                                                                                                       value="hide" <?php echo in_array( get_option( 'hatedetect_comment_form_privacy_notice' ), array(
												'display',
												'hide'
											) ) ? checked( 'hide', get_option( 'hatedetect_comment_form_privacy_notice' ), false ) : 'checked="checked"'; ?> /> <?php esc_html_e( 'Do not display privacy notice.', 'hatedetect' ); ?>
                                        </label></p>
                                </fieldset>
                                <span class="hatedetect-note"><?php esc_html_e( 'To help your site with transparency under privacy laws like the GDPR, HateDetect can display a notice to your users under your comment forms. This feature is disabled by default, however, you can turn it on above.', 'hatedetect' ); ?></span>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <div class="hatedetect-card-actions">

						<?php wp_nonce_field( HateDetect_Admin::NONCE ) ?>
                        <div id="publishing-action">
                            <input type="hidden" name="action" value="enter-key">
                            <input type="submit" name="submit" id="submit"
                                   class="hatedetect-button hatedetect-could-be-primary"
                                   value="<?php esc_attr_e( 'Save Changes', 'hatedetect' ); ?>">
                        </div>
                        <div class="clear"></div>
                    </div>
                </form>
            </div>
        </div>
        <div class="hatedetect-card">
            <div class="hatedetect-section-header">
                <div class="hatedetect-section-header__label">
                    <span><?php esc_html_e( 'Stats', 'hatedetect' ); ?></span>
                </div>
            </div>

            <div class="inside">
                <!--                    <div class="hatedetect-new-snapshot">-->
                <ul>
                    <li>
                        <h3><?php esc_html_e( 'Comments approved', 'hatedetect' ); ?></h3>
                        <span><?php echo number_format( HateDetect_Admin::get_user_comments_approved() ); ?></span>
                    </li>
                    <li>
                        <h3><?php esc_html_e( 'Comments rejected', 'hatedetect' ); ?></h3>
                        <span><?php echo number_format( HateDetect_Admin::get_user_comments_rejected() ); ?></span>
                    </li>
                    <li>
                        <h3><?php esc_html_e( 'Comments queued', 'hatedetect' ); ?></h3>
                        <span><?php echo number_format( HateDetect_Admin::get_user_comments_queued() ); ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

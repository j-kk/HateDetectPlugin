<?php

class HateDetect_ApiKey {

	/**
     * Retrieves hatedetect api key. False if not found
	 *
     * @return false|string
	 */
	public static function get_api_key() {
		# If key is set, but wrong
		if ( !in_array(get_option( 'hatedetect_key_status' ), ['OK', 'Activated']) && get_option( 'hatedetect_api_key' ) ) {
			# Check if still wrong
			if ( self::check_key_status( get_option( 'hatedetect_api_key' ) ) ) {
				return get_option( 'hatedetect_api_key' );
			} else {
				return false;
			}
		}

		return get_option( 'hatedetect_api_key' );
	}

	/**
	 * Checking api key status.
	 * Api key is necessary to connect with hate detection model.
	 *
	 * @param string $api_key api key
	 *
	 * @return bool|null true if key is valid, else false, when unable to connect returns null.
	 */
	public static function check_key_status( string $api_key ) {
		HateDetect::log( 'Verifying key: ' . $api_key );
		$response = HateDetect::http_post( [], 'isalive', null, false, $api_key );

		if ( $response && is_int( $response[1] ) ) {
			if (200 <= $response[1] && $response[1] < 300 && 'OK' == $response[2] ) {
				update_option( 'hatedetect_key_status', 'OK' );
				HateDetect::manual_schedule_cron_recheck(15);
				return true;
			} elseif ($response[1] == 403 || $response[2] != 'OK' ) {
				update_option( 'hatedetect_key_status', 'Failed' );
				HateDetect::log( 'Failed to verify key: ' . wp_json_encode( $response ) );
				return false;
			}
		}
		return null;
	}

	/**
	 * Checks key status.
	 *
	 * @return string result of check of api key.
	 */
	public static function get_key_status(): string
	{
		if (get_option('hatedetect_key_status') === false) {
			self::check_key_status(HateDetect_ApiKey::get_api_key());
		} elseif (get_option('hatedetect_key_status') != 'OK') {
			self::check_key_status(HateDetect_ApiKey::get_api_key());
		}

		return get_option('hatedetect_key_status') ? get_option('hatedetect_key_status') : 'Unknown';
	}

	/**
     * Verifies either if key is correct or not.
	 * When key is correct, it overrides current key (or sets it if unset).
	 *
	 * @param string $key to validate.
	 *
	 * @return bool|null true if key is valid, else false, when unable to connect returns null.
	 */
	public static function verify_key( string $key ) {
		$old_key  = get_option( 'hatedetect_api_key' );
		$response = HateDetect::http_post( [], 'isalive', null, false, $key );

		if ( $response && is_int( $response[1] )) {
			if (200 <= $response[1] && $response[1] < 300 && 'OK' == $response[2] ) {

				update_option( 'hatedetect_api_key', $key );
				if ( $old_key == false ) {
					update_option( 'hatedetect_key_status', 'Activated' );
				}
				return true;
			} elseif ($response[1] == 403 || $response[2] != 'OK' ) {
				update_option( 'hatedetect_key_status', 'Failed' );
				HateDetect::log( 'Failed to verify key: ' . wp_json_encode( $response ) );
				return false;
			}

		}
		return null;
	}
}
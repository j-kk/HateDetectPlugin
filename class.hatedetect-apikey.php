<?php

class HateDetect_ApiKey {


	/**
     * Retrieves hatedetect api key. False if not found
	 *
     * @return false|string
	 */
	public static function get_api_key(): bool|string {
		# If key is set, but wrong
		if ( get_option( 'hatedetect_key_status' ) != 'OK' && get_option( 'hatedetect_api_key' ) ) {
			# Check if still wrong
			if ( self::verify_key( get_option( 'hatedetect_api_key' ) ) ) {
				return get_option( 'hatedetect_api_key' );
			} else {
				return false;
			}
		}

		return get_option( 'hatedetect_api_key' );
	}


	/**
     * Verifies either if key is correct or not.
	 * When key is correct, it overrides current key (or sets it if unset).
	 *
	 * @param string $key to validate.
	 *
	 * @return bool result of the key validation
	 */
	public static function verify_key( string $key ): bool {
		$old_key  = get_option( 'hatedetect_api_key' );
		$response = HateDetect::http_post( [], 'isalive', null, false, $key );

		if ( $response && is_int( $response[1] ) && 200 <= $response[1] && $response[1] < 300 && 'OK' == $response[2] ) {
			update_option( 'hatedetect_api_key', $key );
			if ( $old_key == false ) {
				update_option( 'hatedetect_key_status', 'Activated' );
			}

			return true;
		} else {
			update_option( 'hatedetect_key_status', 'Failed' );
			delete_option( 'hatedetect_api_key' );
			HateDetect::log( 'Failed to verify key: ' . wp_json_encode( $response ) );

			return false;
		}
	}
}
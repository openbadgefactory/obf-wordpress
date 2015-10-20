<?php
/**
 * Get our plugin's directory path
 *
 * @since 1.0.0
 * @return string The filepath of the BadgeOS plugin root directory
 */
function badgeos_get_directory_path() {
	return $GLOBALS['badgeos']->directory_path;
}

/**
 * Get our plugin's directory URL
 *
 * @since 1.0.0
 * @return string The URL for the BadgeOS plugin root directory
 */
function badgeos_get_directory_url() {
	return $GLOBALS['badgeos']->directory_url;
}

/**
 * Check if debug mode is enabled
 *
 * @since  1.0.0
 * @return bool True if debug mode is enabled, false otherwise
 */
function badgeos_is_debug_mode() {

	//get setting for debug mode
	$badgeos_settings = badgeos_obf_get_settings();
	$debug_mode = ( !empty( $badgeos_settings['debug_mode'] ) ) ? $badgeos_settings['debug_mode'] : 'disabled';

	if ( $debug_mode == 'enabled' ) {
		return true;
	}

	return false;

}

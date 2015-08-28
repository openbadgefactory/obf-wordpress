<?php

/**
 * Open Badge Factory user achievement support for the widget
 *
 * @package BadgeOS
 * @subpackage OBF
 * @author Discendum Oy
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://openbadgefactory.com
 */

add_action( 'wp_ajax_achievement_send_to_obf', 'badgeos_send_to_obf_handler' );
add_action( 'wp_ajax_nopriv_achievement_send_to_obf', 'badgeos_send_to_obf_handler' );
/**
 * hook in our credly ajax function
 */
function badgeos_send_to_obf_handler() {

	if ( ! isset( $_REQUEST['ID'] ) ) {
		echo json_encode( sprintf( '<strong class="error">%s</strong>', __( 'Error: Sorry, nothing found.', 'badgeos' ) ) );
		die();
	}

	$send_to_obf = $GLOBALS['badgeos_obf']->post_obf_user_badge( get_current_user_id(), $_REQUEST['ID'] );

	if ( $send_to_obf ) {

		echo json_encode( sprintf( '<strong class="success">%s</strong>', __( 'Success: Sent to Open Badge Factory!', 'badgeos' ) ) );
		die();

	} else {

		echo json_encode( sprintf( '<strong class="error">%s</strong>', __( 'Error: Sorry, Send to Open Badge Factory Failed.', 'badgeos' ) ) );
		die();

	}
}
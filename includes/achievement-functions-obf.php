<?php

/**
 * Open Badge Factory achievement functions
 *
 * @package BadgeOS
 * @subpackage OBF
 * @author Discendum Oy
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://openbadgefactory.com
 */

/**
 * Attempt to send an achievement to Open Badge Factory if the user has send enabled
 *
 * @since 1.0.0
 *
 * @param int $user_id        The ID of the user earning the achievement
 * @param int $achievement_id The ID of the achievement being earned
 */
function obf_issue_badge( $user_id, $achievement_id ) {

	if ( 'true' === $GLOBALS['badgeos_obf']->user_enabled ) {

		$GLOBALS['badgeos_obf']->post_obf_user_badge( $user_id, $achievement_id );

	}

}
add_action( 'badgeos_award_achievement', 'obf_issue_badge', 10, 2 );
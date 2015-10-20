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


/**
 * Destroy the points-based achievements transient if we edit a points-based achievement
 *
 * @since 1.0.0
 * @param integer $post_id The given post's ID
 */
function badgeos_obf_remove_multiple_obf_badge_types( $post_id, $force = false ) {

	$post = get_post($post_id);

	// If the post is one of our achievement types,
	// and the achievement is awarded by minimum points
	if (
		!empty($post) && $post->post_type == 'achievement-type'
		&& (
                        $force ||
			get_post_meta( $post_id, '_badgeos_use_obf_badges', true )
		)
	) {
                
		$achievement_types = get_posts( array(
                    'post_type'      =>	'achievement-type',
                    'posts_per_page' =>	-1,
                ) );
                foreach ( $achievement_types as $achievement_type ) {
                    if ($achievement_type->ID != $post_id) {
                        update_post_meta( $achievement_type->ID, '_badgeos_use_obf_badges', false );
                    }
                }
	}
}
function badgeos_obf_remove_multiple_obf_badge_types_update( $data = array(), $post_args = array() ) {
    $post_id =  $post_args['ID'];
    $force = (array_key_exists('_badgeos_use_obf_badges', $post_args));
    badgeos_obf_remove_multiple_obf_badge_types($post_id, $force);
    
    return $data;
}
add_filter( 'wp_insert_post_data' , 'badgeos_obf_remove_multiple_obf_badge_types_update' , '50', 2 );
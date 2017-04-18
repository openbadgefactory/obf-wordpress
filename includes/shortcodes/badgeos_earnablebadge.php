<?php
/**
 * Register the [badgeos_achievement] shortcode.
 *
 * @since 1.4.0
 */
function badgeos_register_earnable_badge_shortcode() {
	badgeos_register_shortcode( array(
		'name'            => __( 'Single Earnable Badge', 'badgeos' ),
		'slug'            => 'obf_earnable_badge',
		'output_callback' => 'badgeos_earnable_badge_shortcode',
		'description'     => __( 'Render a single earnable badge.', 'badgeos' ),
		'attributes'      => array(
			'earnable_id' => array(
				'name'        => __( 'Earnable ID', 'badgeos' ),
				'description' => __( 'The ID of the earnable badge to render.', 'badgeos' ),
				'type'        => 'text',
				),
      'prefill' => array(
				'name'        => __( 'Prefill', 'badgeos' ),
				'description' => __( 'Prefill user details.', 'badgeos' ),
				'type'        => 'select',
				'values'      => array(
					'true'  => __( 'True', 'badgeos' ),
					'false' => __( 'False', 'badgeos' )
					),
				'default'     => 'true',
				),
      'iframe' => array(
				'name'        => __( 'iframe', 'badgeos' ),
				'description' => __( 'Embed in an iframe', 'badgeos' ),
				'type'        => 'select',
				'values'      => array(
					'true'  => __( 'True', 'badgeos' ),
					'false' => __( 'False', 'badgeos' )
					),
				'default'     => 'false',
				),
		),
	) );
}
add_action( 'init', 'badgeos_register_earnable_badge_shortcode' );

/**
 * Single Achievement Shortcode.
 *
 * @since  1.0.0
 *
 * @param  array $atts Shortcode attributes.
 * @return string 	   HTML markup.
 */
function badgeos_earnable_badge_shortcode( $atts = array() ) {
  error_log("earnable badge shortcode "  . var_export($atts, true));

	// get the post id
	$atts = shortcode_atts( array(
	  'id' => get_the_ID(),
    'earnable_id' => '',
    'prefill' => false,
    'iframe' => false,
	), $atts, 'obf_earnable_badge' );

	// return if post id not specified
	if ( empty($atts['id']) )
	  return;

	wp_enqueue_style( 'badgeos-front' );
	wp_enqueue_script( 'badgeos-earnable' );

	// get the post content and format the badge display
  error_log("atts: " . var_export($atts, true));
	$earnable = $atts['earnable_id'];
	$output = '';

	// If we're dealing with an earnable badge post
	if ( !empty($earnable) ) {
		$output .= '<div id="badgeos-single-earnable-badge-container" class="badgeos-single-earnable-badge">';  // necessary for the jquery click handler to be called
		$output .= badgeos_render_earnable_badge( $earnable, $atts );
		$output .= '</div>';
	}

	// Return our rendered earnable badge
	return $output;
}

function badgeos_register_earnable_page_template() {
  global $badgeos_obf;
  $obf_settings = $badgeos_obf->obf_settings;
  $templater = ObfPageTemplater::get_instance();
  if ( !empty($obf_settings['earnable_page']) ) {
    $templater->add_template('earnable_page', '../templates/earnable.php');
    $templater->set_template_page($obf_settings['earnable_page'], 'earnable_page');
  }
}
add_action('init', 'badgeos_register_earnable_page_template', 99);
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
        'continueurl' => array(
          'name'        => __( 'Continue to URL', 'badgeos' ),
          'description' => __( 'Continue to URL after submitting the form. (Optional)', 'badgeos' ),
          'type'        => 'text',
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
    'continueurl' => '',
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

function badgeos_insert_earnable_badge_page() {
  $badgeos_obf = BadgeOS_Obf::get_instance();
  $obf_settings = $badgeos_obf->obf_settings;
  if (empty($obf_settings['earnable_page'])) {
    $pageattrs = array(
				'post_title'   => __( 'Earnable Badge', 'badgeos'),
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => 1,
				'post_type'    => 'page',
			);
    $page_id = wp_insert_post( $pageattrs );

    $obf_settings = (array) get_option( 'obf_settings', array() );

		$obf_settings['earnable_page'] = (string)$page_id;
		update_option( 'obf_settings', $obf_settings );
  }
}

/**
 * BadgeOS Open Badge Factory earnable badge apply page.
 * @since  1.4.6
 * @return void
 */
function badgeos_obf_earnable_badge_apply_page($encrypteddata = null, $formposturl = '') {
  //http://doppelganger.discendum.com/wp/wp-admin/admin.php?page=badgeos_sub_obf_earnable_badge_apply
	/**
	 * @var $badgeos_obf BadgeOS_Obf
	 */
	global $badgeos_obf;

  $output = '';
  if (is_null($encrypteddata)) {
    $encrypteddata = $_REQUEST['encrypteddata'];
  }
  
  $data = badgeos_obf_simple_crypt($encrypteddata, 'd');
  if (!$data) {
    return;
  }
  $data = json_decode($data);
  $earnableid = $data->earnable_id;
  if ($data->user_id !== get_current_user_id() ) {
    return new WP_Error('error', __('Access denied!', 'badgeos'));
  }
  if ($_SERVER['REQUEST_METHOD'] == 'GET' && ! wp_verify_nonce( $data->_ebnonce, 'earnable_badge-'.$earnableid )) {
    return new WP_Error('error', __('Nonce failed!', 'badgeos'));
  }
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && ! wp_verify_nonce( $data->_ebpnonce, 'earnable_badge-apply-'.$earnableid )) {
    return new WP_Error('error', __('Nonce failed!', 'badgeos'));
  }

  $earnable = $badgeos_obf->obf_client->get_earnable_badge($earnableid, 1);
  error_log('Displaying ' . $earnableid);
  $applyurl = $earnable['apply_url'];
  $continueurl = !empty($data->continueurl) ? 
      $data->continueurl : 
      (
        !empty($earnable['redirect_url']) ?
        $earnable['redirect_url'] : 
        get_permalink($data->id)
      );
  if (!empty($_POST)) {
    try {
      $response = $badgeos_obf->obf_client->earnable_badge_apply($earnableid, $_POST);
    } catch(\Exception $ex) {
      $output .= $ex->getMessage();
      if (method_exists($ex,'getResponse')) {
        $output = $ex->getResponse()->getBody(true);
      }
      
      $response = null;
    }
    

    $output .= var_export($response, true);
  } else {
    if (empty($earnable['form_html'])) {
      return new WP_Error('error', __('Failed retrieving form', 'badgeos'));
    }
    // Print form
    $output .= '<form action="'.$formposturl.'" method="post" enctype="multipart/form-data" id="earnable-form" accept-charset="utf-8" class="form-horizontal">';
    $secret_token_fields = '';
    if ($earnable['approval_method'] == 'secret') {
      $secret_token_fields = '<div class="form-group">
              <label class="control-label col-md-4">Claim code <span class="red">*</span></label>
              <div class="col-md-4">
                <input name="secret_token" class="form-control" required="" maxlength="255" type="text"><p class="help-block">Input your badge claim code here.</p>
              </div>
            </div>';
    }
    if ($earnable['attach_evidence'] == 'optional') {
      $attach_evidence_field = '<div class="form-group">
            <div class="col-md-8 col-md-offset-4">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="attach_evidence" value="1" checked>
                       ' . __('Attach application form as evidence in the badge', 'badgeos') . '
                    </label>
                </div>
            </div>
        </div>';
    } else {
        $attach_evidence_field = '<input type="hidden" name="attach_evidence" value="' . ($earnable['attach_evidence'] == 'yes' ? 1 : 0) . '">';
    }

    $output .= '<fieldset>
    <div class="form-group">
          <div class="col-md-6 col-md-offset-4">
            


          </div>
        </div>

        <hr>
    '.$secret_token_fields.$attach_evidence_field.'
        

        <div class="form-group">
          <label class="control-label col-md-4">Your name <span class="red">*</span></label>
          <div class="col-md-4">
            <input name="applicant" class="form-control" required="" pattern="^[^\r\n]+$" maxlength="255" type="text">
    </div>
        </div>

        <div class="form-group">
          <label class="control-label col-md-4">Your email address <span class="red">*</span></label>
          <div class="col-md-4">
            <input name="email" class="form-control" required="" maxlength="255" type="email">
    </div>
        </div>

        
    </fieldset>';
    $output .= '<hr>';



    $output .= $earnable['form_html'];
    $output .= '';
    $output .= '<input class="btn btn-primary" name="replace" value="Submit your application now" type="submit">';
    $output .= '</form>';
    error_log($output);
    $customscript = '';
    
    $current_user = wp_get_current_user();
    if (property_exists($data, 'prefill') && $data->prefill == 'true' && $current_user) {
      $customscript .= 'jQuery("input[name=\'applicant\'").val("'.$current_user->display_name.'");';
      $customscript .= 'jQuery("input[name=\'email\'").val("'.$current_user->user_email.'");';
    }
    

    $output .= '<script type="text/javascript">' . $customscript . '</script>';
    if (!empty($_POST)) {
      $continueurl = !empty($data->continueurl) ? $data->continueurl : get_permalink($data->id);
      $output .= '<p class="text-center"><a class="btn btn-primary" href="' . $continueurl . '">'.__('Back', 'badgeos').'</a>';
    }
  }
  

  

  echo $output;
}

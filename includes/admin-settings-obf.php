<?php

/**
 * Open Badge Factory settings pages
 *
 * @package BadgeOS
 * @subpackage OBF
 * @author Discendum Oy
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://openbadgefactory.com
 */

function badgeos_obf_register_settings() {
    register_setting( 'obf_settings_group', 'obf_settings', 'badgeos_obf_settings_validate' );
    register_setting( 'obf_import_group', 'obf_import', 'badgeos_obf_import_callback' );
}
add_action( 'admin_init', 'badgeos_obf_register_settings' );

/**
 * Open Badge Factory API Settings validation
 * @since  1.4.5
 * @param  array $options Form inputs data
 * @return array          Sanitized form input data
 */
function badgeos_obf_settings_validate( $options = array() ) {


	// If attempting to retrieve an api key from credly
	if (
		isset( $_POST['badgeos_obf_api_key_nonce'] )
		&& wp_verify_nonce( $_POST['badgeos_obf_api_key_nonce'], 'badgeos_obf_api_key_nonce' )
	) {
		// sanitize
		$username = ( !empty( $options['obf_user'] ) ? sanitize_text_field( $options['obf_user'] ) : '' );
		$password = ( !empty( $options['obf_password'] ) ? sanitize_text_field( $options['obf_password'] ) : '' );
                $apikey = $options['api_key'];
                $url = $options['obf_api_url'];
                $certDir = realpath($options['obf_cert_dir']);
                $correcturl = true;
                // Certificate directory not writable.
                if (empty($certDir) && !empty($options['obf_cert_dir'])) {
                    add_settings_error('obf_settings_bad_certdir', esc_attr( 'settings_updated' ),  sprintf( __( 'API certificate dir (%s) is defined incorrectly. Please use an absolute path of an existing directory.', 'badgeos' ), $options['obf_cert_dir']), 'error');
                }
                if (!is_writable($certDir)) {
                    //clear obf_api_url input if error
                    unset( $options['obf_api_url'] );
                    $correcturl = false;

                    add_settings_error('obf_settings_not_writeable_certdir', esc_attr( 'settings_updated' ),  sprintf( __( 'API certificate dir (%s) is not writeable.', 'badgeos' ), $certDir), 'error');
                } else {
                    if (!empty($apikey) && !empty($url)) {
                    	$apiurl = badgeos_obf_url_checker($url);
                        $clientId = badgeos_obf_get_api_cert($apikey, $apiurl, $certDir);
                        if (false !== $clientId) {
                            $options['obf_client_id'] = sanitize_text_field($clientId);
                            $options['obf_api_url'] = $apiurl;
                        }
                        else{
                        	//clear obf_api_url input if error
                        	unset( $options['obf_api_url'] );
                    		$correcturl = false;
                        }
                    }
                    else{
                    	add_settings_error('obf_settings_bad_apikey', esc_attr( 'settings_updated' ),  __( 'API key or URL is missing', 'badgeos' ), 'error');
                    	//clear obf_api_url input if error
                    	unset( $options['obf_api_url'] );
                    	$correcturl = false;
                    }
                }
	}

	// we're not saving these values
	unset( $options['obf_user'] );
	unset( $options['obf_password'] );
    unset( $options['api_key'] );
	// sanitize all our options
	foreach ( $options as $key => $opt ) {
		$clean_options[$key] = sanitize_text_field( $opt );
	}

	/*
		check if apiurl is changed and clear existing badges from database.
	*/
	badgeos_obf_maybe_clear_all_obf_badges($apiurl, $correcturl);

	return $clean_options;
}

/**
* Clear existing badges if apiurl is changed.
* @since  1.4.7.3
*/
function badgeos_obf_maybe_clear_all_obf_badges($apiurl=null, $correcturl=false) {

	/**
	* @var $badgeos_obf BadgeOS_Obf
	*/
	global $badgeos_obf;


	$obf_settings = $badgeos_obf->obf_settings;
    	if (!empty($certDir)) {
            $obf_settings = array_merge($obf_settings, array('obf_cert_dir' => $certDir));
        }
        
    $client = ObfClient::get_instance(null, $obf_settings);

    //get saved api_url
	$old_api_url = $client->get_api_url();

	//correcturl is true if no errors in connects
	if($old_api_url != $apiurl && !empty($apiurl) && false !== $correcturl){
		//Get all existing badge ids from db;
		$existing_badges = $badgeos_obf->obf_get_existing_badges_id_map();

		//loop and move trash
		foreach($existing_badges as $badge) {
			wp_trash_post($badge);
		}
	}

}

/**
* creates apiurl
* @since  1.4.7.3
* @param  string $api_url
* @return "https://"+url+"/v1"
*/
function badgeos_obf_url_checker($url) {
	if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
		$url = "https://" . $url;
	}
	if (!preg_match("/\/v1$/", $url)) {
		$url = $url . "/v1";
   	}

    return $url;
}

/**
 * creates apiurl
 * @since  1.4.7.3
 * @param  string $api_url
 * @return "https://"+url+"/v1"
 */
  	function url_checker($url) {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "https://" . $url;
        }
        if (!preg_match("/\/v1$/", $url)) {
            $url = $url . "/v1";
        }

        return $url;
    }




/**
 * Retrieves Open Badge Factory api key via username/password
 * @since  1.4.5
 * @param  string $username OBF user email
 * @param  string $password OBF user passowrd
 * @return string           API client id on success, false otherwise
 */
function badgeos_obf_get_api_cert( $apikey, $apiurl, $certDir ) {
        global $badgeos_obf;

	$obf_settings = $badgeos_obf->obf_settings;
        if (!empty($certDir)) {
            $obf_settings = array_merge($obf_settings, array('obf_cert_dir' => $certDir));
        }
        
        $client = ObfClient::get_instance(null, $obf_settings);
        $errorDetails = '';
        try {
            $success = $client->authenticate($apikey, $apiurl);
        } catch (\Exception $ex) {
            $success = false;
            $errorDetails = $ex->getMessage();
        }
        if (true !== $success) {
            $error = '<p>'. sprintf( __( 'There was an error creating an Open Badge Factory API (%s) certificate: %s', 'badgeos' ), $apiurl, $errorDetails ) . '</p>';
            add_settings_error('connecterror', esc_attr( 'settings_updated' ), $error, 'error');
        } else if (($client_id = $client->get_client_id()) && !empty($client_id)) {
            return $client->get_client_id();
        }

	return false;
}

/**
 * Retrieves Open Badge Factory api key via username/password
 * @since  1.4.5
 * @param  string $username OBF user email
 * @param  string $password OBF user passowrd
 * @return string           API key on success, false otherwise
 */
function badgeos_obf_get_api_key( $username = '', $password = '' ) {
	$clientId = 'NPCY64y5CEy1';
	$url = BADGEOS_OBF_API_URL . '/c/client/'.$clientId.'/generate_csrtoken';

	$response = wp_remote_post( $url, array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password )
		),
		'sslverify' => false
	) );

	// If the response is a WP error
	if ( is_wp_error( $response ) ) {
		$error = '<p>'. sprintf( __( 'There was an error getting a Open Badge Factory API Key: %s', 'badgeos' ), $response->get_error_message() ) . '</p>';
		 add_settings_error('responseError', esc_attr( 'settings_updated' ), $error, 'error');
	}

	// If the response resulted from potentially bad credentials
	if ( '401' == wp_remote_retrieve_response_code( $response ) ) {
		$error = '<p>'. __( 'There was an error getting a Open Badge Factory API Key: Please check your username and password.', 'badgeos' ) . '</p>';
		// Save our error message.
		add_settings_error('401error', esc_attr( 'settings_updated' ), $error, 'error');
	}

	$api_key = json_decode( $response['body'] );
	$api_key = $api_key->data->token;

	return $api_key;

}

/**
 * Saves an error string for display on Open Badge Factory settings page
 * @since  1.4.5
 * @param  string $error Error message
 * @return bool          False after updating option
 */
function badgeos_obf_get_api_key_error( $error = '' ) {

        $old_error = get_option('obf_api_key_error');
	// Temporarily store our error message.
	update_option( 'obf_api_key_error', empty($old_error) ? $error : $old_error . '<br/>' . $error );
        return false;
}

add_action( 'all_admin_notices', 'badgeos_obf_api_key_errors' );
/**
 * Displays error messages from Open Badge Factory API key retrieval
 * @since  1.4.5
 * @return void
 */
function badgeos_obf_api_key_errors() {
        $plugin_name = 'open-badge-factory';
	if ( get_current_screen()->id != $plugin_name . '_page_badgeos_sub_obf_integration' || !( $has_notice = get_option( 'obf_api_key_error' ) ) )
            return;
		

	// If we have an error message, we'll display it
	echo '<div id="message" class="error">'. $has_notice .'</div>';
	// and then delete it
	delete_option( 'obf_api_key_error' );
}

/**
 * Plugin Open Badge Factory Integration settings page.
 * @since  1.4.5
 * @return void
 */
function badgeos_obf_options_page() {

	/**
	 * @var $badgeos_obf BadgeOS_Obf
	 */
	global $badgeos_obf;

	$obf_settings = $badgeos_obf->obf_settings;
        
?>
	<div class="wrap" >
		<div id="icon-options-general" class="icon32"></div>
		<h2><?php _e( 'Open Badge Factory Integration Settings', 'badgeos' ); ?></h2>
		<?php settings_errors(); ?>

		<form method="post" action="options.php">
			<?php
				settings_fields( 'obf_settings_group' );
			?>
                        <p><?php printf( __( '<a href="%1$s" target="_blank">Open Badge Factory</a> is a cloud platform that provides the tools your organization needs to implement a meaningful and sustainable Open Badges system. Be successful with your badges! If you do not yet have a Open Badge Factory account, <a href="%2$s" target="_blank">create one now</a>. It\'s free.', 'badgeos' ), 'https://openbadgefactory.com', 'https://openbadgefactory.com/signup' ); ?></p>
			
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="obf_enable"><?php _e( 'Enable Badge Sharing via Open Badge Factory: ', 'badgeos' ); ?></label>
					</th>
					<td>
						<select id="obf_enable" name="obf_settings[obf_enable]">
							<option value="false" <?php selected( $obf_settings['obf_enable'], 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
							<option value="true" <?php selected( $obf_settings['obf_enable'], 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
						</select>
					</td>
				</tr>
                                <tr valign="top">
					<th scope="row">
						<label for="obf_enable"><?php _e( 'API certificate dir: ', 'badgeos' ); ?></label>
					</th>
					<td>
						<input id="obf_cert_dir" type="text" class="widefat" name="obf_settings[obf_cert_dir]" value="<?php echo esc_attr( $obf_settings['obf_cert_dir'] ) ?>">
						</input>
					</td>
				</tr>
			</table>

			<?php
				// We need to get our api key
				if ( empty( $obf_settings['obf_client_id'] ) || ('__EMPTY__' === $obf_settings['obf_client_id'])) {
					badgeos_obf_options_no_api( $obf_settings );
				}
				// We already have our api key
				else {
					badgeos_obf_options_yes_api( $obf_settings );
				}

				submit_button( __( 'Save Settings', 'badgeos' ) );
			?>
		</form>
	</div>
	 <script type="text/javascript">
        jQuery(document).ready(function($) {

        	jQuery('input[name="urledit"]').click(function(){
        		var $this = $(this);

        		if($this.is(':checked')){
        			jQuery('#api_url').attr('readOnly', false);
        		}else{
        			jQuery('#api_url').attr('readOnly', true);
        		}
        	});
        });
    </script>
<?php

}

/**
 * BadgeOS Open Badge Factory API key retrieval form.
 * @since  1.4.5
 * @param  array $obf_settings saved settings
 * @return void
 */
function badgeos_obf_options_no_api( $obf_settings = array() ) {

	wp_nonce_field( 'badgeos_obf_api_key_nonce', 'badgeos_obf_api_key_nonce' );

	if ( is_array( $obf_settings ) ) {
		foreach ( $obf_settings as $key => $opt ) {
			if ( in_array( $key, array( 'obf_user', 'obf_password', 'api_key', 'obf_enable', 'obf_cert_dir' ) ) ) {
				continue;
			}

			// Save our hidden form values
			echo '<input type="hidden" name="obf_settings['. esc_attr( $key ) .']" value="'. esc_attr( $opt ) .'" />';
		}
	}
?>
	<div id="obf-settings">
		<h3><?php _e( 'Get Open Badge Factory API Key', 'badgeos' ); ?></h3>

		<p class="toggle hidden"><?php _e( 'Enter your Open Badge Factory account username and password to access your API key.', 'badgeos' ); ?></p>

		<p class="toggle"><?php printf( __( 'Enter your %s to access your API key.', 'badgeos' ), '<a href="#show-api-key" class="hidden">'. __( 'Open Badge Factory account username and password', 'badgeos' ) .'</a>' ); ?></p>

		<table class="form-table">
			<tr valign="top"  class="hidden">
				<th scope="row">
					<label for="obf_user"><?php _e( 'Username: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<input id="obf_user" type="text" name="obf_settings[obf_user]" class="widefat" value="<?php echo esc_attr( $obf_settings[ 'obf_user' ] ); ?>" style="max-width: 400px;" />
				</td>
			</tr>
			<tr valign="top" class="hidden">
				<th scope="row">
					<label for="obf_password"><?php _e( 'Password: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<input id="obf_password" type="password" name="obf_settings[obf_password]" class="widefat" value="<?php echo esc_attr( $obf_settings[ 'obf_password' ] ); ?>" style="max-width: 400px;" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="api_url"><?php _e( 'URL: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<input id="api_url" type="text" name="obf_settings[obf_api_url]" class="widefat" value="<?php echo esc_attr( $obf_settings['obf_api_url'] ) ?>" readOnly="false" />
                                        <p class="description"><input type="checkbox" name="urledit" value="urledit"/> <?php _e('Use different URL.', 'badgeos'); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="api_key"><?php _e( 'API Key: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<textarea id="api_key" type="text" name="obf_settings[api_key]" class="widefat" value="<?php echo esc_attr( $obf_settings[ 'api_key' ] ); ?>" style="max-width: 1000px;" ></textarea>
                                        <p class="description"><?php _e('Find your API Key by logging in to Open Badge Factory. It is located under Admin Tools > API key.', 'badgeos'); ?></p>
				</td>
			</tr>
		</table>

		<p class="toggle hidden"><?php printf( __( 'Already have your API key? %s', 'badgeos' ), '<a href="#show-api-key">'. __( 'Click here', 'badgeos' ) .'</a>' ); ?></p>
	</div>
<?php

}

/**
 * BadgeOS Open Badge Factory Settings form (when API key has been saved).
 * @since  1.4.5
 * @param  array $obf_settings saved settings
 * @return void
 */
function badgeos_obf_options_yes_api( $obf_settings = array() ) {
        global $badgeos_obf;
        wp_nonce_field( 'badgeos_obf_api_key_nonce', 'badgeos_obf_api_key_nonce' );
        $cert_expiry_date = $badgeos_obf->obf_client->get_certificate_expiration_date();
?>
	<div id="obf-settings">
		<h3><?php _e( 'Open Badge Factory API Key', 'badgeos' ); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label for="api_url"><?php _e( 'URL: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<input id="api_url" type="text" name="obf_settings[obf_api_url]" class="widefat" value="<?php echo esc_attr( $obf_settings['obf_api_url'] ) ?>" readOnly="true" />
                                        <p class="description"><input type="checkbox" name="urledit" value="urledit"/> <?php _e('Use different URL.', 'badgeos'); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="api_key"><?php _e( 'API Key: ', 'badgeos' ); ?></label></th>
				<td>
					<textarea id="api_key" type="text" name="obf_settings[api_key]" class="widefat" value="<?php echo esc_attr( $obf_settings[ 'api_key' ] ); ?>" ></textarea>
                                        <input id="obf_client_id" type="hidden" name="obf_settings[obf_client_id]" class="widefat" value="<?php echo esc_attr( $obf_settings[ 'obf_client_id' ] ); ?>" />
                                        <p class="description"><?php _e('Find your API Key by logging in to Open Badge Factory. It is located under Admin Tools > API key.', 'badgeos'); ?></p>
				</td>
			</tr>
		</table>
                <?php
                if (false !== $cert_expiry_date) {
                    ?>
                    <p class="notice notice-success"><?php echo sprintf(__('Open Badge Factory API certificate expires on %s', 'badgeos'), date_i18n( get_option( 'date_format' ), $cert_expiry_date) ) ?></p>
                    <?php
                }
                
                $advanced_feature_class = badgeos_obf_show_advanced_features() ? 'advanced-features' : 'hidden';
                ?>
                
                <div class="<?php echo $advanced_feature_class; ?>">
                    <h3><?php _e( 'Open Badge Factory Field Mapping', 'badgeos' ); ?></h3>

                    <p><?php _e( 'Customize which Open Badge Factory fields for badge creation and issuing (listed on the left) match which WordPress and Open Badge Factory -plugin fields (listed to the right).', 'badgeos' ); ?>
                            <br /><?php _e( 'When badges are imported from Open Badge Factory, wordpress will rely on the global mapping found here.', 'badgeos' ); ?>
                    </p>
                    <table class="form-table">
                            <tr valign="top">
                                    <th scope="row"><label for="obf_badge_title"><?php _e( 'Badge Title: ', 'badgeos' ); ?></label></th>
                                    <td>
                                            <select id="obf_badge_title" name="obf_settings[obf_badge_title]">
                                                    <?php echo obf_fieldmap_list_options( $obf_settings[ 'obf_badge_title' ] ); ?>
                                            </select>
                                    </td>
                            </tr>
                            <tr valign="top">
                                    <th scope="row">
                                            <label for="obf_badge_short_description"><?php _e( 'Short Description: ', 'badgeos' ); ?></label>
                                    </th>
                                    <td>
                                            <select id="obf_badge_short_description" name="obf_settings[obf_badge_short_description]">
                                                    <?php echo obf_fieldmap_list_options( $obf_settings[ 'obf_badge_short_description' ] ); ?>
                                            </select>
                                    </td>
                            </tr>
                            <tr valign="top">
                                    <th scope="row">
                                            <label for="obf_badge_description"><?php _e( 'Description: ', 'badgeos' ); ?></label>
                                    </th>
                                    <td><select id="obf_badge_description" name="obf_settings[obf_badge_description]">
                                                    <?php echo obf_fieldmap_list_options( $obf_settings[ 'obf_badge_description' ] ); ?>
                                            </select></td>
                            </tr>
                            <tr valign="top">
                                    <th scope="row">
                                            <label for="obf_badge_criteria"><?php _e( 'Criteria: ', 'badgeos' ); ?></label>
                                    </th>
                                    <td>
                                            <select id="obf_badge_criteria" name="obf_settings[obf_badge_criteria]">
                                                    <?php echo obf_fieldmap_list_options( $obf_settings[ 'obf_badge_criteria' ] ); ?>
                                            </select>
                                    </td>
                            </tr>
                            <tr valign="top">
                                    <th scope="row">
                                            <label for="obf_badge_image"><?php _e( 'Image: ', 'badgeos' ); ?></label>
                                    </th>
                                    <td>
                                            <select id="obf_badge_image" name="obf_settings[obf_badge_image]">
                                                    <?php echo obf_fieldmap_list_options( $obf_settings[ 'obf_badge_image' ] ); ?>
                                            </select>
                                    </td>
                            </tr>
                            <tr valign="top" style="display: none;">
                                    <th scope="row">
                                            <label for="obf_badge_testimonial"><?php _e( 'Testimonial: ', 'badgeos' ); ?></label>
                                    </th>
                                    <td><select id="obf_badge_testimonial" name="obf_settings[obf_badge_testimonial]">
                                                    <?php echo obf_fieldmap_list_options( $obf_settings[ 'obf_badge_testimonial' ] ); ?>
                                            </select></td>
                            </tr>
                            <tr valign="top" style="display: none;">
                                    <th scope="row">
                                            <label for="obf_badge_evidence"><?php _e( 'Evidence: ', 'badgeos' ); ?></label>
                                    </th>
                                    <td>
                                            <select id="obf_badge_evidence" name="obf_settings[obf_badge_evidence]">
                                                    <?php echo obf_fieldmap_list_options( $obf_settings[ 'obf_badge_evidence' ] ); ?>
                                            </select>
                                    </td>
                            </tr>
                    </table>

                    <div style="display: none;">
                    <h3><?php _e( 'Open Badge Factory Notification Settings', 'badgeos' ); ?></h3>
                    <p><?php _e( 'Send custom notifications to users when they earn a Open Badge Factory-enabled achievement.', 'badgeos' ); ?></p>    
                    </div>
                    
                    <table class="form-table obf-notifications">
                            <tr valign="top" class="obf-notifications-enable-message"  style="display: none;">
                                    <th scope="row">
                                            <label for="obf_badge_sendemail_add_message"><?php _e( 'Add a global custom message to each notification: ', 'badgeos' ); ?></label>
                                    </th>
                                    <td>
                                            <select id="obf_badge_sendemail_add_message" name="obf_settings[obf_badge_sendemail_add_message]">
                                                    <option value="false"<?php selected( $obf_settings[ 'obf_badge_sendemail_add_message' ], 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
                                                    <option value="true"<?php selected( $obf_settings[ 'obf_badge_sendemail_add_message' ], 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
                                            </select>
                                    </td>
                            </tr>

                            <tr valign="top" class="obf-notifications-message"  style="display: none;">
                                    <th scope="row">
                                            <label for="obf_badge_sendemail"><?php _e( 'Custom notification message: ', 'badgeos' ); ?></label>
                                    </th>
                                    <td>
                                            <textarea id="obf_badge_sendemail_message" name="obf_settings[obf_badge_sendemail_message]" cols="80" rows="10"><?php echo esc_textarea( $obf_settings[ 'obf_badge_sendemail_message' ] ); ?></textarea>
                                    </td>
                            </tr>
                    </table>
                </div>
		
		<?php do_action( 'obf_settings', $obf_settings ); ?>
	</div>
<?php

}

function badgeos_obf_import_callback($options = array()) {
    global $badgeos_obf, $wpdb;
    
    $create_duplicates = $options['on_duplicate'] === 'create';
    $pre_existing = array();
    $import_overrides = array('_badgeos_obf_editing_disabled' => 'true'); // Hide badge fields by default on imported badges
    $badge_selections= array();
    if (count($options) > 0 && array_key_exists('badges', $options)) {
        $badge_selections = $options['badges'];
        foreach($badge_selections as $option => $badge_id) {
            $query = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} pm "
                    . "LEFT JOIN {$wpdb->posts} p ON (pm.post_id = p.id) WHERE p.post_status != 'trash' AND pm.meta_key = '_badgeos_obf_badge_id' AND pm.meta_value = %s LIMIT 1", $badge_id);
            $exists = $wpdb->get_col($query);
            if (empty($exists) || $create_duplicates) {
                $post_id = $badgeos_obf->import_obf_badge(null, $badge_id, false, $import_overrides);
            } else {
                $pre_existing[] = $badge_id;
                $post_id = $exists[0];
            }
        }
    }
    
    if (count($badge_selections) == 1) {
        $url = get_edit_post_link((int)$post_id,'');
        wp_redirect($url);
        exit;
    }
    return null;
}

/**
 * BadgeOS Open Badge Factory badge import page.
 * @since  1.4.6
 * @return void
 */
function badgeos_obf_import_page() {

	/**
	 * @var $badgeos_obf BadgeOS_Obf
	 */
	global $badgeos_obf;

	$obf_settings = $badgeos_obf->obf_settings;
        
        $categories = array_merge(array('all'), $badgeos_obf->obf_client->get_categories());
        
        $badges = $badgeos_obf->obf_client->get_badges();
        $single_select = array_key_exists('single_select', $_REQUEST) && $_REQUEST['single_select'] === 'true' ? true : false;
?>
	<div class="wrap" >
            <form method="post" action="options.php">
            <?php
                settings_fields( 'obf_import_group' );
            ?>
            <div class="shuffle-options filter-options row-fluid">
                <?php foreach($categories as $category) { ?>
                    <a href="#" class="button button-default" data-group="<?php echo esc_attr($category); ?>">
                        <?php 
                        if ($category == 'all') {
                            _e('All', 'badgeos');
                        } else {
                          echo esc_html($category);  
                        }
                        ?>
                    </a>
                <?php } ?>
            </div>
            <div class="span3 m-span3 shuffle__sizer"></div>
            <ul id="obf-badges" class="widget-achievements-listing">
            <?php
                foreach($badges as $badge) {
                    $badge_id = $badge['id'];
                    $badge_name = $badge['name'];
                    $badge_description = $badge['description'];
                    $badge_image = $badge['image'];
                    ?>
                <li class="obf-badge has-thumb" data-groups='<?php echo json_encode($badge['category']); ?>'>
                            <label class="" for=obf_badge_<?php echo $badge_id; ?>>
                                <div class="media">
                                    <div class="pull-left">
                                        <img class="badgeos-item-thumb" src="<?php echo $badge_image; ?>"/>
                                    </div>
                                    <div class="media-body">
                                        <h4 class="obf-badge-title media-heading"><?php echo esc_html($badge_name); ?></h4>
                                        <div class="badge-desc"><?php echo esc_html($badge_description); ?></div>
                                    
                                    </div>
                                    <div class="media-footer">
                                        <div class="center">
                                            <?php 
                                            if ($single_select) {
                                                ?>
                                                <input type="radio" id="obf_badge_<?php echo $badge_id; ?>" name="obf_import[badges][import]" value="<?php echo esc_attr($badge_id); ?>">
                                                <?php
                                            } else {
                                                ?>
                                                <input type="checkbox" id="obf_badge_<?php echo $badge_id; ?>" name="obf_import[badges][<?php echo esc_attr($badge_id); ?>]" value="<?php echo esc_attr($badge_id); ?>">
                                                <?php
                                            }
                                            ?>
                                            
                                        </div>
                                    </div>
                                </div>
                                
                            </label>
                            
                            
                            </input>
                            
                        </li>
                    <?php
                }
            ?>
            </ul>
            <br style="clear: both;"/>

            <table class="form-table">
                <tbody>
                    <tr valign="top" class="obf-notifications-enable-message">
                        <th scope="row">
                                <label><?php _e( 'How to handle import of duplicates', 'badgeos' ); ?></label>
                        </th>
                        <td>
                                <label for="obf_import_on_duplicate_skip">
                                    <input type="radio" checked id="obf_import_on_duplicate_skip" name="obf_import[on_duplicate]" value="skip"></input>
                                    <?php _e('Skip duplicates', 'badgeos'); ?>
                                </label>
                                <br/>
                                <label for="obf_import_on_duplicate_create">
                                    <input type="radio" id="obf_import_on_duplicate_create" name="obf_import[on_duplicate]" value="create"></input>
                                    <?php _e('Create duplicates', 'badgeos'); ?>
                                </label>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php
                if ($single_select) {
                    echo submit_button( __('Pick badge', 'badgeos'));
                } else {
                    echo submit_button( __('Import badges', 'badgeos'));
                }
            ?>
            
            </form>
        </div>
<?php
}


add_action('admin_footer', 'badgeos_obf_post_import_button');
function badgeos_obf_post_import_button() {
    $screen = get_current_screen();
    if ( !badgeos_obf_screen_is_edit_obf_badges($screen) )   // Only add to edit-badges
        return;
    
    add_thickbox();
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {

            $('<a href="admin.php?page=badgeos_sub_obf_import&single_select=true" id="import_obf_badges" class="page-title-action obf-import"><?php _e('Pick a badge', 'badgeos'); ?></a>').appendTo(".wrap h1");
            $('<option>').val('import_obf_badges').text('Import from Open Badge Factory').appendTo('select[name="action"]');
        });
    </script>
    <?php
}

/**
 * Add a metabox to import badge from OBF.
 */
function badgeos_obf_add_obf_import_box(){
$screen = get_current_screen();
    if ($screen->action === 'add') { // Only show the metabox on add, not on edit
        add_meta_box('obf-import-metabox', 'Open Badge Factory', 'obf_import_metabox', 'badges', 'side', 'high');
    }
}

function badgeos_obf_add_obf_columns_for_obf_badges() {
    global $badgeos_obf;
    $screen = get_current_screen();
    if ( !badgeos_obf_screen_is_edit_obf_badges($screen) )   // Only add to edit-badges
        return;
    // TODO: Do we want issuing on column as well as actions list?
    $achievement_type = substr($screen->id, 5);
    add_filter('manage_'.$achievement_type.'_posts_columns', 'badgeos_obf_columns_head', 10);
    add_action('manage_'.$achievement_type.'_posts_custom_column', 'badgeos_obf_columns_content', 10, 2);
}
add_action( 'current_screen', 'badgeos_obf_add_obf_columns_for_obf_badges');


add_action('add_meta_boxes', 'badgeos_obf_add_obf_import_box'); 

/**
 * OBF Import metabox content.
 */
function obf_import_metabox() 
{
    ?>
        <a href="admin.php?page=badgeos_sub_obf_import&single_select=true" class="button button-primary button-large" id="obf-import-button"/>
        <?php _e('Pick a badge', 'badgeos'); ?>
        </a>
    <?php
}

/**
 * Fix create capabilitys for our custom post types.
 */
function badgeos_obf_fix_achievement_capability_create() {
    global $badgeos_obf;
    $post_types = get_post_types( array(),'objects' );
    $our_post_types = $badgeos_obf->obf_badge_achievement_types(false);
    foreach ( $post_types as $post_type ) {
        $cap = "create_".strtolower($post_type->name);
        if (in_array($post_type->name, $our_post_types)) {
            $post_type->cap->create_posts = $cap;
            map_meta_cap( $cap, 1);
        }
    }
}
add_action( 'init', 'badgeos_obf_fix_achievement_capability_create',100);


function badgeos_obf_screen_is_edit_obf_badges($screen) {
    global $badgeos_obf;
    if ( substr($screen->id, 0, 5) != "edit-" )   // Only add to edit-
        return false;

    $achievement_types = $badgeos_obf->obf_badge_achievement_types(false);
    foreach ($achievement_types as $achievement_type) {
        if ($screen->id == "edit-".$achievement_type ) {
            return true;
        }
    }

    return false;
}
function badgeos_obf_import_all_badges_on_admin_init($screen) {
    global $badgeos_obf;
    if ( !badgeos_obf_screen_is_edit_obf_badges($screen) )   // Only add to edit-badges
        return;
    try {
        $import_result = $badgeos_obf->import_all_obf_badges();
    } catch (Exception $ex) {
        $import_result = new WP_Error($ex->getMessage());
    }
    if (is_wp_error($import_result)) {
        badgeos_obf_set_notice(sprintf(__('Error importing badges. (%d %s)','badgeos'), $import_result->get_error_code(), $import_result->get_error_message()));
    }
    
}
add_action( 'current_screen', 'badgeos_obf_import_all_badges_on_admin_init');

/**
 * Remove quick edit links on the badge list.
 * @param type $actions
 * @param type $post
 * @return type
 */
function badgeos_obf_remove_row_actions( $actions, $post )
{
    $screen = get_current_screen();
    if( !badgeos_obf_screen_is_edit_obf_badges($screen) ) {
        return $actions;  
    }
    
    unset( $actions['trash'] );
    unset( $actions['inline hide-if-no-js'] );
    if (obf_is_achievement_giveable($post->ID)) {
        $issue_str = '<a href="admin.php?page=issue-obf-badge&post_id=' . $post->ID . '">' . __('Issue badge', 'badgeos') . '</a>';
        $actions['issue-obf-badge'] = $issue_str;
    }
    $actions['edit'] = '<a href="' .get_edit_post_link($post->ID) . '">' .__('Edit awarding rules', 'badgeos'). '</a>';
    $actions['view'] = '<a href="' .get_post_permalink($post->ID) . '">' .__('View badge', 'badgeos'). '</a>';

    return $actions;
}
add_filter( 'page_row_actions', 'badgeos_obf_remove_row_actions', 10, 2 );

/**
 * Remove quick edit links on the badge list.
 * @param type $actions
 * @param type $post
 * @return type
 */
function badgeos_obf_log_remove_row_actions( $actions, $post )
{
    if( $post->post_type != 'badgeos-log-entry' ) {
        return $actions;  
    }
    
    unset( $actions['inline hide-if-no-js'] );
    unset( $actions['edit'] );
    
    return $actions;
}
add_filter( 'post_row_actions', 'badgeos_obf_log_remove_row_actions', 10, 2 );


 
/**
 * Add column head
 * @param array $defaults
 * @return string
 */
function badgeos_obf_columns_head($defaults) {
    $index = 1;
    $temp = array_slice($defaults, 0, $index);
    $temp['obf_image'] = __('Image', 'badgeos');
    $defaults = array_merge($temp, array_slice($defaults, $index, count($defaults)));
    
    $index = 3;
    $temp = array_slice($defaults, 0, $index);
    $temp['obf_earning'] = __('Earning rules', 'badgeos');
    $defaults = array_merge($temp, array_slice($defaults, $index, count($defaults)));
    
    return $defaults;
}
/**
 * Add column content
 * @param type $column_name
 * @param type $post_ID
 */
function badgeos_obf_columns_content($column_name, $post_ID) {
    if ($column_name == 'issue_badge') {
        // show content of 'directors_name' column
        ?>
    <a href="admin.php?page=issue-obf-badge&post_id=<?php echo $post_ID; ?>">Issue</a>
        <?php
    } else if ($column_name == 'obf_image') {
        echo the_post_thumbnail( 'thumbnail' );
    } else if ($column_name == 'obf_earning') {
        $triggers = badgeos_get_required_achievements_for_achievement($post_ID);
        if (false !== $triggers) {
            $display_count = 1;
            $trigger_names = array();
            foreach($triggers as $trigger) {
                $trigger_names[] = $trigger->post_title;
            }
            if (count($trigger_names) <= $display_count) {
                echo implode(', ', $trigger_names);
            } else {
                echo implode(', ', array_slice($trigger_names, 0, $display_count));
                echo sprintf(__(' + %d more.', 'badgeos'), count($trigger_names) - $display_count);
            }
        } else {
            $earned_by = get_post_meta( $post_ID, '_badgeos_earned_by');
            $earned_by = (is_array($earned_by) && count($earned_by) > 0) ? $earned_by[0] : null;
            
            $humanized_earned_by = badgeos_obf_humanize_earned_by($post_ID, $earned_by);
            if (!empty($humanized_earned_by)) {
                echo $humanized_earned_by;
            }
            
            
        }
    }
}
function badgeos_obf_humanize_earned_by($post_ID, $earned_by) {
    $types = array(
        'triggers' => __( 'Completing Steps', 'badgeos' ),
        'points' => __( 'Minimum Number of Points', 'badgeos' ),
        'submission' => __( 'Submission (Reviewed)', 'badgeos' ),
        'submission_auto' =>__( 'Submission (Auto-accepted)', 'badgeos' ),
        'nomination' => __( 'Nomination', 'badgeos' ),
        'admin' => __( 'Admin-awarded Only', 'badgeos' )
    );
    if (!empty($earned_by) && is_string($earned_by) && array_key_exists($earned_by, $types)) {
        return $types[$earned_by];
    }
    return null;
}

function badgeos_obf_issue_badge_page() {
    if (empty($_REQUEST['post_id']))
        return;
    $post_ID = (int)$_REQUEST['post_id'];
    $post_thumbnail_id = get_post_thumbnail_id($post_ID);
    
    $badge_post = get_post($post_ID);
    $is_givable = obf_is_achievement_giveable($post_ID);
    
    $wp_roles = new WP_Roles();
    $roles = $wp_roles->get_names();
    
    $emails = array_key_exists('obf_issue_badge', $_POST) && isset($_POST['obf_issue_badge']['emails']) ? $_POST['obf_issue_badge']['emails'] : '';
    $users = array_key_exists('obf_issue_badge', $_POST) && isset($_POST['obf_issue_badge']['users']) ? $_POST['obf_issue_badge']['users'] : array();

    if (isset( $_POST['obf_issue_badge_nonce'] )
		&& wp_verify_nonce( $_POST['obf_issue_badge_nonce'], 'obf_issue_badge_nonce' ) ) {
        if (isset($_POST['obf_issue_badge']) ) {
            $success = badgeos_obf_issue_badge_callback($_POST['obf_issue_badge']);
            badgeos_obf_notice();
            if ($success) { // Clear selections on success.
                $emails = '';
                $users = array();
            }
        }
    } elseif (isset( $_POST['obf_issue_badge_nonce'] )) {
        $message = __('Nonce verification failed.', 'badgeos');
        echo '<div id="message" class="error"><p>'. $message .'</p></div>';
    }
    
    
    if (!$is_givable) {
        ?>
        <p id="message" class="warning notice notice-error">
           <?php
           _e('Enable sending this badge to OBF, to enable issuing.');
           ?>
       </p>
       <?php
    }
    ?>
    <div class="wrap" >
       
        <form method="post">

        <?php
            wp_nonce_field( 'obf_issue_badge_nonce', 'obf_issue_badge_nonce' );
        ?>
    <table class="form-table">
        <tbody>
            <tr valign="top" class="obf-notifications-enable-message">
                <th scope="row">
    <?php
    _e('Badge', 'badgeos');
    ?>
                </th>
                <td>
                    <h2>
                        <?php echo esc_html($badge_post->post_title); ?>
                    </h2>
    <?php
    if ($post_thumbnail_id) {
        $post_thumbnail_img = wp_get_attachment_image_src($post_thumbnail_id, 'featured_preview');
        echo '<img src="' . $post_thumbnail_img[0] . '" width="120px" height="120px"/><br/>';
    }
    ?>
                    <input type="hidden" name="obf_issue_badge[post_id]" value="<?php echo $post_ID; ?>"></input>
                </td>
    <tr valign="top" class="obf-notifications-enable-message">
        <th scope="row">
        <?php
            _e('Users', 'badgeos');
        ?>
        </th>
        <td>
            <label for="user-filter-options">
                <?php _e('Filter by user role', 'badgeos'); ?>
                <div id="user-filter-options" class="filter-options">
                    <a href="#" class="filter-option button button-default active" value="all"><?php _e('All', 'badgeos'); ?></a>
                    <?php
                    foreach($roles as $role_value => $role) {
                        ?>
                        <a href="#" class="filter-option button button-default" value="<?php echo esc_attr($role_value); ?>"><?php echo esc_attr($role); ?></a>
                        <?php
                    }
                    ?>
                </div>
            </label>
            <br/>
            <label for="user-filter-input">
                <?php _e('Filter by name', 'badgeos'); ?>
                <input id="user-filter-input" type="text" class="filter-input"></input>
            </label>
            <label for="user-filter-count">
                <?php _e('Amount of results', 'badgeos'); ?>
                <select id="user-filter-count" type="text" class="filter-item-count">
                    <option value="0">All</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <p class="description"><?php _e('Select one or more users by checking the checkboxes below') ?></p>
            <ul id="obf_issue_badge_user_list" class="filterable-items-list">
            <?php
            $all_users = get_users();
            foreach($all_users as $user) {
                $user_id = $user->data->ID;
                $email = $user->data->user_email;
                $name = $user->data->display_name;
                $login = $user->data->user_login;
                $roles = $user->roles;
                //echo "$user_id $email $name <br/>";
                ?>
                <li class="user-select filterable-item" data-groups='<?php echo json_encode($roles); ?>' data-name='<?php echo esc_attr($name); ?>'>
                        <?php if (is_email($email)) { ?>
                        <label for="obf_issue_badge_users_<?php echo $user_id; ?>" title="<?php echo esc_attr($login); ?>">
                            <input type="checkbox" id="obf_issue_badge_users_<?php echo $user_id; ?>" name="obf_issue_badge[users][<?php echo $user_id; ?>]" <?php echo array_key_exists($user_id, $users) ? 'checked' : ''; ?> value="<?php echo $user_id; ?>"></input>
                        <?php } else { ?>
                        <label for="obf_issue_badge_users_<?php echo $user_id; ?>" title="<?php echo esc_attr(sprintf(__('User %s has an invalid email address.', 'badgeos'), $login)); ?>">
                            <input disabled type="checkbox" id="obf_issue_badge_users_<?php echo $user_id; ?>" name="obf_issue_badge[users][<?php echo $user_id; ?>]" value="<?php echo $user_id; ?>"></input>
                        <?php }
                            echo esc_html($name); ?>
                        </label>
                </li>
                <?php
            }
            ?>
            </ul>
            <p class="filter-extra-info">
                <?php echo sprintf( __('Showing <span class="shown-count">%d</span> users. <span class="hidden-count">%d</span> hidden.', 'badgeos'), count($users), 0 ); ?>
            </p>
        </td>
    </tr>
    <tr valign="top" class="obf-notifications-enable-message">
        <th scope="row">
        <?php
            _e('Emails', 'badgeos');
        ?>
        </th>
        <td>
                <textarea class="widefat" rows="10" cols="80" id="obf_issue_badge_emails" name="obf_issue_badge[emails]"><?php echo $emails; ?></textarea>
                <p class="description"><?php _e('Enter additional email addresses here (1 per line) if you wish to issue the badge to people who do not have accounts on this site, or they are unknown.', 'badgeos'); ?></description>
        </td>
    </tr>
    <tr>
        <td>
    <?php
    if ($is_givable) {
        echo submit_button(__('Issue badge to users', 'badgeos'));
    }
    ?>
        </td>
    </tr>
            </form>
    </div>
    <?php
}

function badgeos_obf_add_issue_page() {
    $capability = badgeos_get_submission_manager_capability();
    add_submenu_page( 'options.php', __('Issue badge', 'badgeos'), __('Issue badge', 'badgeos'), $capability, 'issue-obf-badge', 'badgeos_obf_issue_badge_page' );
}
add_action('admin_menu', 'badgeos_obf_add_issue_page');

function badgeos_obf_issue_badge_callback($options) {
    global $badgeos_obf;
    $success = false;
    $users = array_key_exists('users', $options) ? $options['users'] : array();
    $emails = array_key_exists('emails', $options) ? $options['emails'] : '';
    $emails = explode("\n", str_replace("\r", "", $emails));
    $emails = array_filter($emails);
    $users = array_filter($users);
    $badge_id = $options['post_id'];
    if (!is_array($users)) {
        $users = array();
    }

    if (count($users) > 0 || count($emails) > 0) {
        try {
            $issue_result = $badgeos_obf->post_obf_user_badges($users, $emails, $badge_id, true);
        } catch (Exception $ex) {
            $issue_result=null;
            badgeos_obf_set_notice(sprintf(__('Badge issuing failed. Server returned %s', 'badgeos'), $ex->getMessage()));
        }
        if (!empty($issue_result) && !is_wp_error($issue_result)) {
            badgeos_obf_set_notice(__('Badge issued successfully.', 'badgeos'), 'success');
            $success = true;
        } elseif (is_wp_error($issue_result)) {
            badgeos_obf_set_notice(sprintf(__('Badge issuing failed. %s', 'badgeos'), $issue_result->get_error_message()));
        } else if (empty($notice)) {
            badgeos_obf_set_notice(__('Badge issuing failed.', 'badgeos'));
        }
        
    }
    
    return $success;
}

add_action( 'all_admin_notices', 'badgeos_obf_notice' );

function badgeos_obf_set_notice($message, $type = 'error') {
    if (!empty($message)) {
        $notice = array('type' => $type, 'message' => $message);
        update_option( 'obf_notice', $notice  );
    }
    
}
/**
 * Displays notice messages from Open Badge Factory
 * @since  1.4.5
 * @return void
 */
function badgeos_obf_notice() {
        $plugin_name = 'open-badge-factory';
	if (  !( $has_notice = get_option( 'obf_notice' ) ) ) // substr(get_current_screen()->id, 0, strlen($pluin_name)) != $plugin_name ||
            return;
		
        $type = $has_notice['type'];
        $message = $has_notice['message'];
	// If we have an error message, we'll display it
        if ($type == 'error') {
            echo '<div id="message" class="error"><p>'. $message .'</p></div>';
        } else {
            echo '<div id="message" class="notice notice-'.$type.'"><p>'. $message .'</p></div>';
        }
	
	// and then delete it
	delete_option( 'obf_notice' );
}

function badgeos_obf_show_advanced_features() {
    $badgeos_settings = badgeos_obf_get_settings();
    //load settings
    $show_advanced_features = ( isset( $badgeos_settings['show_advanced_features'] ) ) ? $badgeos_settings['show_advanced_features'] == 'enabled' : false;
    return $show_advanced_features;
} 			
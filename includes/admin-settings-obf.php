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

                $certDir = realpath($options['obf_cert_dir']);

                // Certificate directory not writable.
                if (!is_writable($certDir)) {
                    $error .= '<p>'. __( 'API certificate dir is not writeable.', 'badgeos' ). '</p>';
                    badgeos_obf_get_api_key_error( $error );
                }
                
                if (!empty($apikey)) {
                    $clientId = badgeos_obf_get_api_cert($apikey, $certDir);
                    if (false !== $clientId) {
                        $clean_options['obf_client_id'] = sanitize_text_field($clientId);
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
        badgeos_obf_get_api_key_error(json_encode($clean_options));
	return $clean_options;

}

/**
 * Retrieves Open Badge Factory api key via username/password
 * @since  1.4.5
 * @param  string $username OBF user email
 * @param  string $password OBF user passowrd
 * @return string           API client id on success, false otherwise
 */
function badgeos_obf_get_api_cert( $apikey, $certDir ) {
        global $badgeos_obf;

	$obf_settings = $badgeos_obf->obf_settings;
        if (!empty($certDir)) {
            $obf_settings = array_merge($obf_settings, array('obf_cert_dir' => $certDir));
        }
        
        $client = ObfClient::get_instance(null, $obf_settings);
        $errorDetails = '';
	$success = $client->authenticate($apikey);
        if (true !== $success) {
            $error = '<p>'. sprintf( __( 'There was an error creating an Open Badge Factory API certificate: %s', 'badgeos' ), $errorDetails ) . '</p>';
            return badgeos_obf_get_api_key_error( $error );
        } else if (!empty($client->get_client_id())) {
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
		return badgeos_obf_get_api_key_error( $error );
	}

	// If the response resulted from potentially bad credentials
	if ( '401' == wp_remote_retrieve_response_code( $response ) ) {
		$error = '<p>'. __( 'There was an error getting a Open Badge Factory API Key: Please check your username and password.', 'badgeos' ) . '</p>';
		// Save our error message.
		return badgeos_obf_get_api_key_error( $error );
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

	if ( get_current_screen()->id != 'badgeos_page_badgeos_sub_obf_integration' || !( $has_notice = get_option( 'obf_api_key_error' ) ) )
		return;

	// If we have an error message, we'll display it
	echo '<div id="message" class="error">'. $has_notice .'</div>';
	// and then delete it
	delete_option( 'obf_api_key_error' );
}

/**
 * BadgeOS Open Badge Factory Integration settings page.
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
			<p><?php printf( __( '<a href="%1$s" target="_blank">Open Badge Factory</a> Support is coming! <a href="%1$s" target="_blank">Learn more</a>.  <br /><br />If you do not yet have a Open Badge Factory account, <a href="%1$s" target="_blank">create one now</a>. It\'s free.', 'badgeos' ), 'https://openbadgefactory.com/signup' ); ?></p>

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
				if ( empty( $obf_settings['obf_client_id'] ) && '__EMPTY__' !== $obf_settings['obf_client_id']) {
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

		<p class="toggle"><?php printf( __( 'Enter your %s to access your API key.', 'badgeos' ), '<a href="#show-api-key">'. __( 'Open Badge Factory account username and password', 'badgeos' ) .'</a>' ); ?></p>

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
					<label for="api_key"><?php _e( 'API Key: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<input id="api_key" type="text" name="obf_settings[api_key]" class="widefat" value="<?php echo esc_attr( $obf_settings[ 'api_key' ] ); ?>" style="max-width: 1000px;" />
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
        wp_nonce_field( 'badgeos_obf_api_key_nonce', 'badgeos_obf_api_key_nonce' );
?>
	<div id="obf-settings">
		<h3><?php _e( 'Open Badge Factory API Key', 'badgeos' ); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="api_key"><?php _e( 'API Key: ', 'badgeos' ); ?></label></th>
				<td>
					<input id="api_key" type="text" name="obf_settings[api_key]" class="widefat" value="<?php echo esc_attr( $obf_settings[ 'api_key' ] ); ?>" />
				</td>
			</tr>
		</table>

		<h3><?php _e( 'Open Badge Factory Field Mapping', 'badgeos' ); ?></h3>

		<p><?php _e( 'Customize which Open Badge Factory fields for badge creation and issuing (listed on the left) match which WordPress and BadgeOS fields (listed to the right).', 'badgeos' ); ?>
			<br /><?php _e( 'When badges are created and issued, the info sent to Open Badge Factory will rely on the global mapping found here. (Note: Visit the edit screen for each achievement you create in BadgeOS to further configure the sharing and Open Badge Factory settings for that achievement.)', 'badgeos' ); ?>
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
			<tr valign="top">
				<th scope="row">
					<label for="obf_badge_testimonial"><?php _e( 'Testimonial: ', 'badgeos' ); ?></label>
				</th>
				<td><select id="obf_badge_testimonial" name="obf_settings[obf_badge_testimonial]">
						<?php echo obf_fieldmap_list_options( $obf_settings[ 'obf_badge_testimonial' ] ); ?>
					</select></td>
			</tr>
			<tr valign="top">
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

		<h3><?php _e( 'Open Badge Factory Notification Settings', 'badgeos' ); ?></h3>
		<p><?php _e( 'Send custom notifications to users when they earn a Open Badge Factory-enabled achievement.', 'badgeos' ); ?></p>

		<table class="form-table obf-notifications">
			<tr valign="top" class="obf-notifications-enable-message">
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

			<tr valign="top" class="obf-notifications-message">
				<th scope="row">
					<label for="obf_badge_sendemail"><?php _e( 'Custom notification message: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<textarea id="obf_badge_sendemail_message" name="obf_settings[obf_badge_sendemail_message]" cols="80" rows="10"><?php echo esc_textarea( $obf_settings[ 'obf_badge_sendemail_message' ] ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php do_action( 'obf_settings', $obf_settings ); ?>
	</div>
<?php

}

function badgeos_obf_import_callback($options = array()) {
    global $badgeos_obf, $wpdb;
    
    $create_duplicates = $options['on_duplicate'] === 'create';
    $pre_existing = array();
    $badge_selections= array();
    if (count($options) > 0) {
        $badge_selections = $options['badges'];
        foreach($badge_selections as $option => $badge_id) {
            $query = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} pm "
                    . "LEFT JOIN {$wpdb->posts} p ON (pm.post_id = p.id) WHERE p.post_status != 'trash' AND pm.meta_key = '_badgeos_obf_badge_id' AND pm.meta_value = %s LIMIT 1", $badge_id);
            $exists = $wpdb->get_col($query);
            if (empty($exists) || $create_duplicates) {
                $post_id = $badgeos_obf->import_obf_badge(null, $badge_id, false);
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
    return array();
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
        
        $badges = $badgeos_obf->obf_client->get_badges();
        $single_select = array_key_exists('single_select', $_REQUEST) && $_REQUEST['single_select'] === 'true' ? true : false;
?>
	<div class="wrap" >
            <form method="post" action="options.php">
            <?php
                settings_fields( 'obf_import_group' );
            ?>
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


add_action('admin_footer', 'my_post_import_button');
function my_post_import_button() {
    $screen = get_current_screen();
    if ( $screen->id != "edit-badges" )   // Only add to edit-badges
        return;
    
    add_thickbox();
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            //$('<a href="admin.php?page=badgeos_sub_obf_import&TB_iframe=true&width=600&height=550" id="import_obf_badges" class="page-title-action obf-import thickbox">Import from Open Badge Factory</a>').appendTo(".wrap h1");
            $('<a href="admin.php?page=badgeos_sub_obf_import&single_select=true" id="import_obf_badges" class="page-title-action obf-import"><?php _e('Pick a badge', 'badgeos'); ?></a>').appendTo(".wrap h1");
            $('<option>').val('import_obf_badges').text('Import from Open Badge Factory').appendTo('select[name="action"]');
        });
    </script>
    <?php
}

/**
 * Add a metabox to import badge from OBF.
 */
function add_obf_import_box(){
$screen = get_current_screen();
    if ($screen->action === 'add') { // Only show the metabox on add, not on edit
        add_meta_box('obf-import-metabox', 'Open Badge Factory', 'obf_import_metabox', 'badges', 'side', 'high');
    }
}

add_action('add_meta_boxes', 'add_obf_import_box'); 

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
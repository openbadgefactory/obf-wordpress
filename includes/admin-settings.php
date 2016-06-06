<?php
/**
 * Admin Settings Pages
 *
 * @package BadgeOS
 * @subpackage Admin
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Register BadgeOS Settings with Settings API.
 * @return void
 */
function badgeos_register_settings() {
	register_setting( 'badgeos_settings_group', 'badgeos_settings', 'badgeos_settings_validate' );
	register_setting( 'credly_settings_group', 'credly_settings', 'badgeos_credly_settings_validate' );
}
add_action( 'admin_init', 'badgeos_register_settings' );

/**
 * Grant BadgeOS manager role ability to edit BadgeOS settings.
 *
 * @since  1.4.0
 *
 * @param  string $capability Required capability.
 * @return string             Required capability.
 */
function badgeos_edit_settings_capability( $capability ) {
	return badgeos_get_manager_capability();
}
add_filter( 'option_page_capability_badgeos_settings_group', 'badgeos_edit_settings_capability' );
add_filter( 'option_page_capability_credly_settings_group', 'badgeos_edit_settings_capability' );

/**
 * BadgeOS Settings validation
 *
 * @param  string $input The input we want to validate
 * @return string        Our sanitized input
 */
function badgeos_settings_validate( $input = '' ) {

	// Fetch existing settings
	$original_settings = badgeos_obf_get_settings();

	// Sanitize the settings data submitted
	$input['minimum_role'] = isset( $input['minimum_role'] ) ? sanitize_text_field( $input['minimum_role'] ) : $original_settings['minimum_role'];
        $input['achievement_creator_role'] = isset( $input['achievement_creator_role'] ) ? sanitize_text_field( $input['achievement_creator_role'] ) : $original_settings['achievement_creator_role'];
	$input['submission_manager_role'] = isset( $input['submission_manager_role'] ) ? sanitize_text_field( $input['submission_manager_role'] ) : $original_settings['submission_manager_role'];
	$input['debug_mode'] = isset( $input['debug_mode'] ) ? sanitize_text_field( $input['debug_mode'] ) : $original_settings['debug_mode'];
	$input['ms_show_all_achievements'] = isset( $input['ms_show_all_achievements'] ) ? sanitize_text_field( $input['ms_show_all_achievements'] ) : $original_settings['ms_show_all_achievements'];
        $input['show_advanced_features'] = isset( $input['show_advanced_features'] ) ? sanitize_text_field( $input['show_advanced_features'] ) : $original_settings['show_advanced_features'];
        $input['svg_support'] = isset( $input['svg_support'] ) ? sanitize_text_field( $input['svg_support'] ) : $original_settings['svg_support'];
        if (array_key_exists('db_version', $original_settings)) {
            $input['db_version'] = $original_settings['db_version'];
        }
        
        badgeos_register_achievement_capabilites($input['achievement_creator_role']);
	// Allow add-on settings to be sanitized
	do_action( 'badgeos_settings_validate', $input );
        badgeos_obf_clear_settings_cache();

	// Return sanitized inputs
	return $input;

}

/**
 * Credly API Settings validation
 * @since  1.0.0
 * @param  array $options Form inputs data
 * @return array          Sanitized form input data
 */
function badgeos_credly_settings_validate( $options = array() ) {

	// If attempting to retrieve an api key from credly
	if (
		empty( $options['api_key'] )
		&& isset( $_POST['badgeos_credly_api_key_nonce'] )
		&& wp_verify_nonce( $_POST['badgeos_credly_api_key_nonce'], 'badgeos_credly_api_key_nonce' )
		&& 'false' !== $options['credly_enable'] // Only continue if credly is enabled
	) {
		// sanitize
		$username = ( !empty( $options['credly_user'] ) ? sanitize_text_field( $options['credly_user'] ) : '' );
		$password = ( !empty( $options['credly_password'] ) ? sanitize_text_field( $options['credly_password'] ) : '' );

		if ( ! is_email( $username ) || empty( $password ) ) {

			$error = '';

			if ( ! is_email( $username ) )
				$error .= '<p>'. __( 'Please enter a valid email address in the username field.', 'badgeos' ). '</p>';

			if ( empty( $password ) )
				$error .= '<p>'. __( 'Please enter a password.', 'badgeos' ). '</p>';

			// Save our error message.
			badgeos_credly_get_api_key_error( $error );

			// Keep the user/pass
			$clean_options['credly_user'] = $username;
			$clean_options['credly_password'] = $password;
		} else {
			unset( $options['api_key'] );
			$clean_options['api_key'] = badgeos_credly_get_api_key( $username, $password );
			// No key?
			if ( !$clean_options['api_key'] ) {
				// Keep the user/pass
				$clean_options['credly_user'] = $username;
				$clean_options['credly_password'] = $password;
			}
		}

	}

	// we're not saving these values
	unset( $options['credly_user'] );
	unset( $options['credly_password'] );
	// sanitize all our options
	foreach ( $options as $key => $opt ) {
		$clean_options[$key] = sanitize_text_field( $opt );
	}

	return $clean_options;

}

/**
 * Retrieves credly api key via username/password
 * @since  1.0.0
 * @param  string $username Credly user email
 * @param  string $password Credly user passowrd
 * @return string           API key on success, false otherwise
 */
function badgeos_credly_get_api_key( $username = '', $password = '' ) {

	$url = BADGEOS_CREDLY_API_URL . 'authenticate/';

	$response = wp_remote_post( $url, array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password )
		),
		'sslverify' => false
	) );

	// If the response is a WP error
	if ( is_wp_error( $response ) ) {
		$error = '<p>'. sprintf( __( 'There was an error getting a Credly API Key: %s', 'badgeos' ), $response->get_error_message() ) . '</p>';
		return badgeos_credly_get_api_key_error( $error );
	}

	// If the response resulted from potentially bad credentials
	if ( '401' == wp_remote_retrieve_response_code( $response ) ) {
		$error = '<p>'. __( 'There was an error getting a Credly API Key: Please check your username and password.', 'badgeos' ) . '</p>';
		// Save our error message.
		return badgeos_credly_get_api_key_error( $error );
	}

	$api_key = json_decode( $response['body'] );
	$api_key = $api_key->data->token;

	return $api_key;

}

/**
 * Saves an error string for display on Credly settings page
 * @since  1.0.0
 * @param  string $error Error message
 * @return bool          False after updating option
 */
function badgeos_credly_get_api_key_error( $error = '' ) {

	// Temporarily store our error message.
	update_option( 'credly_api_key_error', $error );
	return false;
}

add_action( 'all_admin_notices', 'badgeos_credly_api_key_errors' );
/**
 * Displays error messages from Credly API key retrieval
 * @since  1.0.0
 * @return void
 */
function badgeos_credly_api_key_errors() {
        $plugin_name = 'open-badge-factory';
	if ( get_current_screen()->id != $plugin_name . '_page_badgeos_sub_credly_integration' || !( $has_notice = get_option( 'credly_api_key_error' ) ) )
		return;

	// If we have an error message, we'll display it
	echo '<div id="message" class="error">'. $has_notice .'</div>';
	// and then delete it
	delete_option( 'credly_api_key_error' );
}

/**
 * BadgeOS main settings page output
 * @since  1.0.0
 * @return void
 */
function badgeos_settings_page() {
	?>
	<div class="wrap" >
		<div id="icon-options-general" class="icon32"></div>
		<h2><?php _e( 'Open Badge Factory Settings', 'badgeos' ); ?></h2>

		<form method="post" action="options.php">
			<?php settings_fields( 'badgeos_settings_group' ); ?>
			<?php $badgeos_settings = badgeos_obf_get_settings(); ?>
			<?php
			//load settings
			$minimum_role = ( isset( $badgeos_settings['minimum_role'] ) ) ? $badgeos_settings['minimum_role'] : 'manage_options';
                        $achievement_creator_role = ( isset( $badgeos_settings['achievement_creator_role'] ) ) ? $badgeos_settings['achievement_creator_role'] : 'manage_options';
			$submission_manager_role = ( isset( $badgeos_settings['submission_manager_role'] ) ) ? $badgeos_settings['submission_manager_role'] : 'manage_options';
			$submission_email = ( isset( $badgeos_settings['submission_email'] ) ) ? $badgeos_settings['submission_email'] : '';
                        $show_advanced_features = ( isset( $badgeos_settings['show_advanced_features'] ) ) ? $badgeos_settings['show_advanced_features'] : 'disabled';
			$submission_email_addresses = ( isset( $badgeos_settings['submission_email_addresses'] ) ) ? $badgeos_settings['submission_email_addresses'] : '';
			$debug_mode = ( isset( $badgeos_settings['debug_mode'] ) ) ? $badgeos_settings['debug_mode'] : 'disabled';
			$ms_show_all_achievements = ( isset( $badgeos_settings['ms_show_all_achievements'] ) ) ? $badgeos_settings['ms_show_all_achievements'] : 'disabled';
                        $svg_support = ( isset( $badgeos_settings['svg_support'] ) ) ? $badgeos_settings['svg_support'] : 'disabled';

			/*email templates */
			//$blogid = $site["blog_id"];
			$email_subject = isset($badgeos_settings["email_subject"]) ? $badgeos_settings["email_subject"] : '';
			$email_body = isset($badgeos_settings["email_body"]) ? $badgeos_settings["email_body"] : '';
			$email_link_text = isset($badgeos_settings["email_link_text"]) ? $badgeos_settings["email_link_text"] : '';
			$email_footer = isset($badgeos_settings["email_footer"]) ? $badgeos_settings["email_footer"] : '';
			wp_nonce_field( 'badgeos_settings_nonce', 'badgeos_settings_nonce' );
			?>
			<table class="form-table">
				<?php if ( current_user_can( 'manage_options' ) ) { ?>
					<tr valign="top"><th scope="row"><label for="minimum_role"><?php _e( 'Minimum Role to Administer Open Badge Factory plugin: ', 'badgeos' ); ?></label></th>
						<td>
							<select id="minimum_role" name="badgeos_settings[minimum_role]">
								<option value="manage_options" <?php selected( $minimum_role, 'manage_options' ); ?>><?php _e( 'Administrator', 'badgeos' ); ?></option>
								<option value="delete_others_posts" <?php selected( $minimum_role, 'delete_others_posts' ); ?>><?php _e( 'Editor', 'badgeos' ); ?></option>
								<option value="publish_posts" <?php selected( $minimum_role, 'publish_posts' ); ?>><?php _e( 'Author', 'badgeos' ); ?></option>
							</select>
						</td>
					</tr>
					<tr valign="top"><th scope="row"><label for="submission_manager_role"><?php _e( 'Minimum Role to Administer Submissions/Nominations: ', 'badgeos' ); ?></label></th>
						<td>
							<select id="submission_manager_role" name="badgeos_settings[submission_manager_role]">
								<option value="manage_options" <?php selected( $submission_manager_role, 'manage_options' ); ?>><?php _e( 'Administrator', 'badgeos' ); ?></option>
								<option value="delete_others_posts" <?php selected( $submission_manager_role, 'delete_others_posts' ); ?>><?php _e( 'Editor', 'badgeos' ); ?></option>
								<option value="publish_posts" <?php selected( $submission_manager_role, 'publish_posts' ); ?>><?php _e( 'Author', 'badgeos' ); ?></option>
							</select>
						</td>
					</tr>
                                        <tr valign="top"><th scope="row"><label for="achievement_creator_role"><?php _e( 'Minimum Role to create Badges/Achievements: ', 'badgeos' ); ?></label></th>
						<td>
							<select id="achievement_creator_role" name="badgeos_settings[achievement_creator_role]">
								<option value="manage_options" <?php selected( $achievement_creator_role, 'manage_options' ); ?>><?php _e( 'Administrator', 'badgeos' ); ?></option>
								<option value="delete_others_posts" <?php selected( $achievement_creator_role, 'delete_others_posts' ); ?>><?php _e( 'Editor', 'badgeos' ); ?></option>
								<option value="publish_posts" <?php selected( $achievement_creator_role, 'publish_posts' ); ?>><?php _e( 'Author', 'badgeos' ); ?></option>
                                                                <option value="edit_posts" <?php selected( $achievement_creator_role, 'edit_posts' ); ?>><?php _e( 'Contributor', 'badgeos' ); ?></option>
							</select>
						</td>
					</tr>
				<?php } /* endif current_user_can( 'manage_options' ); */ ?>
				<tr valign="top"><th scope="row"><label for="submission_email"><?php _e( 'Send email when submissions/nominations are received:', 'badgeos' ); ?></label></th>
					<td>
						<select id="submission_email" name="badgeos_settings[submission_email]">
							<option value="enabled" <?php selected( $submission_email, 'enabled' ); ?>><?php _e( 'Enabled', 'badgeos' ) ?></option>
							<option value="disabled" <?php selected( $submission_email, 'disabled' ); ?>><?php _e( 'Disabled', 'badgeos' ) ?></option>
						</select>
					</td>
				</tr>
                                
                                <tr valign="top"><th scope="row"><label for="show_advanced_features"><?php _e( 'Show advanced features:', 'badgeos' ); ?></label></th>
					<td>
						<select id="show_advanced_features" name="badgeos_settings[show_advanced_features]">
							<option value="enabled" <?php selected( $show_advanced_features, 'enabled' ); ?>><?php _e( 'Enabled', 'badgeos' ) ?></option>
							<option value="disabled" <?php selected( $show_advanced_features, 'disabled' ); ?>><?php _e( 'Disabled', 'badgeos' ) ?></option>
						</select>
					</td>
				</tr>
                                
				<tr valign="top"><th scope="row"><label for="submission_email_addresses"><?php _e( 'Notification email addresses:', 'badgeos' ); ?></label></th>
					<td>
						<input id="submission_email_addresses" name="badgeos_settings[submission_email_addresses]" type="text" value="<?php echo esc_attr( $submission_email_addresses ); ?>" class="regular-text" />
						<p class="description"><?php _e( 'Comma-separated list of email addresses to send submission/nomination notifications, in addition to the Site Admin email.', 'badgeos' ); ?></p>
					</td>
				</tr>
                                <tr valign="top"><th scope="row"><label for="svg_support"><?php _e( 'SVG Support:', 'badgeos' ); ?></label></th>
					<td>
						<select id="svg_support" name="badgeos_settings[svg_support]">
							<option value="disabled" <?php selected( $svg_support, 'disabled' ); ?>><?php _e( 'Disabled', 'badgeos' ) ?></option>
							<option value="enabled" <?php selected( $svg_support, 'enabled' ); ?>><?php _e( 'Enabled', 'badgeos' ) ?></option>
						</select>
                                                <p class="description"><?php _e('SVG-image support is required for Badges with images designed inside Open Badge Factory. If you are not already using another plugin that provides SVG-support, please keep this enabled. Enabling this does not allow users to upload SVG-images, it only affects badges imported from Open Badge Factory.', 'badgeos'); ?></p>
					</td>
				</tr>
				<tr valign="top"><th scope="row"><label for="debug_mode"><?php _e( 'Debug Mode:', 'badgeos' ); ?></label></th>
					<td>
						<select id="debug_mode" name="badgeos_settings[debug_mode]">
							<option value="disabled" <?php selected( $debug_mode, 'disabled' ); ?>><?php _e( 'Disabled', 'badgeos' ) ?></option>
							<option value="enabled" <?php selected( $debug_mode, 'enabled' ); ?>><?php _e( 'Enabled', 'badgeos' ) ?></option>
						</select>
					</td>
				</tr>
				<?php
				/* Email template*/
				?>
				<tr >
					<tr valign="top"><th scope="row"><label for="debug_mode"><?php _e( 'Email template:', 'badgeos' ); ?></label></th>
					<td>
						<div class="form-group">
						    <label for="email-subject"><b><?php _e('Email subject', 'badgeos'); ?></b></label>
						    <div >
						      <input type="subject" name="badgeos_settings[email_subject]" class="form-control regular-text" id="email-subject" pattern="^[^\r\n]+$" maxlength="255" value="<?php echo esc_attr( $email_subject ); ?>" >
						    </div>
						</div>
						<div class="form-group">
						    <label for="email-body" ><b><?php _e('Message body', 'badgeos'); ?></b></label>
						    <div >
						      <textarea name="badgeos_settings[email_body]" id="email-body" rows="10" cols="40" maxlength="65535" class="form-control" value="<?php echo esc_textarea( $email_body ); ?>"><?php echo esc_textarea( $email_body ); ?></textarea>
						    </div>
						</div>
						<div class="form-group">
						  <label for="email-link-text"><b><?php _e('Button link text', 'badgeos'); ?></b></label>
						  <div >
						    <input type="text" name="badgeos_settings[email_link_text]" class="form-control regular-text" id="email-link-text" pattern="[a-zA-Z0-9]+[a-zA-Z0-9 ]+" maxlength="255" value="<?php echo esc_attr( $email_link_text ); ?>">
						    <p class="description"><?php _e('Text used in receive badge link button.', 'badgeos'); ?></p>
						  </div>
						</div>
					  <div class="form-group">
					    <label for="email-footer"><b><?php _e('Message footer', 'badgeos'); ?></b></label>
					    <div >
					      <textarea name="badgeos_settings[email_footer]" id="email-footer" rows="5" cols="40" maxlength="65535" class="form-control" value="<?php echo esc_textarea( $email_footer ); ?>"><?php echo esc_textarea( $email_footer ); ?></textarea>
					    </div>
					  </div>
					</td>
				</tr>
				<?php
				// check if multisite is enabled & if plugin is network activated
				if ( is_super_admin() ){
					if ( is_multisite() ) {
					?>
						<tr valign="top"><th scope="row"><label for="debug_mode"><?php _e( 'Show achievements earned across all sites on the network:', 'badgeos' ); ?></label></th>
							<td>
								<select id="debug_mode" name="badgeos_settings[ms_show_all_achievements]">
									<option value="disabled" <?php selected( $ms_show_all_achievements, 'disabled' ); ?>><?php _e( 'Disabled', 'badgeos' ) ?></option>
									<option value="enabled" <?php selected( $ms_show_all_achievements, 'enabled' ); ?>><?php _e( 'Enabled', 'badgeos' ) ?></option>
								</select>
							</td>
						</tr>
					<?php
					}
				}
				do_action( 'badgeos_settings', $badgeos_settings ); ?>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Save Settings', 'badgeos' ); ?>" />
			</p>
			<!-- TODO: Add settings to select WP page for archives of each achievement type.
				See BuddyPress' implementation of this idea.  -->
		</form>
	</div>
	<?php
}


/**
 * Adds additional options to the BadgeOS Settings page
 *
 * @since 1.0.0
 */
function badgeos_license_settings() {

	// Get our licensed add-ons
	$licensed_addons = apply_filters( 'badgeos_licensed_addons', array() );

	// If we have any licensed add-ons
	if ( ! empty( $licensed_addons ) ) {

		// Output the header for licenses
		echo '<tr><td colspan="2"><hr/><h2>' . __( 'Open Badge Factory Add-on Licenses', 'badgeos' ) . '</h2></td></tr>';

		// Sort our licenses alphabetially
		ksort( $licensed_addons );

		// Output each individual licensed product
		foreach ( $licensed_addons as $slug => $addon ) {
			$status = ! empty( $addon['license_status'] ) ? $addon['license_status'] : 'inactive';
			echo '<tr valign="top">';
			echo '<th scope="row">';
			echo '<label for="badgeos_settings[licenses][' . $slug . ']">' . urldecode( $addon['item_name'] ) . ': </label></th>';
			echo '<td>';
			echo '<input type="text" size="30" name="badgeos_settings[licenses][' . $slug . ']" id="badgeos_settings[licenses][' . $slug . ']" value="' . $addon['license'] . '" />';
			echo ' <span class="badgeos-license-status ' . $status . '">' . sprintf( __( 'License Status: %s' ), '<strong>' . ucfirst( $status ) . '</strong>' ) . '</span>';
			echo '</td>';
			echo '</tr>';
		}
	}

}
add_action( 'badgeos_settings', 'badgeos_license_settings', 0 );
add_action( 'badgeos_settings', 'badgeos_obf_clear_settings_cache', 10 );

/**
 * Add-ons settings page
 *
 * @since  1.0.0
 */
function badgeos_add_ons_page() {
	$image_url = $GLOBALS['badgeos']->directory_url .'images/';
	?>
	<div class="wrap badgeos-addons">
		<div id="icon-options-general" class="icon32"></div>
		<h2><?php _e( 'Add-Ons', 'badgeos' ); ?></h2>
		<p><?php _e( 'These add-ons extend the functionality of the Open Badge Factory -plugin.', 'badgeos' ); ?></p>
		<?php echo badgeos_add_ons_get_feed(); ?>
	</div>
	<?php
}

/**
 * Get all add-ons from the BadgeOS catalog feed.
 *
 * @since  1.2.0
 * @return string Concatenated markup from feed, or error message
*/
function badgeos_add_ons_get_feed() {

	// Attempt to pull back our cached feed
	$feed = get_transient( 'badgeos_add_ons_feed' );

	// If we don't have a cached feed, pull back fresh data
	if ( empty( $feed ) ) {
                $coming_soon = true;
                if (true == $coming_soon) {
                    $feed = array();
                    $feed['body'] = '<div class"coming-soon"><p>Coming soon!</p></div>';
                    set_transient( 'badgeos_add_ons_feed', $feed, HOUR_IN_SECONDS );
                } else {
                    // Retrieve and parse our feed
                    $feed = wp_remote_get( 'http://badgeos.org/?feed=addons', array( 'sslverify' => false ) );
                }
                
		if ( ! is_wp_error( $feed ) ) {
			if ( isset( $feed['body'] ) && strlen( $feed['body'] ) > 0 ) {
				$feed = wp_remote_retrieve_body( $feed );
				$feed = str_replace( '<html><body>', '', $feed );
				$feed = str_replace( '</body></html>', '', $feed );
				// Cache our feed for 1 hour
				set_transient( 'badgeos_add_ons_feed', $feed, HOUR_IN_SECONDS );
			}
		} else {
			$feed = '<div class="error"><p>' . __( 'There was an error retrieving the add-ons list from the server. Please try again later.', 'badgeos' ) . '</div>';
		}
	}

	// Return our feed, or error message
	return $feed;
}

/**
 * Help and Support settings page
 * @since  1.0.0
 * @return void
 */
function badgeos_help_support_page() { ?>
	<div class="wrap" >
		<div id="icon-options-general" class="icon32"></div>
		<h2><?php _e( 'Open Badge Factory Help and Support', 'badgeos' ); ?></h2>
                <div class="card">
                    <h2><?php _e( 'About Open Badge Factory', 'badgeos' ); ?>:</h2>
                    <p><?php printf(
                            __( 'Open Badge Factory&trade; -plugin, is a plugin to WordPress that allows your site\'s users to complete tasks, demonstrate achievements, and earn badges. You define the achievement types, organize your requirements any way you like, and choose from a range of options to determine whether each task or requirement has been achieved. Badges earned in Open Badge Factory -plugin are Mozilla OBI compatible.', 'badgeos' ),
                            '<a href="https://openbadgefactory.com/" target="_blank">Open Badge Factory</a>'
                    ); ?></p>
                    <p><?php printf(
                            __( "Open Badge Factory -plugin is extremely extensible. Check out examples of what we've built with it, and stay connected to the project site for updates, add-ins and news. Share your ideas and code improvements on %s so we can keep making Open Badge Factory better for everyone.", 'badgeos' ),
                            '<a href="https://github.com/discendum" target="_blank">GitHub</a>'
                    ); ?></p>
                    <?php do_action( 'badgeos_help_support_page_about' ); ?>
                </div>
		
                <?php
                if (badgeos_user_can_manage_achievements()) {
                    ?>
                <div class="card">
                    <h2><?php _e('First steps after installing the plugin', 'badgeos'); ?></h2>
                    <h3><?php _e('I. Setting up the plugin', 'badgeos'); ?></h3>

                    <ol>
                    <li><?php _e('Get the Open Badge Factory API certificate from Open Badge Factory (<strong>Admin tools > Api Key > Generate certificate signing request token', 'badgeos'); ?></strong>)
                    <li><?php _e('Enter the generated Open Badge Factory API key in your wordpress\' <strong>Open Badge Factory > OBF Integration</strong> -page to enable badge sharing.', 'badgeos'); ?></li>
                    <li><?php _e('Set up awarding rules for your badges in your wordpress\' <strong>Open Badge Factory > Badges</strong> -page.', 'badgeos'); ?></li>
                    </ol>

                    <?php add_thickbox(); $apikey_image_url = badgeos_get_directory_url() .'/doc/install/generated_api_key.png' ?>
                    <div id="api-key-image-full" style="display:none;">
                         <p><img class="obf-help-image" src="<?php echo $apikey_image_url; ?>" alt="Generated API key" title="Generated API key" /></li></p>
                    </div>
                    <a href="#TB_inline?width=600&height=550&inlineId=api-key-image-full" class="thickbox">
                        <img class="obf-help-image thumbnail" src="<?php echo $apikey_image_url; ?>" alt="Generated API key" title="Generated API key" /></li>
                    </a>


                    <?php $step_image_url = badgeos_get_directory_url().'/doc/install/wp_plugin_steps.png'; ?>
                    <div id="steps-image-full" style="display:none;">
                         <p><img class="obf-help-image" src="<?php echo $step_image_url; ?>" alt="Required steps" title="Required steps" /></li></p>
                    </div>
                    <h3><?php _e('II. Creating awarding rules', 'badgeos'); ?></h3>
                    <p><?php echo sprintf(__('To create badge awarding rules you should have a badge or badges created at <a href="%s">Open Badge Factory</a>.', 'badgeos'), 'https://openbadgefactory.com'); ?></p>
                    <p><?php _e('If everything is set up, you should have a <strong>Open Badge Factory > Badges</strong> -menu item in your wordpress admin dashboard.', 'badgeos'); ?></p>
                    <p><?php _e('Badges -page contains all the ready to be issued badges you\'ve created in Open Badge Factory. If a badge you have created isn\'t visible, make sure it is not set as a draft.', 'badgeos'); ?></p>
                    <p><?php _e('If you open a badge for editing, you can choose from multiple earning options. The most flexible earning option is using required steps. You can add one or multiple steps a user needs to complete in order to earn the badge.', 'badgeos'); ?></p>
                    <p>
                        <a href="#TB_inline?width=600&height=550&inlineId=steps-image-full" class="thickbox">
                            <img class="obf-help-image thumbnail" src="<?php echo $step_image_url; ?>" alt="Required steps" title="Required steps" /></li>
                        </a>
                    </p>
                    <p><?php _e('After you save a with awarding rules, the badge associated with the awarding rule will be automatically awarded to users who complete the required steps.', 'badgeos'); ?></p>
                    <p><?php _e('You can monitor user step unlocking process and achivements via the <strong>Log Entries</strong> menu.', 'badgeos'); ?></p>
                    <p><?php _e('You can also define meta-achivements by adding badges as required steps. Choose <strong>Specific Achievement of Type &gt; Badges &gt; your badge</strong> at Required Steps.', 'badgeos'); ?></p>
                    
                    <h3><?php _e('III. Advanced features', 'badgeos'); ?></h3>
                    <p><?php _e('When you are confident with setting up and creating awarding rules, you may choose to enable advanced features.', 'badgeos'); ?></p>
                    <p><?php _e('Advanced features can be enabled on the <strong>Open Badge Factory &gt; Settings</strong> -page, by changing <strong>Show advanced features</strong> to <strong>Enabled</strong>.', 'badgeos'); ?></p>
                    <p><?php _e('After enabling advanced features, you can rename achievement types, access submissions and nominations from the menu, and map badge fields to wordpress fields.', 'badgeos'); ?></p>
                </div>
                <?php
                }
                ?>
                
                
                <?php
                if (badgeos_user_can_manage_submissions()) {
                    ?>
                <div class="card">
                    <h2><?php _e('Using the advanced features', 'badgeos'); ?></h2>
                    
                    <h3><?php _e('Submissions', 'badgeos'); ?></h3>
                    <p><?php _e('You can award badges through approved submissions. Create a page or post, insert Open Badge Factory shortcode Submission Form and the Achievement ID of the badge you wish to issue. Publish the post so your users can leave submissions to earn the badge.', 'badgeos'); ?></p>
                    <p><?php _e('You can see all your users submissions at <strong>Open Badge Factory &gt; Submissions</strong> -page. Review and comment the submissions to award the badge.', 'badgeos'); ?></p>
                    <p><?php _e('You can specify the role who can approve submissions/nominations at <strong>Open Badge Factory &gt; Setting</strong> -page.', 'badgeos'); ?></p> 
                </div>
                    <?php
                }
                ?>
                
                
                <div class="card">
                    <h2><?php _e( 'Help / Support', 'badgeos' ); ?>:</h2>
                    <p><?php printf(
                            __( 'For support on using Open Badge Factory -plugin or to suggest feature enhancements, visit the %1$s. The Open Badge Factory team does perform custom development that extends the Open Badge Factory platform in some incredibly powerful ways. %2$s with inquiries. Also take a look at %3$s.', 'badgeos' ),
                            sprintf(
                                    '<a href="http://openbadgefactory.com" target="_blank">%s</a>',
                                    __( 'Open Badge Factory site', 'badgeos' )
                            ),
                            sprintf(
                                    '<a href="mailto:contact@openbadgefactory.com" target="_blank">%s</a>',
                                    __( 'Contact us', 'badgeos' )
                            ),
                            sprintf(
                                    '<a href="http://openbadgepassport.com/">%s</a>',
                                    __( 'Open Badge Passport', 'badgeos' )
                            )
                    ); ?></p>
                    <p><?php printf( __( 'Please submit bugs or issues to %s for the Open Badge Factory Project.', 'badgeos' ), '<a href="https://github.com/discendum" target="_blank">Github</a>' ); ?></p>
                    <?php do_action( 'badgeos_help_support_page_help' ); ?>
                </div>
                
                <div class="card">
                    <h2><?php _e( 'Shortcodes', 'badgeos' ); ?>:</h2>
                    <p><?php _e(
                             'With Open Badge Factory activated, the following shortcodes can be placed on any page or post within WordPress to expose a variety of Open Badge Factory functions.', 'badgeos'
                    ); ?></p>
                    <?php do_action( 'badgeos_help_support_page_shortcodes' ); ?>
                </div>
	</div>
	<?php
}

/**
 * BadgeOS Credly Integration settings page.
 * @since  1.0.0
 * @return void
 */
function badgeos_credly_options_page() {

	/**
	 * @var $badgeos_credly BadgeOS_Credly
	 */
	global $badgeos_credly;

	$credly_settings = $badgeos_credly->credly_settings;
?>
	<div class="wrap" >
		<div id="icon-options-general" class="icon32"></div>
		<h2><?php _e( 'Credly Integration Settings', 'badgeos' ); ?></h2>
		<?php settings_errors(); ?>

		<form method="post" action="options.php">
			<?php
				settings_fields( 'credly_settings_group' );
			?>
			<p><?php printf( __( '<a href="%1$s" target="_blank">Credly</a> is a universal way for people to earn and showcase their achievements and badges. With Credly Integration enabled here, badges or achievements you create on this site can automatically be created on your Credly account. As select badges are earned using the site, the badge will automatically be issued via Credly to the earner so they can easily share it on Facebook, LinkedIn, Twitter, Mozilla Backpack, their web site, blog, Credly profile or other location. Credly makes badge issuing and sharing fun and easy! <a href="%1$s" target="_blank">Learn more</a>.  <br /><br />If you do not yet have a Credly account, <a href="%1$s" target="_blank">create one now</a>. It\'s free.', 'badgeos' ), 'https://credly.com/#!/create-account' ); ?></p>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="credly_enable"><?php _e( 'Enable Badge Sharing via Credly: ', 'badgeos' ); ?></label>
					</th>
					<td>
						<select id="credly_enable" name="credly_settings[credly_enable]">
							<option value="false" <?php selected( $credly_settings['credly_enable'], 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
							<option value="true" <?php selected( $credly_settings['credly_enable'], 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
						</select>
					</td>
				</tr>
			</table>

			<?php
				// We need to get our api key
				if ( empty( $credly_settings['api_key'] ) ) {
					badgeos_credly_options_no_api( $credly_settings );
				}
				// We already have our api key
				else {
					badgeos_credly_options_yes_api( $credly_settings );
				}

				submit_button( __( 'Save Settings', 'badgeos' ) );
			?>
		</form>
	</div>
<?php

}

/**
 * BadgeOS Credly API key retrieval form.
 * @since  1.0.0
 * @param  array $credly_settings saved settings
 * @return void
 */
function badgeos_credly_options_no_api( $credly_settings = array() ) {

	wp_nonce_field( 'badgeos_credly_api_key_nonce', 'badgeos_credly_api_key_nonce' );

	if ( is_array( $credly_settings ) ) {
		foreach ( $credly_settings as $key => $opt ) {
			if ( in_array( $key, array( 'credly_user', 'credly_password', 'api_key', 'credly_enable' ) ) ) {
				continue;
			}

			// Save our hidden form values
			echo '<input type="hidden" name="credly_settings['. esc_attr( $key ) .']" value="'. esc_attr( $opt ) .'" />';
		}
	}
?>
	<div id="credly-settings">
		<h3><?php _e( 'Get Credly API Key', 'badgeos' ); ?></h3>

		<p class="toggle"><?php _e( 'Enter your Credly account username and password to access your API key.', 'badgeos' ); ?></p>

		<p class="toggle hidden"><?php printf( __( 'Enter your %s to access your API key.', 'badgeos' ), '<a href="#show-api-key">'. __( 'Credly account username and password', 'badgeos' ) .'</a>' ); ?></p>

		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label for="credly_user"><?php _e( 'Username: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<input id="credly_user" type="text" name="credly_settings[credly_user]" class="widefat" value="<?php echo esc_attr( $credly_settings[ 'credly_user' ] ); ?>" style="max-width: 400px;" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="credly_password"><?php _e( 'Password: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<input id="credly_password" type="password" name="credly_settings[credly_password]" class="widefat" value="<?php echo esc_attr( $credly_settings[ 'credly_password' ] ); ?>" style="max-width: 400px;" />
				</td>
			</tr>
			<tr valign="top" class="hidden">
				<th scope="row">
					<label for="api_key"><?php _e( 'API Key: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<input id="api_key" type="text" name="credly_settings[api_key]" class="widefat" value="<?php echo esc_attr( $credly_settings[ 'api_key' ] ); ?>" style="max-width: 1000px;" />
				</td>
			</tr>
		</table>

		<p class="toggle"><?php printf( __( 'Already have your API key? %s', 'badgeos' ), '<a href="#show-api-key">'. __( 'Click here', 'badgeos' ) .'</a>' ); ?></p>
	</div>
<?php

}

/**
 * BadgeOS Credly Settings form (when API key has been saved).
 * @since  1.0.0
 * @param  array $credly_settings saved settings
 * @return void
 */
function badgeos_credly_options_yes_api( $credly_settings = array() ) {

?>
	<div id="credly-settings">
		<h3><?php _e( 'Credly API Key', 'badgeos' ); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="api_key"><?php _e( 'API Key: ', 'badgeos' ); ?></label></th>
				<td>
					<input id="api_key" type="text" name="credly_settings[api_key]" class="widefat" value="<?php echo esc_attr( $credly_settings[ 'api_key' ] ); ?>" />
				</td>
			</tr>
		</table>

		<h3><?php _e( 'Credly Field Mapping', 'badgeos' ); ?></h3>

		<p><?php _e( 'Customize which Credly fields for badge creation and issuing (listed on the left) match which WordPress and Open Badge Factory -plugin fields (listed to the right).', 'badgeos' ); ?>
			<br /><?php _e( 'When badges are created and issued, the info sent to Credly will rely on the global mapping found here. (Note: Visit the edit screen for each achievement you create in Open Badge Factory to further configure the sharing and awarding settings for that achievement.)', 'badgeos' ); ?>
		</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="credly_badge_title"><?php _e( 'Badge Title: ', 'badgeos' ); ?></label></th>
				<td>
					<select id="credly_badge_title" name="credly_settings[credly_badge_title]">
						<?php echo credly_fieldmap_list_options( $credly_settings[ 'credly_badge_title' ] ); ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="credly_badge_short_description"><?php _e( 'Short Description: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<select id="credly_badge_short_description" name="credly_settings[credly_badge_short_description]">
						<?php echo credly_fieldmap_list_options( $credly_settings[ 'credly_badge_short_description' ] ); ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="credly_badge_description"><?php _e( 'Description: ', 'badgeos' ); ?></label>
				</th>
				<td><select id="credly_badge_description" name="credly_settings[credly_badge_description]">
						<?php echo credly_fieldmap_list_options( $credly_settings[ 'credly_badge_description' ] ); ?>
					</select></td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="credly_badge_criteria"><?php _e( 'Criteria: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<select id="credly_badge_criteria" name="credly_settings[credly_badge_criteria]">
						<?php echo credly_fieldmap_list_options( $credly_settings[ 'credly_badge_criteria' ] ); ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="credly_badge_image"><?php _e( 'Image: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<select id="credly_badge_image" name="credly_settings[credly_badge_image]">
						<?php echo credly_fieldmap_list_options( $credly_settings[ 'credly_badge_image' ] ); ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="credly_badge_testimonial"><?php _e( 'Testimonial: ', 'badgeos' ); ?></label>
				</th>
				<td><select id="credly_badge_testimonial" name="credly_settings[credly_badge_testimonial]">
						<?php echo credly_fieldmap_list_options( $credly_settings[ 'credly_badge_testimonial' ] ); ?>
					</select></td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="credly_badge_evidence"><?php _e( 'Evidence: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<select id="credly_badge_evidence" name="credly_settings[credly_badge_evidence]">
						<?php echo credly_fieldmap_list_options( $credly_settings[ 'credly_badge_evidence' ] ); ?>
					</select>
				</td>
			</tr>
		</table>

		<h3><?php _e( 'Credly Notification Settings', 'badgeos' ); ?></h3>
		<p><?php _e( 'Send custom notifications to users when they earn a Credly-enabled achievement.', 'badgeos' ); ?></p>

		<table class="form-table credly-notifications">
			<tr valign="top" class="credly-notifications-enable-message">
				<th scope="row">
					<label for="credly_badge_sendemail_add_message"><?php _e( 'Add a global custom message to each notification: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<select id="credly_badge_sendemail_add_message" name="credly_settings[credly_badge_sendemail_add_message]">
						<option value="false"<?php selected( $credly_settings[ 'credly_badge_sendemail_add_message' ], 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
						<option value="true"<?php selected( $credly_settings[ 'credly_badge_sendemail_add_message' ], 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
					</select>
				</td>
			</tr>

			<tr valign="top" class="credly-notifications-message">
				<th scope="row">
					<label for="credly_badge_sendemail"><?php _e( 'Custom notification message: ', 'badgeos' ); ?></label>
				</th>
				<td>
					<textarea id="credly_badge_sendemail_message" name="credly_settings[credly_badge_sendemail_message]" cols="80" rows="10"><?php echo esc_textarea( $credly_settings[ 'credly_badge_sendemail_message' ] ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php do_action( 'credly_settings', $credly_settings ); ?>
	</div>
<?php

}

/**
 * Globally replace "Featured Image" text with "Achievement Image".
 *
 * @since  1.3.0
 *
 * @param  string $string Original output string.
 * @return string         Potentially modified output string.
 */
function badgeos_featured_image_metabox_title( $string = '' ) {

	// If this is a new achievement type post
	// OR this is an existing achievement type post
	// AND the text is "Featured Image"
	// ...replace the string
	if (
		(
			( isset( $_GET['post_type'] ) && in_array( $_GET['post_type'], badgeos_get_achievement_types_slugs() ) )
			|| ( isset( $_GET['post'] ) && badgeos_is_achievement( $_GET['post'] ) )
		) && 'Featured Image' == $string

	)
		$string = __( 'Achievement Image', 'badgeos' );
	elseif (
		(
			( isset( $_GET['post_type'] ) && 'achievement-type' == $_GET['post_type'] )
			|| ( isset( $_GET['post'] ) && 'achievement-type' == get_post_type( $_GET['post'] ) )
		) && 'Featured Image' == $string
	)
		$string = __( 'Default Achievement Image', 'badgeos' );

	return $string;
}
add_filter( 'gettext', 'badgeos_featured_image_metabox_title' );

/**
 * Change "Featured Image" to "Achievement Image" in post editor metabox.
 *
 * @since  1.3.0
 *
 * @param  string  $content HTML output.
 * @param  integer $ID      Post ID.
 * @return string           Potentially modified output.
 */
function badgeos_featured_image_metabox_text( $content = '', $ID = 0 ) {
	if ( badgeos_is_achievement( $ID ) )
		$content = str_replace( 'featured image', __( 'achievement image', 'badgeos' ), $content );
	elseif ( 'achievement-type' == get_post_type( $ID ) )
		$content = str_replace( 'featured image', __( 'default achievement image', 'badgeos' ), $content );

	return $content;
}
add_filter( 'admin_post_thumbnail_html', 'badgeos_featured_image_metabox_text', 10, 2 );

/**
 * Change "Featured Image" to "Achievement Image" throughout media modal.
 *
 * @since  1.3.0
 *
 * @param  array  $strings All strings passed to media modal.
 * @param  object $post    Post object.
 * @return array           Potentially modified strings.
 */
function badgeos_media_modal_featured_image_text( $strings = array(), $post = null ) {

	if ( is_object( $post ) ) {
		if ( badgeos_is_achievement( $post->ID ) ) {
			$strings['setFeaturedImageTitle'] = __( 'Set Achievement Image', 'badgeos' );
			$strings['setFeaturedImage'] = __( 'Set achievement image', 'badgeos' );
		} elseif ( 'achievement-type' == $post->post_type ) {
			$strings['setFeaturedImageTitle'] = __( 'Set Default Achievement Image', 'badgeos' );
			$strings['setFeaturedImage'] = __( 'Set default achievement image', 'badgeos' );
		}
	}

	return $strings;
}
add_filter( 'media_view_strings', 'badgeos_media_modal_featured_image_text', 10, 2 );

/**
 * Get capability required for BadgeOS administration.
 *
 * @since  1.4.0
 *
 * @return string User capability.
 */
function badgeos_get_manager_capability() {
	$badgeos_settings = badgeos_obf_get_settings();
	return isset( $badgeos_settings[ 'minimum_role' ] ) ? $badgeos_settings[ 'minimum_role' ] : 'manage_options';
}

/**
 * Get capability required for Submission management.
 *
 * @since  1.4.0
 *
 * @return string User capability.
 */
function badgeos_get_submission_manager_capability() {
	$badgeos_settings = badgeos_obf_get_settings();
	return isset( $badgeos_settings[ 'submission_manager_role' ] ) ? $badgeos_settings[ 'submission_manager_role' ] : badgeos_get_manager_capability();
}

/**
 * Get capability required for achievement/badge creation.
 *
 * @since  1.4.0
 *
 * @return string User capability.
 */
function badgeos_get_achievement_creator_capability() {
	$badgeos_settings = badgeos_obf_get_settings();
	return isset( $badgeos_settings[ 'achievement_creator_role' ] ) ? $badgeos_settings[ 'achievement_creator_role' ] : badgeos_get_manager_capability();
}

/**
 * Get the lowest capability needed.
 * This is used for the menus main item capability requirement.
 */
function badgeos_get_minimum_capability() {
    $caps = array();
    $caps[] = badgeos_get_manager_capability();
    $caps[] = badgeos_get_submission_manager_capability();
    $caps[] = badgeos_get_achievement_creator_capability();
    $lowest_to_highest = array(
        'edit_posts',
        'publish_posts',
        'delete_others_posts',
        'manage_options'
    );
    foreach ($lowest_to_highest as $cap) {
        if (in_array($cap, $caps)) {
            return $cap;
        }
    }
    return $caps[0];
}
/**
 * Check if a user can manage submissions.
 *
 * @since  1.4.0
 *
 * @param  integer $user_id User ID.
 * @return bool             True if user can manaage submissions, otherwise false.
 */
function badgeos_user_can_manage_submissions( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	return ( user_can( $user_id, badgeos_get_submission_manager_capability() ) || user_can( $user_id, badgeos_get_manager_capability() ) );
}

function badgeos_user_can_manage_achievements( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	return ( user_can( $user_id, badgeos_get_achievement_creator_capability() ) || user_can( $user_id, badgeos_get_manager_capability() ) );
}
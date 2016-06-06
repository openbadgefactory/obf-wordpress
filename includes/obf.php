<?php
/**
 * Open Badge Factory Integration
 *
 * @package BadgeOS
 * @subpackage OBF
 * @author Discendum Oy
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://openbadgefactory.com
 */

//Check if we've defined our url elsewhere, if not, set it here.
if ( !defined( 'BADGEOS_OBF_API_URL' ) )
	define( 'BADGEOS_OBF_API_URL', 'https://openbadgefactory.com/v1' );

/**
 * Open Badge Factory API -class.
 */
class BadgeOS_Obf {

	public $api_url = BADGEOS_OBF_API_URL;
	public $api_key;

	public $obf_settings = array();
        
        public $obf_client;

	public $field_title = 'post_title';
	public $field_short_description = 'post_excerpt';
	public $field_description = 'post_body';
	public $field_criteria = '';
	public $field_image = 'featured_image';
	public $field_testimonial = 'congratulations_text';
	public $field_evidence = 'permalink';
	public $send_email = true;
	public $custom_message = '';

	public $user_id = 0;
	public $user_enabled = 'true';
        
        private $achievement_types_plural = array();
        private $achievement_types_singular = array();
        
        private static $badge_cache = array();

	function __construct() {

		// Set our options based on our Obf settings
		$this->obf_settings = (array) get_option( 'obf_settings', array() );
                //unset($this->obf_settings['obf_api_url']);

		$default_settings = array(
			'api_key' => '',
                        'obf_api_url' => BADGEOS_OBF_API_URL,
                        'obf_client_id' => '__EMPTY__', // Do not use real empty. It's easier to sanitize form submissions this way.
                        'obf_cert_dir' => dirname(__DIR__) . '/pki/',
                        'obf_pki_certfile_suffix' => '-cert.pem',
                        'obf_pki_keyfile_suffix' => '.key',
			'obf_user' => '',
			'obf_password' => '',
			'obf_enable' => empty( $this->obf_settings ) ? 'false' : 'true',
			'obf_badge_title' => 'post_title',
			'obf_badge_short_description' => 'post_excerpt',
			'obf_badge_description' => 'post_body',
			'obf_badge_criteria' => '',
			'obf_badge_image' => 'featured_image',
			'obf_badge_testimonial' => 'congratulations_text',
			'obf_badge_evidence' => 'permalink',
			'obf_badge_sendemail_add_message' => 'false',
			'obf_badge_sendemail_message' => __( 'NOTE: To claim this official badge and share it on social networks click on the "Save & Share" button above. If you already have an Open Badge Passport account, simply sign in and then "Accept" the badge. If you are not a member, Create an Account (it\'s free), confirm your email address, and then "Accept" your badge.', 'badgeos' ),
                        'obf_assertion_sync' => 'true',
		);

		$this->obf_settings = array_merge( $default_settings, $this->obf_settings );

		// Title required
		if ( empty( $this->obf_settings[ 'obf_badge_title' ] ) ) {
			$this->obf_settings[ 'obf_badge_title' ] = $default_settings[ 'obf_badge_title' ];
		}

		// Attachment required
		if ( empty( $this->obf_settings[ 'obf_badge_image' ] ) ) {
			$this->obf_settings[ 'obf_badge_image' ] = $default_settings[ 'obf_badge_image' ];
		}

        $this->api_key                 = $this->obf_settings['api_key'];
		$this->api_url                 = $this->obf_settings['obf_api_url'];
		$this->field_title             = $this->obf_settings['obf_badge_title'];
		$this->field_short_description = $this->obf_settings['obf_badge_short_description'];
		$this->field_description       = $this->obf_settings['obf_badge_description'];
		$this->field_criteria          = $this->obf_settings['obf_badge_criteria'];
		$this->field_image             = $this->obf_settings['obf_badge_image'];
		$this->field_testimonial       = $this->obf_settings['obf_badge_testimonial'];
		$this->field_evidence          = $this->obf_settings['obf_badge_evidence'];
		$this->send_email              = true;
		$this->custom_message          = ( 'true' == $this->obf_settings['obf_badge_sendemail_add_message'] ) ? $this->obf_settings['obf_badge_sendemail_message'] : '';

                // Set our user settings
		if ( is_user_logged_in() ) {
			$this->user_id             = get_current_user_id();
			$this->user_enabled        = ( 'false' === get_user_meta( $this->user_id, 'obf_user_enable', true ) ? 'false' : 'true' );

			// Hook in to WordPress
			$this->hooks();
		}
                $this->obf_client = ObfClient::get_instance(null, $this->obf_settings);
                $certificate_file = $this->obf_client->get_cert_filename();
                $certificate_exists = !empty($certificate_file) && file_exists($certificate_file);
                if ($this->obf_client_id_exists() && !$certificate_exists) {
                    $this->obf_settings['obf_client_id'] = '__EMPTY__';
                }

	}
        public function obf_client_id_exists() {
            return (
                        !empty($this->obf_settings['obf_client_id']) && 
                        $this->obf_settings['obf_client_id'] != '__EMPTY__'
                    );
        }
        function import_all_obf_badges() {
            global $wpdb;
            if (!$this->obf_client_id_exists()) {
                return;
            }
            $existing = $this->obf_get_existing_badges();
            $existing_badges = array();
            $nowdate = new DateTime();
            $import_interval = 24 * 60 * 60; // Import badges at least once per day.
            $new_badge_overrides = array(
                '_badgeos_send_to_obf' => 'true',
                '_badgeos_obf_editing_disabled' => 'true'
            );
            foreach($existing as $post) {
                $post_id = $post->post_id;
                $badge_id = $post->badge_id;
                
                $existing_badges[$badge_id]['post_id'] = $post_id;
                $existing_badges[$badge_id]['modified_date'] = new DateTime($post->modified_date);
            }
            try {
                $obf_badges = $this->obf_client->get_badges();
            } catch (Exception $ex) {
                // TODO: Display error message
                return new WP_Error($ex->getCode(), $ex->getMessage());
            }
            $count = 0; $failed = false;
            foreach($obf_badges as $badge_array) {
                if ($failed) {
                    break;
                }
                $badge_id = $badge_array['id'];
                $badge_modified = DateTime::createFromFormat('U', $badge_array['mtime']);
                if (array_key_exists($badge_id, $existing_badges)) {
                    $local_modified_ago = ($nowdate->format('U') - $existing_badges[$badge_id]['modified_date']->format('U'));
                    $local_older = ($badge_modified->format('U') - $existing_badges[$badge_id]['modified_date']->format('U')) > 0;
                } else {
                    $local_older = true;
                    $local_modified_ago = 0;
                }
                if (!array_key_exists($badge_id, $existing_badges)) {
                    try {
                        $this->import_obf_badge(null, $badge_id, true, $new_badge_overrides, $badge_array);
                        $count ++;
                    } catch (Exception $ex) {
                        $failed = true;
                    }
                    
                } elseif($local_older || $local_modified_ago > $import_interval) {
                    $post_id = $existing_badges[$badge_id]['post_id'];
                    $this->import_obf_badge($post_id, $badge_id, false, array(), $badge_array);
                    $count ++;
                }
                
            }
            if ($count > 0) {
                badgeos_flush_rewrite_rules();
            }
        }

        /**
	 * Import a badge from OBF
	 *
	 * @since  1.4.6
	 * @param  string   $badge_id The badge ID on OBF
	 * @param  array    $override_fields   An array of meta fields for our badge import
	 * @return mixed              False on error. Our badge ID for our badge on success
	 */
	function import_obf_badge( $post_id = 0, $badge_id = '', $force = false, $override_fields = array(), $badge_array = null ){
            if (empty($badge_array)) {
                $badge_array = $this->obf_client->get_badge($badge_id);
            }
            $ret = false;
            $options = obf_fieldmap_get_fields();
            
            $fields_array = array(
                'name' => $this->field_title,
                'short_description' => $this->field_short_description,
                'description' => $this->field_description,
                'criteria_html' => $this->field_criteria,
                'evidence_html' => $this->field_evidence,
            );
            $fields_array = array_map(
                function ($f) { 
                    if ('post_body' === $f) { 
                        return 'post_content';

                    }
                    return $f;
                },
                $fields_array
            );

            $metafields_array = array(
                'id' => '_badgeos_obf_badge_id',
            );
            $metafield_values = array();
            foreach($metafields_array as $array_index => $field) {
                $metafield_values[$field] = $badge_array[$array_index];
            }
            
            $updated = false;
            $obf_achievement_types = $this->obf_badge_achievement_types(false);
            foreach($obf_achievement_types as $obf_achievement_type) {
                if ($updated) {
                    break;
                }
                $postObj = new stdClass();
                foreach($fields_array as $key => $field) {
                    if (array_key_exists($key, $badge_array) && !empty($field)) {
                        $postObj->{$field} = $badge_array[$key];
                    }
                }
                
                // Import with creation and modification timestamps
                if (array_key_exists('ctime', $badge_array) && is_numeric($badge_array['ctime'])) {
                    $postObj->post_date = (new DateTime("@{$badge_array['ctime']}"))->format('Y-m-d H:i:s');
                    
                }
                if (array_key_exists('mtime', $badge_array) && is_numeric($badge_array['mtime'])) {
                    $postObj->modified_date = (new DateTime("@{$badge_array['mtime']}"))->format('Y-m-d H:i:s');
                }

                // Set some settings for the post. Imported badges are not drafts
                $postObj->{'post_status'} = 'publish';
                $postObj->{'ping_status'} = 'closed';
                $postObj->{'comment_status'} = 'closed';
                $image_id = null;

                if (empty($post_id) || $post_id == 0) {
                    $postObj->{'post_type'} = $obf_achievement_type;
                    $post = new WP_Post($postObj);
                    $post_id = wp_insert_post($post);
                } else {
                    $post = get_post($post_id);
                    $updated_post = (object) array_merge((array) $post, (array) $postObj);
                    $post_id = wp_update_post($updated_post);
                    $updated = true;
                }


                if (array_key_exists('image', $badge_array)) {
                    $image_id = $this->import_obf_badge_image($post_id, $badge_array['id'], $badge_array['image']);
                    $metafield_values['_thumbnail_id'] = $image_id;
                }
                foreach($override_fields as $field => $value) {
                    $metafield_values[$field] = $value;
                }
                foreach($metafield_values as $field => $value) {
                    update_post_meta( $post_id, $field, $value );
                }
            }
                
            return $post_id;
        }
        
        function import_obf_badge_image($post_id, $badge_id, $base64_image, $force = false) {
            // gives us access to the download_url() and wp_handle_sideload() functions
            global $wpdb;
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            
            $svg_support = BadgeOS_Obf_Svg_Support::get_instance();
            $svg_support->allow_upload();
            
            $data = explode(',', $base64_image);
            $mimetype = 'image/jpg';
            if (1 === preg_match('/data:([\/\w\+]+);/', $data[0], $matches)) {
                $mimetype = $matches[1];
            }
            $extension = 'tmp';
            if (1 === preg_match('/image\/([\w]+)[;\+]/', $data[0], $matches)) {
                $extension = $matches[1];
            }
            $tmp_file = wp_tempnam($badge_id . "." . $extension);
            
            // Check if image is already imported.
            $attachement_posts = $wpdb->get_results( 
                    $wpdb->prepare(
                            "SELECT id FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.id)" . 
                            "WHERE post_type = 'attachment' AND post_mime_type = '%s' " .
                            "AND pm.meta_key = '_badgeos_obf_image_badge_id' AND pm.meta_value = '%s' " .
                            "LIMIT 1", 
                            $mimetype,
                            $badge_id), 
                    OBJECT 
                    );
            if ( false == $force && $attachement_posts && count($attachement_posts) == 1){
                $attach_id = $attachement_posts[0]->id;
                return $attach_id;
            }
            
            $ifp = fopen($tmp_file, "wb"); 

            fwrite($ifp, base64_decode($data[1])); 
            fclose($ifp); 
            
            // File should be created, now move to wordpress upload dir.
            
            // array based on $_FILE as seen in PHP file uploads
            $file = array(
                    'name' => $badge_id . '.' . $extension, // ex: wp-header-logo.png
                    'type' => $mimetype,
                    'tmp_name' => $tmp_file,
                    'error' => 0,
                    'size' => filesize($tmp_file),
            );
            
            
            $overrides = array(
                'test_form' => false,
                'test_size' => true,
                'test_upload' => true,
            );
            // Copy the temporary file into the uploads directory and delete temp file
            $results = wp_handle_sideload( $file, $overrides );
            if (file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
            
            if (!empty($results['error'])) {
		// TODO: insert any error handling here
                return new WP_Error('obf_image', 'error saving badge image');
            } else {
                    /**
                     * See http://codex.wordpress.org/Function_Reference/wp_insert_attachment
                     */
                    $filename = $results['file']; // full path to the file
                    $local_url = $results['url']; // URL to the file in the uploads dir
                    $type = $results['type']; // MIME type of the file
                    
                    $basefilename = preg_replace('/\.[^.]+$/', '', basename($filename));

                    // Get the path to the upload directory.
                    $wp_upload_dir = wp_upload_dir();

                    // perform any actions here based in the above results
                    $attachment = array(
                        'post_mime_type' => $type,
                        'post_title' => $basefilename,
                        'post_content' => '',
                        'post_status' => 'inherit',
                        'guid' => $wp_upload_dir['url'] . '/' . basename($filename)
                    );
                    $attach_id = wp_insert_attachment( $attachment, $filename, 0 );
                    
                    // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );

                    // Generate the metadata for the attachment, and update the database record.
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
                    wp_update_attachment_metadata( $attach_id, $attach_data );
                    update_post_meta($attach_id, '_badgeos_obf_image_badge_id', $badge_id);
                    
                    return $attach_id;
            }
        }
        
	/**
	 * Add any hooks into WordPress here
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function hooks() {

		//admin notice
		add_action( 'admin_notices', array( $this, 'obf_admin_notice' ) );

		// Badge Metabox
		add_action( 'add_meta_boxes', array( $this, 'badge_metabox_add' ) );
		add_action( 'save_post', array( $this, 'badge_metabox_save' ) );

		// Category search AJAX
		add_action( 'wp_ajax_search_obf_categories', array( $this, 'obf_category_search' ) );

		// Obf enable user meta setting
		add_action( 'personal_options', array( $this, 'obf_profile_setting' ), 99 );
		add_action( 'personal_options_update', array( $this, 'obf_profile_setting_save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'obf_profile_setting_save' ) );
		add_action( 'init', array( $this, 'obf_profile_setting_force_enable' ), 999 );

		// Update Obf ID on profile save
		add_action( 'personal_options_update', array( $this, 'obf_get_user_id' ) );
                
                //add_action( 'badgeos_obf_sync_assertions', array( $this, 'sync_obf_assertions' ) );
                
                // Sync non-wp assertions twice per day
                if ( ! wp_next_scheduled( 'badgeos_obf_sync_assertions' ) ) {
                    wp_schedule_event( time(), 'twicedaily', 'badgeos_obf_sync_assertions' );
                }
                
	}

	/**
	 * Display an admin notice if there is no Obf API key saved and Obf is enabled
	 *
	 * @since  1.0.0
	 * @return string      Admin notice if API key is empty
	 */
	public function obf_admin_notice() {

		// Check if Obf is enabled and if an API key exists
		if ( 'false' === $this->obf_settings['obf_enable'] || !empty( $this->obf_settings['obf_client_id'] ) && '__EMPTY__' !== $this->obf_settings['obf_client_id'] )
			return;

		//display the admin notice
		printf( __( '<div class="updated"><p>Note: Open Badge Factory Integration is turned on, but you must first <a href="%s">enter your Open Badge Factory -credentials</a> to allow earned badges to be shared by recipients (or Disable Open Badge Factory Integration to hide this notice).</p></div>', 'badgeos' ), admin_url( 'admin.php?page=badgeos_sub_obf_integration' ) );

	}

	/**
	 * Create or update a badge on Obf
	 *
	 * @since  1.0.0
	 * @param  integer  $badge_id The post ID of our badge
	 * @param  array    $fields   An array of meta fields from our badge post
	 * @return mixed              False on error. The Obf ID for our badge on success
	 */
	function post_obf_badge( $badge_id = 0, $fields = array() ){
                return; // Disabled for now. TODO: Do users need this feature?
		// Set array of parameters for API call
		$body = $this->post_obf_badge_args( $badge_id, $fields );
                
                $obf_badge_id = false;
                if (!empty($fields['obf_badge_id'])) {
                    $obf_badge_id = $fields['obf_badge_id'];
                }

                $results = $this->obf_client->export_badge($body, $obf_badge_id);
                
                if (empty($results)) {
                    $results = false;
                }
                if (!empty($obf_badge_id) && false !== $obf_badge_id) {
                    $results = $obf_badge_id;
                }

		return $results;

	}

	/**
	 * Generate the array for the Obf badge API call
	 *
	 * @since  1.0.0
	 * @param  integer  $badge_id The post of our badge
	 * @param  array    $fields   Our array of fields
	 * @return array              An array of args for our API call
	 */
	function post_obf_badge_args( $badge_id = 0, $fields = array() ) {

		$attachment        = $this->encoded_image( obf_fieldmap_get_field_value( $badge_id, $this->field_image ) );

		$title             = obf_fieldmap_get_field_value( $badge_id, $this->field_title );
		$title             = ( strlen( $title ) > 128 ? ( substr( $title, 0, 124 ) . '...' ) : $title );

		$short_description = obf_fieldmap_get_field_value( $badge_id, $this->field_short_description );
		$short_description = ( strlen( $short_description ) > 128 ? ( substr( $short_description, 0, 124 ) . '...' ) : $short_description );

		$description       = obf_fieldmap_get_field_value( $badge_id, $this->field_description );
		$description       = ( strlen( $description ) > 500 ? ( substr( $description, 0, 496 ) . '...' ) : $description );

		$criteria          = obf_fieldmap_get_field_value( $badge_id, $this->field_criteria );
		$criteria          = ( strlen( $criteria ) > 500 ? ( substr( $criteria, 0, 496 ) . '...' ) : $criteria );

		$is_giveable       = ( 'true' == $fields['obf_is_giveable'] ? true : false );

		$expires_in        = ( is_numeric( $fields['obf_expiration'] ) ? (int) $fields['obf_expiration'] * 86400 : 0 );

		$categories        = ( $fields['obf_categories'] ? implode( ',',  $fields['obf_categories'] ) : '' );

		$badge_builder_meta = get_post_meta( $badge_id, '_obf_badge_meta', true );

		$args = array(
			'image'        => $attachment, // base64 encoded string
			'name'             => $title, // string; limit 128
			'short_description' => $short_description, // string; limit 128
			'description'       => $description, // string; limit 500
			'criteria_html'     => $criteria, // string; limit 500
			'draft'             => !$is_giveable, // boolean
			'expires'           => $expires_in, // int; in seconds
			'categories'        => $categories, // comma separated string of ids
			'packagedData'      => $badge_builder_meta, // JSON object
                        'tags'              => array(),
		);

		// Remove array keys with an empty value TODO: Check if we want to
		// $args = array_diff( $args, array( '' ) );

		return $args;

	}

	/**
	 * Process our category search ajax call
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function obf_category_search() {

		// Sanitize our input
		$search_string = sanitize_text_field( $_REQUEST['search_terms'] );

		// Get the results, and filter categories
                $results = $this->obf_client->get_categories();
                $results = array_filter(
                        $results,
                        function ($v) use ($search_string) {
                            if (empty($search_string) || false !== strpos(strtolower($v), strtolower($search_string))) {
                                return true;
                            }
                            return false; 
                        }
                    );
		// Send back our response
                    
                $markup = '';

                foreach ( $results as $category ) {

                    $markup .= '<label for="' . esc_attr( $category ) . '"><input type="checkbox" name="_badgeos_obf_categories[' . $category . ']" id="'. esc_attr( $category ) . '" value="' . esc_attr( $category ) . '" /> ' . ucwords( $category ) . '</label><br />';

                }
                
		echo json_encode( $markup );
		die();

	}


	/**
	 * Output existing saved Obf categories for our metabox
	 *
	 * @since  1.0.0
	 * @param  array  $categories An array of category names and ids
	 * @return string             A concatenated string of html markup
	 */
	private function obf_existing_category_output( $categories = array() ) {

		// Return if we don't have any categories saved in post meta
		if ( ! is_array( $categories ) )
			return;

		$markup = '';

		foreach ( $categories as $name => $value ) {

			$markup .= '<label for="' . esc_attr( $name ). '"><input type="checkbox" name="_badgeos_obf_categories[' . $name . ']" id="'. esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" checked="checked" disabled="true" /> ' . ucwords( $name ) . '</label><br />';
		}

		return $markup;

	}


	/**
	 * Add a obf_user_enable checkbox to our user profile
	 *
	 * @since  1.0.0
	 * @param  object  $user The WP_User object of the user being edited.
	 * @return void
	 */
	public function obf_profile_setting( $user ) {
	?>

		<tr>
			<th scope="row"><?php _e( 'Badge Sharing', 'badgeos' ); ?></th>
			<td><label for="obf_user_enable"><input type="checkbox" name="obf_user_enable" value="true" <?php checked( $user->obf_user_enable, 'true' ); ?>/> <?php _e( 'Send eligible earned badges to Open Badge Factory', 'badgeos' ); ?></td>
		</tr>

	<?php
	}


	/**
	 * Process our obf_user_enable user meta setting
	 *
	 * @since  1.0.0
	 * @param  int  $user_id The user ID of the user being edited
	 * @return void
	 */
	public function obf_profile_setting_save( $user_id = 0 ) {

		if ( !current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		$obf_enable = ( ! empty( $_POST['obf_user_enable'] ) && $_POST['obf_user_enable'] == 'true' ? 'true' : 'false' );

		$this->obf_profile_setting_update_meta( $user_id, $obf_enable );

	}


	/**
	 * Wrapper to update our user meta value
	 *
	 * @since  1.0.0
	 * @param  string  $user_id The user ID we're updating
	 * @param  string  $value   true if enabling, false if disabling
	 * @return mixed            User meta id on success, false on failure
	 */
	private function obf_profile_setting_update_meta( $user_id = '', $value = 'true' ) {

		// Check if we have a numeric user id, if not get current user
		$user_id = ( is_numeric( $user_id ) ? $user_id : $this->user_id );

		$updated = update_user_meta( $user_id, 'obf_user_enable', $value );

		return $updated;

	}


	/**
	 * Enable Obf for the current if they don't have a true/false value set in their user meta
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function obf_profile_setting_force_enable() {

		$enabled = get_user_meta( $this->user_id, 'obf_user_enable', true );

		if ( empty( $enabled ) )
			$this->obf_profile_setting_update_meta();

	}

        /**
         * Get our existing local copies of OBF badges
         * 
         * @since 1.4.7.2
         * @return stdClass[] Array of existing badges
         */
        public function obf_get_existing_badges() {
            global $wpdb;
            $obf_achievement_types = $this->obf_badge_achievement_types(false);
            // Get all badge posts with obf badge id, excluding those which have an achievement type that has been deleted.
            $sql = "SELECT post_id, pm.meta_value AS badge_id, post_modified_gmt AS modified_date FROM {$wpdb->postmeta} pm "
                    . "LEFT JOIN {$wpdb->posts} p ON (pm.post_id = p.id) WHERE p.post_type IN ("
                    . implode(', ', array_fill(0, count($obf_achievement_types), '%s'))
                    . ") AND p.post_status != 'trash' AND pm.meta_key = '_badgeos_obf_badge_id'"
                    // Badges, where the subselect below does not return anything, belong to an achievement type that has been deleted.
                    . "AND EXISTS (SELECT id FROM {$wpdb->postmeta} subpm  LEFT JOIN {$wpdb->posts} subp ON (subpm.post_id = subp.id) "
                    . "WHERE subp.post_type='achievement-type' AND subp.post_status NOT IN ('trashed', 'closed', 'trash') "
                    . "AND subp.post_name=p.post_type AND subpm.meta_key='_badgeos_singular_name')";
            $query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $obf_achievement_types));
            $existing = $wpdb->get_results($query, OBJECT);
            return $existing;
        }
        /**
        * Creates array where badges obf id is key and wordpress post id is value.
        *
        * @since 1.4.7.2
        * @return array An array of existing badges where key[obf_id]=value[wp_post_id];
        */
        public function obf_get_existing_badges_id_map() {
            $existing_badges = $this->obf_get_existing_badges();
            $post_ids = array();
            foreach($existing_badges as $badge) {
                $post_ids[$badge->badge_id] = $badge->post_id;
            }
            return $post_ids;
        }
	/**
	 * Gets our Obf user ID or defaults to user email
	 *
	 * @since  1.0.0
	 * @param  int     User ID we're checking
	 * @return string  A numeric user ID from obf or the user email
	 */
	public function obf_get_user_id( $user_id = 0 ) {

		$user_id = ( ! empty( $user_id ) ? $user_id : $this->user_id );

		$user = get_userdata( $user_id );

		// If we're saving our profile use that value, otherwise the current user email
		$user_email = ( ! empty( $_POST['email'] ) ? $_POST['email'] : $user->user_email );

		// Note: Users don't really have an id, unless we want to add OBP-support
		$obf_id = $this->obf_user_email_search( $user_email );

		// If we didn't return a numeric id, set it to the user email
		if ( ! is_numeric( $obf_id ) && !is_email($obf_id) )
			$obf_id = $user_email;

		// Set our local meta
		$this->obf_user_set_id( $user_id, $obf_id );

		return $obf_id;

	}
        
        public function get_badge_from_cache($badge_id) {
            if (!array_key_exists($badge_id, self::$badge_cache)) {
                self::$badge_cache[$badge_id] = $this->obf_client->get_badge($badge_id);
            }
            return self::$badge_cache[$badge_id];
        }


	/**
	 * Searches the obf API for a user email. (Not really, return email)
	 *
         * @todo Search OBP API for user email?
	 * @since  1.0.0
	 * @param  string  $email Email address for the current user
	 * @return mixed          Numeric user ID on success, false on failure
	 */
	private function obf_user_email_search( $email = '' ) {
		return $email;
	}

	/**
	 * Helper function to set Obf user ID
	 *
	 * @since  1.0.0
	 * @param  string  $id The user ID value we're setting
	 * @return void
	 */
	private function obf_user_set_id( $id = '' ) {

		if ( ! empty( $id ) )
			update_user_meta( $this->user_id, 'obf_user_id', $id );

	}

	/**
	 * returns email template for obf badge issuing what has been saved to settings
	 *
	 * @since  1.4.7.6
	 * @return $emailTemplate object
	 */
	private function obf_get_email_template($badge_id) {
		$badgeos_settings = badgeos_obf_get_settings();
                
                $emailTemplate = new stdClass();
		$emailTemplate->email_subject = isset($badgeos_settings["email_subject"]) ? $badgeos_settings["email_subject"] : '';
		$emailTemplate->email_body = isset($badgeos_settings["email_body"]) ? $badgeos_settings["email_body"] : '';
		$emailTemplate->email_link_text = isset($badgeos_settings["email_link_text"]) ? $badgeos_settings["email_link_text"] : '';
		$emailTemplate->email_footer = isset($badgeos_settings["email_footer"]) ? $badgeos_settings["email_footer"] : '';
                if (empty($emailTemplate->email_subject)) {
                    try {
                        $badge = $this->get_badge_from_cache($badge_id);
                        if (is_array($badge) && !empty($badge['email_subject'])) {
                            $emailTemplate->email_subject   = $badge['email_subject'];
                            $emailTemplate->email_body      = $badge['email_body'];
                            $emailTemplate->email_link_text = $badge['email_link_text'];
                            $emailTemplate->email_footer    = $badge['email_footer'];
                        }
                    } catch (Exception $ex) {
                    }
                }
                if (empty($emailTemplate->email_subject)) {
                    $emailTemplate->email_subject = __('You earned a badge', 'badgeos');
                }
                
		

		return $emailTemplate;
	}


	/**
	 * Post a users earned badge to Obf, when user has automatically earned the badge.
	 *
	 * @since  1.0.0
	 * @param  int  $user_id  The given users ID
	 * @param  int  $badge_id The badge ID the user is earning
	 * @return string         Results of the API call
	 */
	public function post_obf_user_badge( $user_id = 0, $badge_id = 0 ) {
            
		// Bail if the badge isn't in Obf
		if ( ! obf_is_achievement_giveable( $badge_id, $user_id ) )
			return false;

		if ( empty( $user_id ) )
			$user_id = $this->user_id;

		// Generate our API URL endpoint
		//$url = $this->api_url_user_badge();

		// Generate our args
		$body = $this->post_user_badge_args( $user_id, $badge_id );

		// POST our data to the Obf API and get our response (which should be event id on success)
                try {
		    $emailTemplate = $this->obf_get_email_template($body['badge_id']);

                    $results = $this->obf_client->issue_badge( $body, $body['recipient'], null, $emailTemplate );
                } catch (Exception $ex) {
                    return new WP_Error($ex->getCode(), $ex->getMessage());
                }
		

		// If post was successful, trigger other actions
		if ( $results ) {
			do_action( 'post_obf_user_badge', $user_id, $badge_id, true );
		}

		return $results;
                
	}


	/**
	 * Generate the array for user badge API call
	 *
	 * @since  1.0.0
	 * @param  int  $user_id  The ID of the user earning a badge
	 * @param  int  $badge_id The badge ID the user is earning
	 * @return array          An array of args
	 */
	private function post_user_badge_args( $user_id = 0, $badge_id = 0 ) {

		$args = '';

		$user_id = ( ! empty( $user_id ) ? $user_id : $this->user_id );

		$obf_user_id = $this->obf_get_user_id( $user_id );

		$obf_badge_id = get_post_meta( $badge_id, '_badgeos_obf_badge_id', true );

		//$testimonial = obf_fieldmap_get_field_value( $badge_id, $this->field_testimonial );
		//$testimonial = ( strlen( $testimonial ) > 1000 ? ( substr( $testimonial, 0, 996 ) . '...' ) : $testimonial );

                $expires_in_days = ( get_post_meta( $badge_id, '_badgeos_obf_expiration', true ) ? get_post_meta( $badge_id, '_badgeos_obf_expiration', true ) : '0' );
                $expires = 0;
                if (is_numeric($expires_in_days) && $expires_in_days != 0) {
                    $expires = time() + (((int)$expires_in_days) * 86400);
                }
                
                
                $args = array(
                    'recipient'     => array($obf_user_id),
                    'badge_id'      => $obf_badge_id,
                    'expires'       => $expires
                );

		// Remove array keys with an empty value TODO: Do we want this?
		//$args = array_diff( $args, array( '' ) );

		return $args;

	}
        
        
        /**
	 * Issue badges to multiple users.
	 *
	 * @since  1.4.6
	 * @param  int  $user_ids  The given users IDs
         * @param  string[]  $emails    Array of emails to issue to
	 * @param  int  $badge_id The badge ID the user is earning
	 * @return string         Results of the API call
	 */
	public function post_obf_user_badges( $user_ids = array(), $emails = array(), $badge_id = 0, $force = false ) {
            
                $is_sendable = obf_is_achievement_giveable($badge_id);
                if (!$is_sendable) {
                    return false;
                }
                $ok_user_ids = array();
                foreach($user_ids as $user_id) {
                    // Bail if the badge isn't in Obf
                    if ( ! $force && ! obf_is_achievement_giveable( $badge_id, $user_id ) ) {
                        continue;
                    }
                    $user_email = $this->obf_get_user_id( $user_id );
                    if (!is_email($user_email)) {
                        $user = get_userdata($user_id);
                        return new WP_Error('invalid', sprintf(__('User %1$s email is invalid ("%2$s")!', 'badgeos'), $user->user_login, $user_email));
                    }
                    $emails[] = $user_email;
                    $ok_user_ids[] = $user_id;

                }
                $emails = array_map('strtolower', $emails);
                $emails = array_unique($emails);
                
                $valid_emails = array_filter($emails, 'is_email');
                if (count($valid_emails) < count($emails)) {
                    $invalid_emails = array_diff($emails, $valid_emails);
                    return new WP_Error('invalid', sprintf(__('Detected %1$d invalid emails (%2$s).', 'badgeos'), count($invalid_emails), implode(', ', $invalid_emails)));
                }

                if (count($emails) == 0) {
                    return false;
                }
		// Generate our args
		$body = $this->post_user_badges_args( $emails, $badge_id );

		// POST our data to the Obf API and get our response (which should be event id on success)
		$emailTemplate = $this->obf_get_email_template($body['badge_id']);

		$results = $this->obf_client->issue_badge( $body, $body['recipient'], null, $emailTemplate );

		// If post was successful, trigger other actions
		if ( $results && !is_wp_error($results)) {
                    foreach($ok_user_ids as $user_id) {
                        do_action( 'post_obf_user_badge', $user_id, $badge_id, true );
                    }
		}

		return $results;
                
	}
        
        /**
	 * Generate the array for multi-user badge API call
	 *
	 * @since  1.4.6
         * @param  string[] $emails  The IDs of the users earning a badge
	 * @param  int  $badge_id The local badge ID the user is earning
	 * @return array          An array of args
	 */
	private function post_user_badges_args( $emails = array(), $badge_id = 0 ) {
            $obf_badge_id = get_post_meta( $badge_id, '_badgeos_obf_badge_id', true );
            $expires_in_days = ( get_post_meta( $badge_id, '_badgeos_obf_expiration', true ) ? get_post_meta( $badge_id, '_badgeos_obf_expiration', true ) : '0' );
            $expires = 0;
            if (is_numeric($expires_in_days) && $expires_in_days != 0) {
                $expires = time() + (((int)$expires_in_days) * 86400);
            }
            $args = array(
                'recipient'     => $emails,
		'badge_id'      => $obf_badge_id,
                'expires'       => $expires
            );
            return $args;
        }
        
        public function maybe_sync_obf_assertions()
        {
            $obf_settings = (array) get_option( 'obf_settings', array() );
            $sync_option_name = 'obf_assertion_sync';
            //$sync_delay = 60 * 60;
            if (
                    !array_key_exists($sync_option_name, $obf_settings) ||
                    'true' == $obf_settings[$sync_option_name]
            ) {
                $this->sync_obf_assertions();
            }
        }
        /**
         * Sync OBF assertions with the local users
         * 
         * @param \WP_User[]|\WP_User|null $users Array of WP_Users to sync
         * @since 1.4.7.2
         * @return \WP_Error|int Number of assertions which were matched to local users or WP_Error on failure
         */
        public function sync_obf_assertions($users = null)
        {
            if (!$this->obf_client_id_exists()) {
                return;
            }
            if ($users instanceof \WP_User) {
                $users = array($users);
            }
            $email = !empty($users) && count($users) == 1 ? $users[0]->user_email : null;
            try {
                $assertions = $this->obf_client->get_assertions(null, $email, array('order_by' => 'desc'));
            } catch (Exception $ex) {
                return new WP_Error($ex->getCode(), $ex->getMessage());
            }
            $count = 0;
            
            $badges_recipients = array();
            $obf_id_post_id_map = $this->obf_get_existing_badges_id_map();
            if (0 == count($obf_id_post_id_map)) {
                return 0;
            }
            
            if (empty($users)) {
                $users = get_users(array('fields' => array('ID','user_email')));
            }

            // Create an array of addresses => ids (user@example.com => 1) 
            $user_email_user_ids = array();
            foreach ($users as $user) {
                if (!empty($user->user_email)) {
                    $user_email_user_ids[strtolower($user->user_email)] = $user->ID;
                }
            }
            
            $issued_on_timestamps = array();
            foreach ($assertions as $assertion) {
              $obf_badge_id = $assertion['badge_id'];
              $wp_badge_id = array_key_exists($obf_badge_id, $obf_id_post_id_map) ? $obf_id_post_id_map[$obf_badge_id] : null;
              $badge_recipient_ids = array();
              if (!empty($assertion['recipient']) && is_array($assertion['recipient'])) {
                  foreach($assertion['recipient'] as $recipient) {
                      if (array_key_exists(strtolower($recipient), $user_email_user_ids) ) {
                          $badge_recipient_ids[] = $user_email_user_ids[$recipient];
                          if (isset($assertion['issued_on'])) {
                            $issued_on_timestamps[$wp_badge_id][$user_email_user_ids[strtolower($recipient)]] = $assertion['issued_on'];
                          }
                      }
                  }
              }
              if (!empty($badge_recipient_ids) && !is_null($wp_badge_id)) {
                if (array_key_exists($wp_badge_id, $badges_recipients)) {
                   $badge_recipient_ids = array_unique(array_merge($badges_recipients[$wp_badge_id], $badge_recipient_ids));
                }
                $badges_recipients[$wp_badge_id] = $badge_recipient_ids;
              }
            }
            
            
            foreach($badges_recipients as $wp_badge_id => $badge_recipient_ids) {
                foreach($badge_recipient_ids as $recipient_id) {
                        $issued_on = (isset($issued_on_timestamps[$wp_badge_id][$recipient_id])) ? $issued_on_timestamps[$wp_badge_id][$recipient_id] : null;
                        badgeos_user_sent_achievement_to_obf($recipient_id, $wp_badge_id, true, $issued_on);
                        $count++;
                }
            }
            return $count;
        }
        
	/**
	 * Encode our image file so we can pass it to the Obf API
	 *
	 * @since  1.0.0
	 * @param  string  $image_id The ID of our image attachment
	 * @return string            base64 encoded image file
	 */
	private function encoded_image( $image_id = '' ) {

		// If we don't have a valid image ID, bail here
		if ( ! is_numeric( $image_id ) )
			return null;

		$image_file = get_attached_file( $image_id );

		// If we don't have a valid file, bail here
		if ( ! file_exists( $image_file ) )
			return null;

		// Open and encode our image file
		$handle        = fopen( $image_file, 'r' );
		$image_binary  = fread( $handle, filesize( $image_file ) );
		$encoded_image = base64_encode( $image_binary );

		// Return the encoded file
		return $encoded_image;

	}


	/**
	 * Add a Obf Badge Settings metabox on the badge CPT
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function badge_metabox_add() {

		foreach ( $this->obf_badge_achievement_types(false) as $achievement_type ) {

			add_meta_box( 'badgeos_obf_details_meta_box', __( 'Badge Sharing Options', 'badgeos' ), array( $this, 'badge_metabox_show' ), $achievement_type, 'advanced', 'default' );
                        remove_meta_box( 'postimagediv', $achievement_type, 'side' ); // Remove featured image metabox, as it cannot be changed.
                        add_meta_box( 'badgeos_obf_image_meta_box', __( 'Badge Image', 'badgeos' ), array( $this, 'badge_image_metabox_show' ), $achievement_type, 'side', 'default' );
		}
	}
        

	/**
	 * Output a Obf Badge Settings metabox on the badge CPT
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function badge_metabox_show() {

		global $post;

		//Check existing post meta
		$send_to_obf             = ( get_post_meta( $post->ID, '_badgeos_send_to_obf', true ) ? get_post_meta( $post->ID, '_badgeos_send_to_obf', true ) : 'false' );
		//$obf_include_evidence    = ( get_post_meta( $post->ID, '_badgeos_obf_include_evidence', true ) ? get_post_meta( $post->ID, '_badgeos_obf_include_evidence', true ): 'false' );
		//$obf_include_testimonial = ( get_post_meta( $post->ID, '_badgeos_obf_include_testimonial', true ) ? get_post_meta( $post->ID, '_badgeos_obf_include_testimonial', true ) : 'false' );
                $obf_include_evidence    = 'false';
                $obf_include_testimonial = 'false'; // No support for testimonials at the moment.
		$obf_expiration          = ( get_post_meta( $post->ID, '_badgeos_obf_expiration', true ) ? get_post_meta( $post->ID, '_badgeos_obf_expiration', true ) : '0' );
		//$obf_is_giveable         = ( get_post_meta( $post->ID, '_badgeos_obf_is_giveable', true ) ? get_post_meta( $post->ID, '_badgeos_obf_is_giveable', true ) : 'false' );
                $obf_is_giveable         = 'true'; // Do not process drafts
                $obf_editing_disabled    = ( get_post_meta( $post->ID, '_badgeos_obf_editing_disabled', true ) ? get_post_meta( $post->ID, '_badgeos_obf_editing_disabled', true ) : 'false' );
		$obf_categories          = maybe_unserialize( get_post_meta( $post->ID, '_badgeos_obf_categories', true ) );
		$obf_badge_id            = get_post_meta( $post->ID, '_badgeos_obf_badge_id', true );

	?>
		<input type="hidden" name="obf_details_nonce" value="<?php echo wp_create_nonce( 'obf_details' ); ?>" />
		<table class="form-table">
			<tr valign="top">
				<td colspan="2"><?php _e( "This setting makes the earned badge for this achievement sharable via Open Badge Factory and Open Badge Passport on social networks, such as Facebook, Twitter, LinkedIn, Mozilla Backpack, or the badge earner's own blog or site.", 'badgeos' ); ?> (<?php printf( __( '<a href="%s">Configure global settings</a> for Open Badge Factory integration.', 'badgeos' ), admin_url( 'admin.php?page=badgeos_sub_obf_integration' ) ); ?> )</td>
			</tr>
			<tr valign="top"><th scope="row"><label for="_badgeos_send_to_obf"><?php _e( 'Send to Open Badge Factory when earned', 'badgeos' ); ?></label></th>
				<td>
					<select id="_badgeos_send_to_obf" name="_badgeos_send_to_obf">
						<option value="1" <?php selected( $send_to_obf, 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
						<option value="0" <?php selected( $send_to_obf, 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
					</select>
                                    <span class="description cmb_metabox_description"><?php _e('Open Badge Factory will automatically email the badge to the user.', 'badgeos'); ?></span>
				</td>
			</tr>
		</table>

		<div id="obf-badge-settings">
                    <table class="form-table">
                        <tr valign="top" style="display: none;"><th scope="row"><label for="_badgeos_obf_editing_disabled"><?php _e( 'Editing Disabled', 'badgeos' ); ?></label></th>
                            <td>
                                <select id="_badgeos_obf_editing_disabled" name="_badgeos_obf_editing_disabled">
                                    <option value="1" <?php selected( $obf_editing_disabled, 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
                                    <option value="0" <?php selected( $obf_editing_disabled, 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
                                </select>
                            </td>
			</tr>
			<tr valign="top" style="display: none;"><th scope="row"><label for="_badgeos_obf_include_evidence"><?php _e( 'Include Evidence', 'badgeos' ); ?></label></th>
				<td>
					<select id="_badgeos_obf_include_evidence" name="_badgeos_obf_include_evidence">
						<option value="1" <?php selected( $obf_include_evidence, 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
						<option value="0" <?php selected( $obf_include_evidence, 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
					</select>
				</td>
			</tr>
			<tr valign="top" style="display: none;"><th scope="row"><label for="_badgeos_obf_include_testimonial"><?php _e( 'Include Testimonial', 'badgeos' ); ?></label></th>
				<td>
					<select id="_badgeos_obf_include_testimonial" name="_badgeos_obf_include_testimonial">
						<option value="1" <?php selected( $obf_include_testimonial, 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
						<option value="0" <?php selected( $obf_include_testimonial, 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
					</select>
				</td>
			</tr>
			<tr valign="top"><th scope="row"><label for="_badgeos_obf_expiration"><?php _e( 'Expiration', 'badgeos' ); ?></label></th>
				<td>
					<input type="text" id="_badgeos_obf_expiration" name="_badgeos_obf_expiration" value="<?php echo $obf_expiration; ?>" class="widefat" />
                                        <span class="description cmb_metabox_description"><?php _e('Enter number of days, and 0 if badge never expires.', 'badgeos'); ?></span>
				</td>
			</tr>
			<tr valign="top" style="display: none;"><th scope="row"><label for="_badgeos_obf_is_giveable"><?php _e( 'Allow Badge to be Given by Others', 'badgeos' ); ?></label></th>
				<td>
					<select id="_badgeos_obf_is_giveable" name="_badgeos_obf_is_giveable">
						<option value="1" <?php selected( $obf_is_giveable, 'true' ); ?>><?php _e( 'Yes', 'badgeos' ) ?></option>
						<option value="0" <?php selected( $obf_is_giveable, 'false' ); ?>><?php _e( 'No', 'badgeos' ) ?></option>
					</select>
                                        <span class="description"><?php _e('Is the badge issuable (Yes), or a draft (No).', 'badgeos'); ?></span>
				</td>
			</tr>
			<tr valign="top" class="obf_category_search" style="display: none;"><th scope="row"><label for="obf_category_search"><?php _e( 'Open Badge Factory Category Search', 'badgeos' ); ?></label></th>
				<td>
					<input type="text" id="obf_category_search" name="obf_category_search" value="" size="50" />
					<a id="obf_category_search_submit" class="button" /><?php _e( 'Search Categories', 'badgeos' ); ?></a>
				</td>
			</tr>
			<tr valign="top" id="obf_search_results" <?php if ( ! is_array( $obf_categories ) ) { ?>style="display:none"<?php } ?>><th scope="row"><label><?php _e( 'Open Badge Factory Badge Category', 'badgeos' ); ?></label></th>
				<td>
					<fieldset>
						<?php echo $this->obf_existing_category_output( $obf_categories ); ?>
					</fieldset>
				</td>
			</tr>
			<tr valign="top"><th scope="row"><label for="_badgeos_obf_badge_id"><?php _e( 'Open Badge Factory Badge ID', 'badgeos' ); ?></label></th>
				<td>
					<input type="text" id="_badgeos_obf_badge_id" name="_badgeos_obf_badge_id" value="<?php echo esc_attr( $obf_badge_id ); ?>" class="widefat" readonly="readonly" />
				</td>
			</tr>
			</table>
		</div>

	<?php
	}

        /**
	 * Output a OBF Badge image metabox on the badge CPT
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function badge_image_metabox_show() {

		global $post;
                the_post_thumbnail( 'thumbnail' );
        }

	/**
	 * Save our Obf Badge Settings metabox
	 *
	 * @since  1.0.0
	 * @param  int     $post_id The ID of the given post
	 * @return int     Return the post ID of the post we're running on
	 */
	public function badge_metabox_save( $post_id = 0 ) {

		// Verify nonce
		if ( ! isset( $_POST['obf_details_nonce'] ) || ! wp_verify_nonce( $_POST['obf_details_nonce'], 'obf_details' ) )
			return $post_id;

		// Make sure we're not doing an autosave
		if ( defined('DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		// Make sure this isn't a post revision
		if ( wp_is_post_revision( $post_id ) )
			return $post_id;

		// Check user permissions
		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		// Sanitize our fields
		$fields = $this->badge_metabox_sanitize_fields();

		// If enabled and we have a obf badge ID lets update
		if ( 'true' == $fields['send_to_obf'] )
			$obf_badge = $this->post_obf_badge( $post_id, $fields );

		// Save our meta
		$meta = $this->badge_metabox_save_meta( $post_id, $fields );

		// Update our meta value with our returned Obf badge ID
		if ( isset( $obf_badge ) )
			update_post_meta( $post_id, '_badgeos_obf_badge_id', $obf_badge );

		return $post_id;

	}
        
        public function obf_badge_achievement_types($plural = true)
        {
            $types = array();
            $achievement_types = get_posts( array(
		'post_type'      =>	'achievement-type',
		'posts_per_page' =>	-1,
            ) );

            if (empty($this->achievement_types_plural)) {
                foreach($achievement_types as $achievement_type)
                {
                    $use_obf = get_post_meta( $achievement_type->ID, '_badgeos_use_obf_badges', true );
                    if ($use_obf) {
                            $achievement_name_plural = get_post_meta( $achievement_type->ID, '_badgeos_plural_name', true );
                            $achievement_name_singular = get_post_meta( $achievement_type->ID, '_badgeos_singular_name', true );
                            $this->achievement_types_plural[] = sanitize_title(strtolower($achievement_name_plural));
                            $this->achievement_types_singular[] = sanitize_title(strtolower($achievement_name_singular));
                            $types[] = $plural ? $achievement_name_plural : $achievement_name_singular;
                    }
                }   
            }
            
            return $plural ? $this->achievement_types_plural : $this->achievement_types_singular;
        }


	/**
	 * Sanitize our metabox fields
	 *
	 * @since  1.0.0
	 * @return array  An array of sanitized fields from our metabox
	 */
	private function badge_metabox_sanitize_fields() {

		$fields = array();

		// Sanitize our input fields
		$fields['send_to_obf']             = ( $_POST['_badgeos_send_to_obf'] ? 'true' : 'false' );
		$fields['obf_include_evidence']    = ( $_POST['_badgeos_obf_include_evidence'] ? 'true' : 'false' );
		$fields['obf_include_testimonial'] = ( $_POST['_badgeos_obf_include_testimonial'] ? 'true' : 'false' );
		$fields['obf_expiration']          = ( $_POST['_badgeos_obf_expiration'] ? sanitize_text_field( $_POST['_badgeos_obf_expiration'] ) : '0' );
		$fields['obf_is_giveable']         = ( $_POST['_badgeos_obf_is_giveable'] ? 'true' : 'false' );
                $fields['obf_editing_disabled']    = ( $_POST['_badgeos_obf_editing_disabled'] ? 'true' : 'false' );
		$fields['obf_categories']          = ( ! empty ( $_POST['_badgeos_obf_categories'] ) ? array_map( 'sanitize_text_field', $_POST['_badgeos_obf_categories'] ) : '' );
		$fields['obf_badge_id']            = ( $_POST['_badgeos_obf_badge_id'] ? sanitize_text_field( $_POST['_badgeos_obf_badge_id'] ) : '' );

		return $fields;

	}


	/**
	 * Save the meta fields from our metabox
	 *
	 * @since  1.0.0
	 * @param  int  $post_id   Post ID
	 * @param  array  $fields  An array of fields in the metabox
	 * @return bool            Return true
	 */
	private function badge_metabox_save_meta( $post_id = 0, $fields = array() ) {

		update_post_meta( $post_id, '_badgeos_send_to_obf', $fields['send_to_obf'] );
		update_post_meta( $post_id, '_badgeos_obf_include_evidence', $fields['obf_include_evidence'] );
		update_post_meta( $post_id, '_badgeos_obf_include_testimonial', $fields['obf_include_testimonial'] );
		update_post_meta( $post_id, '_badgeos_obf_expiration', $fields['obf_expiration'] );
		update_post_meta( $post_id, '_badgeos_obf_is_giveable', $fields['obf_is_giveable'] );
                update_post_meta( $post_id, '_badgeos_obf_editing_disabled', $fields['obf_editing_disabled'] );
		update_post_meta( $post_id, '_badgeos_obf_categories', $fields['obf_categories'] );

		return true;

	}

} // End BadgeOS_Obf class

/**
 * Generate the available fields to map to
 *
 * @since  1.0.0
 * @return array        An array of fields available for achievement types
 */
function obf_fieldmap_get_fields() {

	$fields = array();

	// Set our default fields
	$fields[] = 'post_title';
	$fields[] = 'post_body';
	$fields[] = 'post_excerpt';
	$fields[] = 'featured_image';
	$fields[] = 'permalink';
	$fields[] = 'congratulations_text';

	$achievement_types = badgeos_get_achievement_types_slugs();

	if ( !empty( $achievement_types ) ) {
		$achievement_types_format = implode( ', ', array_fill( 0, count( $achievement_types ), '%s' ) );

		// Get all unique meta keys from the postmeta table
		global $wpdb;

		$meta_keys = $wpdb->get_col(
			$wpdb->prepare(
				"
					SELECT DISTINCT `pm`.`meta_key`
					FROM `{$wpdb->postmeta}` AS `pm`
					LEFT JOIN `{$wpdb->posts}` AS `p` ON `p`.`ID` = `pm`.`post_id`
					WHERE
						`p`.`post_type` IN ( {$achievement_types_format} )
						AND `pm`.`meta_key` NOT LIKE '_%%'
						AND `pm`.`meta_key` != ''
				",
				$achievement_types
			)
		);

		// Merge our default fields with unique meta keys
		$fields = array_merge( $fields, $meta_keys );

		// Avoid possible duplicates from meta_keys that match our default $fields
		$fields = array_unique( $fields );
	}

	$fields = apply_filters( 'badgeos_obf_field_map', $fields );

	return $fields;

}

/**
 * Generate a string of html option values based on our fields
 *
 * @since  1.0.0
 * @return string        A string of <options>
 */
function obf_fieldmap_list_options( $value = '' ) {

	$fields = obf_fieldmap_get_fields();

	// Start off our <option>s with a default blank
	$options = '<option value="">' . __( '&mdash; Select Field &mdash;', 'badgeos' ) . '</option>';

	// Create the correct <option> markup for our fields
	foreach ( $fields as $field ) {

		$options .= '<option value="' . esc_attr( $field ) . '" ' . selected( $field, $value ) . '>' . esc_html( $field ) . '</option>';

	}

	return $options;

}

/**
 * Map our fieldmap values to actual content.
 *
 * In the case it's one of our defined defaults, get the relevant post content.
 * For everything else, assume it's meta and attempt to get a meta key with
 * matching field name.
 *
 * @since  1.0.0
 * @param  int|string $post_id The ID of the badge post we're mapping fields for
 * @param  string $field   The field name we're attempting to map
 * @return mixed           string for most values. int for featured_image
 */
function obf_fieldmap_get_field_value( $post_id, $field = '' ) {

	switch ( $field ) {
		case 'post_title':
			$value = get_the_title( $post_id );

			break;

		case 'post_body':
			$value = get_post_field( 'post_content', $post_id );

			break;

		case 'post_excerpt':
			$value = get_post_field( 'post_excerpt', $post_id );

			break;

		case 'featured_image':
			$value = get_post_thumbnail_id( $post_id );
			if ( ! $value ) {
				$parent_achievement = get_page_by_path( get_post_type( $post_id ), OBJECT, 'achievement-type' );
				$value = get_post_thumbnail_id( $parent_achievement->ID );
			}

			break;

		case 'permalink':
			$value = get_permalink( $post_id );

			break;

		case 'congratulations_text':
			$value = get_post_meta( $post_id, '_badgeos_congratulations_text', true );

			break;

		case '':
			$value = '';

			break;

		default:
			$value = get_post_meta( $post_id, $field, true );

			break;
	}

	return $value;

}

/**
 * Check if an achievement is giveable in Obf
 *
 * @since  1.0.0
 * @param  integer $achievement_id The achievement ID we're checking
 * @return bool                    True if giveable, false if not
 */
function obf_is_achievement_giveable( $achievement_id = 0, $user_id = 0 ) {

	// Check if "send to obf" is enabled
	$is_sendable = get_post_meta( $achievement_id, '_badgeos_send_to_obf', true );

	// Get Obf badge ID
	$obf_badge_id = get_post_meta( $achievement_id, '_badgeos_obf_badge_id', true );

	// If send to obf is ON, and badge ID is set, badge is givable
	if ( 'true' == $is_sendable && ! empty( $obf_badge_id ) ){
		$is_giveable = true;
	} else {
		$is_giveable = false;
	}

	// If achievement is giveable, check if user is allowed to send to obf
	if ( $is_giveable && !empty($user_id) ) {
		$is_giveable = badgeos_can_user_send_achievement_to_obf( $user_id, $achievement_id );
	}

	// Return givable status
	return apply_filters( 'obf_is_achievement_giveable', $is_giveable, $achievement_id, $user_id );

}

/**
 * Get the stored Obf API key
 *
 * @since  1.3.0
 *
 * @return string|bool Stored API key on success, otherwise false.
 */
function obf_get_api_key() {

	/**
	 * @var $badgeos_obf BadgeOS_Obf
	 */
	global $badgeos_obf;

	$obf_settings = $badgeos_obf->obf_settings;

	// If we have no settings, no key, or obf is not enabled, return false
	if ( empty( $obf_settings['api_key'] ) || 'false' === $obf_settings['obf_enable'] )
		return false;

	// Otherwise, return our stored key
	return $obf_settings['api_key'];

}

/**
 * Check if an earned acheivement instance has been sent to obf
 *
 * @since  1.3.4
 *
 * @param  object $earned_achievement_instance BadgeOS Achievement object.
 * @return bool                                True if achievement has been sent to Obf, otherwise false.
 */
function badgeos_achievement_has_been_sent_to_obf( $earned_achievement_instance = null ) {

	// If instance has been sent to obf, return true
	if ( isset( $earned_achievement_instance->sent_to_obf ) ) {
		return true;
	}

	// Otherwise, return false
	return false;
}

/**
 * Check if user is elligble to send an achievement to Obf.
 *
 * @since  1.3.4
 *
 * @param  integer $user_id        User ID.
 * @param  integer $achievement_id Achievement post ID.
 * @return bool                    True if achievement can be sent, otherwise false.
 */
function badgeos_can_user_send_achievement_to_obf( $user_id = 0, $achievement_id = 0 ) {

	// If passed ID is not an achievement, bail here
	if ( ! badgeos_is_achievement( $achievement_id ) )
		return false;

	// If no user was specified, get the current user
	if ( ! $user_id )
		$user_id = get_current_user_id();

	// Get all earned instances of this achievement
	$earned_achievements = badgeos_get_user_achievements( array( 'user_id' => $user_id, 'achievement_id' => $achievement_id ) );

	// Loop through each earned instance
	if ( ! empty( $earned_achievements ) ) {
		foreach ( $earned_achievements as $key => $achievement ) {
			// If this instance has not been sent to obf, it may be sent
			if ( ! badgeos_achievement_has_been_sent_to_obf( $achievement ) ) {
				return true;
			}
		}
	}

	// No earned instances were eligable
	return false;
}

/**
 * Update user's earned achievements to reflect a specific acheivement has been sent to Obf.
 *
 * @since  1.3.4
 *
 * @param  integer $user_id        User ID.
 * @param  integer $achievement_id Achievement post ID.=
 * @param  integer|null $timestamp Timestamp override, when the achievement was earned
 * @return mixed                   Updated user meta ID on success, otherwise false.
 */
function badgeos_user_sent_achievement_to_obf( $user_id, $achievement_id, $add = false, $timestamp = null ) {

	// Get all earned achievements
	$earned_achievements = badgeos_get_user_achievements( array( 'user_id' => $user_id ) );

        $existing = false;
	// Loop through each achievement
	if ( ! empty( $earned_achievements ) ) {
		foreach ( $earned_achievements as $key => $achievement ) {

			// If acheivement doesn't match our ID, skip it
			if ( $achievement_id !== $achievement->ID )
				continue;
                        
                        $existing = true;

			// If this instance has not been sent to obf, mark it as sent and exit
			if ( ! badgeos_achievement_has_been_sent_to_obf( $achievement ) ) {
				$earned_achievements[ $key ]->sent_to_obf = true;
				return badgeos_update_user_achievements( array( 'user_id' => $user_id, 'all_achievements' => $earned_achievements ) );
			}
		}
	}
        if (true == $add && false == $existing) {
            // Setup our achievement object
            $achievement_object = badgeos_build_achievement_object( $achievement_id );
            $achievement_object->sent_to_obf = true;
            if (!empty($timestamp) && is_numeric($timestamp)) {
                $achievement_object->date_earned = $timestamp;
            }

            // Update user's earned achievements
            badgeos_update_user_achievements( array( 'user_id' => $user_id, 'new_achievements' => array( $achievement_object ) ) );
        }

	return false;
}
add_action( 'post_obf_user_badge', 'badgeos_user_sent_achievement_to_obf', 10, 4 );

/**
 * Create a log entry for an achievement being sent to Obf.
 *
 * @since  1.3.4
 *
 * @param  integer $user_id        User ID.
 * @param  integer $achievement_id Achievement post ID.
 */
function badgeos_log_user_sent_achievement_to_obf( $user_id, $achievement_id ) {

	// Get user data from ID
	$user = get_userdata( $user_id );

	// Sanity check, if user doesnt exist, bail
	if ( ! is_object( $user ) || is_wp_error( $user ) )
		return;

	// Log the action
	$title = sprintf( __( '%1$s sent %2$s to Open Badge Factory', 'badgeos' ),
			$user->user_login,
			get_the_title( $achievement_id )
			);
	badgeos_post_log_entry( $achievement_id, $user_id, null, $title );
}
add_action( 'post_obf_user_badge', 'badgeos_log_user_sent_achievement_to_obf', 10, 2 );


/**
 * Sync OBF assertions
 * @since 1.4.7.2
 * @global type $badgeos_obf
 */
function sync_obf_assertions_function() {
    global $badgeos_obf;
    $badgeos_obf->sync_obf_assertions();
}
add_action( 'badgeos_obf_sync_assertions', 'sync_obf_assertions_function' );

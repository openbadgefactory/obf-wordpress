<?php
/**
* Plugin Name: Open Badge Factory
* Plugin URI: http://www.openbadgefactory.com/
* Description: Open Badge Factory -plugin lets your site’s users complete tasks and earn badges that recognize their achievement.  Define achievements and choose from a range of options that determine when they're complete.  Badges are Mozilla Open Badges (OBI) compatible.
* Author: Discendum Oy
* Version: 1.4.7.5
* Author URI: http://www.discendum.com/
* License: GNU AGPL
* Text Domain: badgeos
*/

/*
Copyright © 2015 Discendum Oy
Copyright © 2012-2014 LearningTimes, LLC

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU Affero General Public License, version 3,
as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General
Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>;.
*/


/**
 * Displays error messages from Open Badge Factory API key retrieval
 * @since  1.4.5
 * @return void
 */
function badgeos_obf_install_errors() {
        $has_notice = get_option( 'obf_install_error' );
        if (!empty($has_notice)) {
            // If we have an error message, we'll display it
            echo '<div id="message" class="error"><p>'. $has_notice .'</p></div>';
            // and then delete it
            delete_option( 'obf_install_error' );
        }
}
/**
 * Check if BadgeOS is already activated, 
 * and show a message about the two not being compatible with each other.
 * 
 * This prevents a nastly error message from showing, and everything failing.
 */
if (class_exists('BadgeOS') && function_exists('badgeos_obf_get_directory_path') 
        && !array_key_exists('badgeos_obf', $GLOBALS) && array_key_exists('badgeos', $GLOBALS)
    ) {
    update_option('obf_install_error', _('Open Badge Factory -plugin cannot co-exist with BadgeOS-plugin. Please disable the BadgeOS -plugin, if you wish to continue using the Open Badge Factory -plugin.') );
    add_action( 'all_admin_notices', 'badgeos_obf_install_errors' );
    return;
}
class BadgeOS {

	/**
	 * BadgeOS Version
	 *
	 * @var string
	 */
	public static $version = '1.4.7.5';
        public static $db_version = 6;
        
        private $settings;

	function __construct() {
		// Define plugin constants
		$this->basename       = plugin_basename( __FILE__ );
		$this->directory_path = plugin_dir_path( __FILE__ );
		$this->directory_url  = plugin_dir_url( __FILE__ );


		// Load translations
		load_plugin_textdomain( 'badgeos', false, 'badgeos/languages' );

		// Setup our activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Hook in all our important pieces
		add_action( 'plugins_loaded', array( $this, 'includes' ) );
                add_action( 'plugins_loaded', array( $this, 'check_plugin_update' ) );
		add_action( 'init', array( $this, 'register_scripts_and_styles' ) );
		add_action( 'init', array( $this, 'include_cmb' ), 999 );
		add_action( 'init', array( $this, 'register_achievement_relationships' ) );
		add_action( 'init', array( $this, 'register_image_sizes' ) );
		add_action( 'admin_menu', array( $this, 'plugin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_action( 'init', array( $this, 'credly_init' ) );
		add_action( 'init', array( $this, 'obf_init' ) );
                add_action( 'init', array( $this, 'check_plugin_update_init' ) );
                add_action( 'init', array( $this, 'svg_support_maybe_init' ) );
                add_action( 'init', array( $this, 'submodule_init' ) );
	}

	/**
	 * Include all our important files.
	 */
	function includes() {
                require_once( $this->directory_path . 'vendor/autoload.php' );
                require_once( $this->directory_path . 'includes/badgeos_global_functions.php' );
		require_once( $this->directory_path . 'includes/obf_client.php' );
		require_once( $this->directory_path . 'includes/p2p/load.php' );
		require_once( $this->directory_path . 'includes/class.BadgeOS_Editor_Shortcodes.php' );
		require_once( $this->directory_path . 'includes/class.BadgeOS_Plugin_Updater.php' );
		require_once( $this->directory_path . 'includes/class.BadgeOS_Shortcode.php' );
		require_once( $this->directory_path . 'includes/class.Credly_Badge_Builder.php' );
		require_once( $this->directory_path . 'includes/post-types.php' );
                require_once( $this->directory_path . 'includes/admin-settings-obf.php' );
		require_once( $this->directory_path . 'includes/admin-settings.php' );
		require_once( $this->directory_path . 'includes/achievement-functions-obf.php' );
                require_once( $this->directory_path . 'includes/achievement-functions.php' );
		require_once( $this->directory_path . 'includes/activity-functions.php' );
		require_once( $this->directory_path . 'includes/ajax-functions.php' );
		require_once( $this->directory_path . 'includes/logging-functions.php' );
		require_once( $this->directory_path . 'includes/meta-boxes.php' );
		require_once( $this->directory_path . 'includes/points-functions.php' );
		require_once( $this->directory_path . 'includes/triggers.php' );
		require_once( $this->directory_path . 'includes/steps-ui.php' );
		require_once( $this->directory_path . 'includes/shortcodes.php' );
		require_once( $this->directory_path . 'includes/content-filters.php' );
		require_once( $this->directory_path . 'includes/submission-actions.php' );
		require_once( $this->directory_path . 'includes/rules-engine.php' );
		require_once( $this->directory_path . 'includes/user.php' );
		require_once( $this->directory_path . 'includes/credly.php' );
		require_once( $this->directory_path . 'includes/obf.php' );
		require_once( $this->directory_path . 'includes/obf_svg_support.php' );
		require_once( $this->directory_path . 'includes/credly-badge-builder.php' );
		require_once( $this->directory_path . 'includes/widgets.php' );
                require_once( $this->directory_path . 'includes/submodule-base.php' );
                $this->submodule_includes();
	}
        function submodule_init() {
            $this->submodule_activations(true);
        }
        function submodule_includes() {
            if (!isset($GLOBALS['badgeos_community'])) {
                require_once( $this->directory_path . 'includes/community/community.php' );
            } else if (isset($GLOBALS['badgeos_community']) && !method_exists($GLOBALS['badgeos_community'], 'is_obf') ) {
                    update_option('obf_install_error', _('Open Badge Factory -plugin cannot co-exist with BadgeOS Community Add-On -plugin. Please disable the BadgeOS Community Add-On -plugin, if you wish to continue using the Open Badge Factory -plugin.') );
                    add_action( 'all_admin_notices', 'badgeos_obf_install_errors' );
            }
            
            if (!isset($GLOBALS['badgeos_learndash'])) {
                require_once( $this->directory_path . 'includes/learndash/learndash.php' );
            } else if (isset($GLOBALS['badgeos_learndash']) && !method_exists($GLOBALS['badgeos_learndash'], 'is_obf') ) {
                update_option('obf_install_error', _('Open Badge Factory -plugin cannot co-exist with BadgeOS LearnDash Add-On -plugin. Please disable the BadgeOS LearnDash Add-On -plugin, if you wish to continue using the Open Badge Factory -plugin.') );
                add_action( 'all_admin_notices', 'badgeos_obf_install_errors' );
            }
        }

	/**
	 * Register all core scripts and styles
	 *
	 * @since  1.3.0
	 */
	function register_scripts_and_styles() {
		// Register scripts
		wp_register_script( 'badgeos-admin-js', $this->directory_url . 'js/admin.js', array( 'jquery' ) );
		wp_register_script( 'badgeos-credly', $this->directory_url . 'js/credly.js' );
                wp_register_script( 'badgeos-obf', $this->directory_url . 'js/obf.js' );
                wp_register_script( 'badgeos-obf-modernizr', $this->directory_url . 'js/modernizr.js', array( 'jquery' ) );
                wp_register_script( 'badgeos-obf-shuffle', $this->directory_url . 'js/jquery.shuffle.js', array( 'jquery' ) );
                wp_register_script( 'badgeos-obf-shuffle-impl', $this->directory_url . 'js/gridfilters.js' );
		wp_register_script( 'badgeos-achievements', $this->directory_url . 'js/badgeos-achievements.js', array( 'jquery' ), '1.1.0', true );
		wp_register_script( 'credly-badge-builder', $this->directory_url . 'js/credly-badge-builder.js', array( 'jquery' ), '1.3.0', true );
                wp_register_script( 'badgeos-obf-fastfilterlive', $this->directory_url . 'js/customfastfilterlive.js' );
                

		// Register styles
		wp_register_style( 'badgeos-admin-styles', $this->directory_url . 'css/admin.css' );
                wp_register_style( 'badgeos-obf-admin-styles', $this->directory_url . 'css/admin-obf.css' );

		$badgeos_front = file_exists( get_stylesheet_directory() .'/badgeos.css' )
			? get_stylesheet_directory_uri() .'/badgeos.css'
			: $this->directory_url . 'css/badgeos-front.css';
		wp_register_style( 'badgeos-front', $badgeos_front, null, '1.0.1' );

		$badgeos_single = file_exists( get_stylesheet_directory() .'/badgeos-single.css' )
			? get_stylesheet_directory_uri() .'/badgeos-single.css'
			: $this->directory_url . 'css/badgeos-single.css';
		wp_register_style( 'badgeos-single', $badgeos_single, null, '1.0.1' );

		$badgeos_widget = file_exists( get_stylesheet_directory() .'/badgeos-widgets.css' )
			? get_stylesheet_directory_uri() .'/badgeos-widgets.css'
			: $this->directory_url . 'css/badgeos-widgets.css';
		wp_register_style( 'badgeos-widget', $badgeos_widget, null, '1.0.1' );
	}

	/**
	 * Initialize CMB.
	 */
	function include_cmb() {
		require_once( $this->directory_path . 'includes/cmb/load.php' );
	}

	/**
	 * Register custom Post2Post relationships
	 */
	function register_achievement_relationships() {

		// Grab all our registered achievement types and loop through them
		$achievement_types = badgeos_get_achievement_types_slugs();
		if ( is_array( $achievement_types ) && ! empty( $achievement_types ) ) {
			foreach ( $achievement_types as $achievement_type ) {

				// Connect steps to each achievement type
				// Used to get an achievement's required steps (e.g. This badge requires these 3 steps)
				p2p_register_connection_type( array(
					'name'      => 'step-to-' . $achievement_type,
					'from'      => 'step',
					'to'        => $achievement_type,
					'admin_box' => false,
					'fields'    => array(
						'order'   => array(
							'title'   => __( 'Order', 'badgeos' ),
							'type'    => 'text',
							'default' => 0,
						),
					),
				) );

				// Connect each achievement type to a step
				// Used to get a step's required achievement (e.g. this step requires earning Level 1)
				p2p_register_connection_type( array(
					'name'      => $achievement_type . '-to-step',
					'from'      => $achievement_type,
					'to'        => 'step',
					'admin_box' => false,
					'fields'    => array(
						'order'   => array(
							'title'   => __( 'Order', 'badgeos' ),
							'type'    => 'text',
							'default' => 0,
						),
					),
				) );

			}
		}

	}

	/**
	 * Register custom WordPress image size(s)
	 */
	function register_image_sizes() {
		add_image_size( 'badgeos-achievement', 100, 100 );
	}

	/**
	 * Activation hook for the plugin.
	 */
	function activate() {
		// Include our important bits
		$this->includes();
                $register_capabilities = false;

		// Create Badge achievement type
		if ( !get_page_by_title( 'Badge', 'OBJECT', 'achievement-type' ) ) {
			$badge_post_id = wp_insert_post( array(
				'post_title'   => __( 'Badge', 'badgeos'),
				'post_content' => __( 'Badges badge type', 'badgeos' ),
				'post_status'  => 'publish',
				'post_author'  => 1,
				'post_type'    => 'achievement-type',
			) );
			update_post_meta( $badge_post_id, '_badgeos_singular_name', __( 'Badge', 'badgeos' ) );
                        update_post_meta( $badge_post_id, '_badgeos_plural_name', __( 'Badges', 'badgeos' ) );
			update_post_meta( $badge_post_id, '_badgeos_show_in_menu', true );
                        update_post_meta( $badge_post_id, '_badgeos_use_obf_badges', true );
                        $badges_page = get_page_by_title( 'Badges', 'OBJECT', 'achievement-type' );
                        if ($badges_page) {
                            $badges_page_uses_obf = get_post_meta($badges_page->ID, '_badgeos_use_obf_badges', true);
                            if (empty($badges_page_uses_obf) || $badges_page_uses_obf == 'false') {
                                update_post_meta( $badges_page->ID, '_badgeos_show_in_menu', false );
                            }
                        }
                        $register_capabilities = true;
		}

		// Setup default BadgeOS options
		$badgeos_settings = ( $exists = $this->get_settings() ) ? $exists : array();
		if ( empty( $badgeos_settings ) ) {
			$badgeos_settings['minimum_role']     = 'manage_options';
                        $badgeos_settings['achievement_creator_role'] = 'manage_options';
			$badgeos_settings['submission_manager_role'] = 'manage_options';
			$badgeos_settings['submission_email'] = 'enabled';
			$badgeos_settings['debug_mode']       = 'disabled';
                        $badgeos_settings['db_version']       = self::$db_version;
                        $badgeos_settings['svg_support']      = 'enabled';
			$this->update_settings( $badgeos_settings );
                        $register_capabilities = true;
		}
                if ($register_capabilities) {
                    badgeos_register_achievement_capabilites($badgeos_settings['achievement_creator_role']);
                }

		// Setup default obf options
		$obf_settings = (array) get_option( 'obf_settings', array() );

		if ( empty( $obf_settings ) || !isset( $obf_settings[ 'obf_enable' ] ) ) {
			$obf_settings['obf_enable']                      = 'true';
                        $obf_settings['obf_client_id']                   = '__EMPTY__';
			$obf_settings['obf_badge_title']                 = 'post_title';
			$obf_settings['obf_badge_description']           = 'post_body';
			$obf_settings['obf_badge_short_description']     = 'post_excerpt';
			$obf_settings['obf_badge_criteria']              = '';
			$obf_settings['obf_badge_image']                 = 'featured_image';
			$obf_settings['obf_badge_testimonial']           = 'congratulations_text';
			$obf_settings['obf_badge_evidence']              = 'permalink';
			$obf_settings['obf_badge_sendemail_add_message'] = 'false';
			update_option( 'obf_settings', $obf_settings );
		}
                
                // Disable other service on activate
		$credly_settings = (array) get_option( 'credly_settings', array() );

		if ( empty( $credly_settings ) || isset( $credly_settings[ 'credly_enable' ] ) ) {
			$credly_settings['credly_enable']                      = 'false';
                        update_option( 'credly_settings', $credly_settings );
                }

		// Register our post types and flush rewrite rules
		badgeos_flush_rewrite_rules();
                $this->submodule_activations();
	}
        
        /**
	 * Activation hook for the plugin sub modules.
	 */
	function submodule_activations($maybe = false) {
            $sub_modules = array('badgeos_community');
            foreach($sub_modules as $sub_module) {
                if (isset($GLOBALS[$sub_module]) && is_object($GLOBALS[$sub_module]) && method_exists($GLOBALS[$sub_module], 'activate')) {
                    if ($maybe && method_exists($GLOBALS[$sub_module], 'maybe_activate') ) {
                        $GLOBALS[$sub_module]->maybe_activate();
                    }
                    $GLOBALS[$sub_module]->activate();
                }
            }
        }

	/**
	 * Create BadgeOS Settings menus
	 */
	function plugin_menu() {

		// Set minimum role setting for menus
		$manager_role = badgeos_get_manager_capability();
                $minimum_role = badgeos_get_minimum_capability();
                
                $creator_role = badgeos_get_achievement_creator_capability();
                
                $advanced_feature_parent = badgeos_obf_show_advanced_features() ? 'badgeos_badgeos' : 'options.php';
                
		// Create main menu
		add_menu_page( 'Open Badge Factory', 'Open Badge Factory', $minimum_role, 'badgeos_badgeos', 'badgeos_settings', $this->directory_url . 'images/obf_icon.png', 110 );

		// Create submenu items
		add_submenu_page( 'badgeos_badgeos', __( 'Open Badge Factory -Plugin Settings', 'badgeos' ), __( 'Settings', 'badgeos' ), $manager_role, 'badgeos_settings', 'badgeos_settings_page' );
		//add_submenu_page( 'badgeos_badgeos', __( 'Credly Integration', 'badgeos' ), __( 'Credly Integration', 'badgeos' ), $minimum_role, 'badgeos_sub_credly_integration', 'badgeos_credly_options_page' );
		add_submenu_page( 'badgeos_badgeos', __( 'OBF Integration', 'badgeos' ), __( 'OBF Integration', 'badgeos' ), $manager_role, 'badgeos_sub_obf_integration', 'badgeos_obf_options_page' );
		add_submenu_page( 'options.php', __( 'Add-Ons', 'badgeos' ), __( 'Add-Ons', 'badgeos' ), $minimum_role, 'badgeos_sub_add_ons', 'badgeos_add_ons_page' );
		add_submenu_page( 'badgeos_badgeos', __( 'Help / Support', 'badgeos' ), __( 'Help / Support', 'badgeos' ), $minimum_role, 'badgeos_sub_help_support', 'badgeos_help_support_page' );
                
                // Import badges
                add_submenu_page( 'options.php', __( 'OBF Import', 'badgeos' ), __( 'OBF Import', 'badgeos' ), $creator_role, 'badgeos_sub_obf_import', 'badgeos_obf_import_page' );

	}

	/**
	 * Admin scripts and styles
	 */
	function admin_scripts() {

		// Load scripts
		wp_enqueue_script( 'badgeos-admin-js' );
		wp_enqueue_script( 'badgeos-credly' );
                wp_enqueue_script( 'badgeos-obf' );
                wp_enqueue_script( 'badgeos-obf-modernizr' );
                wp_enqueue_script( 'badgeos-obf-shuffle' );
                wp_enqueue_script( 'badgeos-obf-shuffle-impl' );
                wp_enqueue_script( 'badgeos-obf-fastfilterlive' );

		// Load styles
		wp_enqueue_style( 'badgeos-admin-styles' );
                wp_enqueue_style( 'badgeos-obf-admin-styles' );
                

	}

	/**
	 * Frontend scripts and styles
	 */
	function frontend_scripts() {

		$data = array(
			'ajax_url'        => esc_url( admin_url( 'admin-ajax.php', 'relative' ) ),
			'message'         => __( 'Would you like to display this badge on social networks and add it to your lifelong badge collection?', 'badgeos' ),
			'confirm'         => __( 'Yes, send to Credly', 'badgeos' ),
			'cancel'          => __( 'Cancel', 'badgeos' ),
			'share'           => __( 'Share on Credly!', 'badgeos' ),
			'localized_error' => __( 'Error:', 'badgeos' ),
			'errormessage'    => __( 'Error: Timed out', 'badgeos' )
		);
		wp_localize_script( 'badgeos-achievements', 'BadgeosCredlyData', $data );
	}

	/**
	 * Deactivation hook for the plugin.
	 */
	function deactivate() {
		global $wp_rewrite;
		flush_rewrite_rules();
	}

	/**
	 * Initialize Credly API
	 */
	function credly_init() {

		// Initalize the CredlyAPI class
		$GLOBALS['badgeos_credly'] = new BadgeOS_Credly();

	}

	/**
	 * Initialize Open Badge Factory API
	 */
	function obf_init() {
		$GLOBALS['badgeos_obf'] = new BadgeOS_Obf();
	}
        
        /**
         * Initialize Open Badge Factory SVG Support
         */
        function svg_support_maybe_init() {
            $settings = $this->get_settings();
            if (array_key_exists('svg_support', $settings) && 'enabled' == $settings['svg_support']) {
                $GLOBALS['badgeos_obf_svg_support'] = BadgeOS_Obf_Svg_Support::get_instance();
            }
        }
        
        
        /**
         * Run database upgrade function if upgrading from a previous version.
         */
        public function check_plugin_update() {
            $settings = $this->get_settings();
            $previous_db_version = array_key_exists('db_version', $settings) ? (int)$settings['db_version'] : 0;
            if ($previous_db_version < self::$db_version) {
                $this->upgrade_plugin_db($previous_db_version);
            }
        }
        /**
         * Run database upgrade function if upgrading from a previous version.
         */
        public function check_plugin_update_init() {
            $settings = $this->get_settings();
            $previous_db_version = array_key_exists('db_version', $settings) ? (int)$settings['db_version'] : 0;
            if ($previous_db_version < self::$db_version) {
                $this->upgrade_plugin_db($previous_db_version, true);
            }
        }
        /**
         * Database upgrades.
         * @param boolean $init If running on the init hook or not.
         */
        public function upgrade_plugin_db($from, $init = false) {
            if (false == $init) {
                //Updated to be run on the plugins_loaded hook. (Before init)
                if ($from < 4) {
                    $this->update_setting('svg_support', 'enabled');
                }
            } else {
                // Updates to be run on the init hook.
                if ($from < 1) {
                    // Fix error 404 on badge view.
                    badgeos_flush_rewrite_rules();
                }
                if ($from < 5) {
                    if (array_key_exists('badgeos_obf', $GLOBALS)) {
                        sync_obf_assertions_function();
                    }
                }
                if ($from < 6) {
                    $this->update_setting('obf_api_url',"https:openbadgefactory.com/v1");
                }
                
                $this->update_setting('db_version', self::$db_version);
            }
        }
        /**
         * Get plugin settings
         * 
         * @return array The settings
         */
        public function get_settings() {
            if (empty($this->settings)) {
                $this->settings = get_option('badgeos_settings');
            }
            return $this->settings;
        }
        /**
         * Update plugin settings
         * 
         * @param array $settings The settings
         * @return \BadgeOS
         */
        public function update_settings($settings) {
            $this->settings = $settings;
            update_option('badgeos_settings', $settings);
            return $this;
        }
        /**
         * Update a plugin setting with value.
         * 
         * @param string $setting_name
         * @param mixed $setting_value
         * @return \BadgeOS
         */
        public function update_setting($setting_name, $setting_value) {
            $settings = $this->get_settings();
            $settings[$setting_name] = $setting_value;
            $this->update_settings($settings);
            return $this;
        }
        /**
         * Clear the settings cache variable, 
         * to force loading of settings with get_option.
         * 
         * @return \BadgeOS
         */
        public function clear_settings_cache() {
            $this->settings = null;
            return $this;
        }

}
$GLOBALS['badgeos'] = new BadgeOS();

/**
 * Get our plugin's directory path
 *
 * @since 1.0.0
 * @return string The filepath of the BadgeOS plugin root directory
 */
function badgeos_obf_get_directory_path() {
	return $GLOBALS['badgeos']->directory_path;
}

/**
 * Get our plugin's directory URL
 *
 * @since 1.0.0
 * @return string The URL for the BadgeOS plugin root directory
 */
function badgeos_obf_get_directory_url() {
	return $GLOBALS['badgeos']->directory_url;
}

/**
 * Check if debug mode is enabled
 *
 * @since  1.0.0
 * @return bool True if debug mode is enabled, false otherwise
 */
function badgeos_obf_is_debug_mode() {

	//get setting for debug mode
	$badgeos_settings = get_option( 'badgeos_settings' );
	$debug_mode = ( !empty( $badgeos_settings['debug_mode'] ) ) ? $badgeos_settings['debug_mode'] : 'disabled';

	if ( $debug_mode == 'enabled' ) {
		return true;
	}

	return false;

}

/**
 * Get plugin settings
 * 
 * @since 1.4.6
 * @return array
 */
function badgeos_obf_get_settings() {
    return $GLOBALS['badgeos']->get_settings();
}

/**
 * Update plugin settings
 * 
 * @since 1.4.6
 * @return BadgeOS
 */
function badgeos_obf_update_settings($settings) {
    return $GLOBALS['badgeos']->update_settings($settings);
}
/**
 * Clear the variable containing plugin settings,
 * so that next time badgeos_obf_get_settings is called,
 * the settings will be loaded with get_option.
 * @return BadgeOS
 */
function badgeos_obf_clear_settings_cache() {
    return $GLOBALS['badgeos']->clear_settings_cache();
}

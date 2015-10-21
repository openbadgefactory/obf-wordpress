<?php
/*
	Copyright 2013  Benbodhi  (email : wp@benbodhi.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2,
	as published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	The license for this software can likely be found here:
	http://www.gnu.org/licenses/gpl-2.0.html
	If not, write to the Free Software Foundation Inc.
	51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

/**
 * Open Badge Factory SVG Support. 
 * Adds support for SVG-images coming from Open Badge Factory.
 *
 * @package BadgeOS
 * @subpackage OBF_SVG_Support
 * @author Discendum Oy
 * @author Benbodhi
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 * @link https://openbadgefactory.com
 */

class BadgeOS_Obf_Svg_Support {
    private static $instance;
    
    public static function get_instance() {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    private function __construct() {
        add_action('admin_init', array($this, 'badgeos_obf_svgs_display_thumbs'));
        
        add_filter( 'post_thumbnail_html', array($this, 'remove_dimensions_svg'), 10 );
        add_filter( 'image_send_to_editor', array($this, 'remove_dimensions_svg'), 10 );
    }
    
    /**
     * Call this when wanting to allow upload of svg-images.
     * (Best time to call this would be on badge import, so regular userts cannot upload svgs.)
     */
    public function allow_upload() {
        add_filter( 'upload_mimes', array($this, 'badgeos_obf_svg_upload_mimes') );
    }
    
    // Add svg mime types to allowed types.
    function badgeos_obf_svg_upload_mimes($mimes = array()) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    // Fix display in media gallery etc.
    function badgeos_obf_svgs_display_thumbs() {

            ob_start();

            add_action( 'shutdown', 'badgeos_obf_svgs_thumbs_filter', 0 );
            function badgeos_obf_svgs_thumbs_filter() {

                $final = '';
                $ob_levels = count( ob_get_level() );

                for ( $i = 0; $i < $ob_levels; $i++ ) {

                    $final .= ob_get_clean();

                }

                echo apply_filters( 'final_output', $final );

            }

            add_filter( 'final_output', 'badgeos_obf_svgs_final_output' );
            function badgeos_obf_svgs_final_output( $content ) {

                    $content = str_replace(
                            '<# } else if ( \'image\' === data.type && data.sizes && data.sizes.full ) { #>',
                            '<# } else if ( \'svg+xml\' === data.subtype ) { #>
                                    <img class="details-image" src="{{ data.url }}" draggable="false" />
                                    <# } else if ( \'image\' === data.type && data.sizes && data.sizes.full ) { #>',

                            $content
                    );

                    $content = str_replace(
                            '<# } else if ( \'image\' === data.type && data.sizes ) { #>',
                            '<# } else if ( \'svg+xml\' === data.subtype ) { #>
                                    <div class="centered">
                                            <img src="{{ data.url }}" class="thumbnail" draggable="false" />
                                    </div>
                            <# } else if ( \'image\' === data.type && data.sizes ) { #>',

                            $content
                    );

                    return $content;

            }
    }
    // removes the width and height attributes during insertion of svg
    function remove_dimensions_svg( $html='' ) {
            return str_ireplace( array( " width=\"1\"", " height=\"1\"" ), "", $html );
    }
}
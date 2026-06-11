<?php
/*
Plugin Name: WP Grid Builder - Posts 2 Posts
Version: 1.0.1
Version Boilerplate: 3.5.0
Plugin URI: https://beapi.fr
Description: Add a P2P connexion facet for the plugin WP Grid Builder
Author: Be API Technical team
Author URI: https://beapi.fr
Domain Path: languages
Text Domain: wp-grid-builder-p2p

----

Copyright 2021 Be API Technical team (human@beapi.fr)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

// Plugin constants
define( 'WP_GRID_BUILDER_P2P_FACET_VERSION', '1.0.0' );
define( 'WP_GRID_BUILDER_P2P_FACET_VIEWS_FOLDER_NAME', 'wp-grid-builder-facetwp' );

// Plugin URL and PATH
define( 'WP_GRID_BUILDER_P2P_FACET_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_GRID_BUILDER_P2P_FACET_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_GRID_BUILDER_P2P_FACET_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );


// Require vendor
if ( file_exists( WP_GRID_BUILDER_P2P_FACET_DIR . '/vendor/autoload.php' ) ) {
	require WP_GRID_BUILDER_P2P_FACET_DIR . 'vendor/autoload.php';
}

add_action( 'plugins_loaded', 'init_wp_grid_builder_p2p_plugin' );
/**
 * Init the plugin
 */
function init_wp_grid_builder_p2p_plugin(): void {
	// Client
	\WP_Grid_Builder\P2P_Facet\Facet::get_instance();
}

<?php
/**
 * Plugin Name: Elementor Systeme.io Integration
 * Plugin URI: https://github.com/tyfricko/elementor-systemeio-integration.git
 * Description: Adds Systeme.io integration to Elementor forms
 * Version: 1.0.2
 * Author: Matej Zlatich
 * Author URI: https://matejzlatic.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elementor-systemeio
 * Requires at least: 5.6
 * Requires PHP: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function register_systemeio_action( $form_actions_registrar ) {
    require_once( __DIR__ . '/form-actions/systemeio.php' );
    $form_actions_registrar->register( new SystemeIO_Action_After_Submit() );
}

add_action( 'elementor_pro/forms/actions/register', 'register_systemeio_action', 20 );

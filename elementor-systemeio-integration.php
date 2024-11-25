<?php
/**
 * Plugin Name: Elementor Systeme.io Integration
 * Description: Integrates Elementor Forms with Systeme.io API
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: elementor-systemeio-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function register_systemeio_action( $form_actions_registrar ) {
    require_once( __DIR__ . '/form-actions/systemeio.php' );
    $form_actions_registrar->register( new SystemeIO_Action_After_Submit() );
}

add_action( 'elementor_pro/forms/actions/register', 'register_systemeio_action', 20 );

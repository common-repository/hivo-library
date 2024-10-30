<?php
/**
 * @package Hivo Connector
 * @version 0.0.3
 * @link https://wordpress.org/plugins/hivo-connector/
 *
 * Plugin Name:       HIVO Connector
 * Plugin URI:        https://wordpress.org/plugins/hivo-connector/
 * Description:       Hivo Connector for Wordpress
 * Version:           0.0.3
 * Author:            HIVO
 * Author URI:        https://www.hivo.com.au
 * Text Domain:       hivo-connector
 * Domain Path:       /lang/
 *
 * Copyight 2020 HIVO.
 */

if(!defined('ABSPATH')) {
  die('You are not allowed to call this page directly.');
}

/**
 * Let's get started!
 */
if (!class_exists('Hivo_Connector_Setup')) {
  class Hivo_Connector_Setup {

    /**
     * Constructor
     * @since 0.0.1
     */
    public function __construct() {
      $this->_define_constants();
      $this->_load_wp_includes();
      $this->_load_hivo_connector();
    }

    /**
     * Define paths
     * @since 0.0.1
     */
    private function _define_constants() {
      define('HIVO_CONNECTOR_VERSION', '0.0.3');
      define('HIVO_CONNECTOR_FOLDER', basename(dirname(__FILE__)));
      define('HIVO_CONNECTOR_DIR', plugin_dir_path(__FILE__));
      define('HIVO_CONNECTOR_INC', HIVO_CONNECTOR_DIR . 'includes/');
      define('HIVO_CONNECTOR_URL', plugin_dir_url(__FILE__));
      define('HIVO_CONNECTOR_INC_URL', HIVO_CONNECTOR_URL . 'includes/');
    }

    /**
     * WordPress includes used in plugin
     * @since 0.0.1
     */
    private function _load_wp_includes() {
      require_once(ABSPATH . 'wp-admin/includes/image.php');
    }

    /**
     * Load Hivo Connector
     * @since 0.0.1
     */
    private function _load_hivo_connector() {

      // Load plugin settings page.
      require_once(HIVO_CONNECTOR_INC . 'plugin-page.php');
      $hivo_connector_plugin_page = new Hivo_Connector_Plugin_Page();

      // Load Custom API.
      require_once(HIVO_CONNECTOR_INC . 'upload-api.php');
      $hivo_connector_upload_api = new Hivo_Connector_Upload_API();

    }
  }
}

/**
 * Initialize
 */
if (class_exists('Hivo_Connector_Setup')) {
  new Hivo_Connector_Setup();
}

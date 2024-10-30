<?php
/**
 * @package Hivo Connector
 */

if (!defined('ABSPATH')) {
  die('You are not allowed to call this page directly.');
}

if (!class_exists('Hivo_Connector_Plugin_Page')) {
  class Hivo_Connector_Plugin_Page {
    
    /**
     * Constructor
     * @since 0.0.1
     */
    public function __construct() {
      add_action('admin_menu', [$this, 'hivo_add_menu']);
    }

    /**
     * Adds left-hand menu
     * @since 0.0.1
     */
    public function hivo_add_menu() {
      $hivo_menu_page = add_menu_page(
        __('HIVO Connector', 'hivo-connector'),
        __('HIVO Connector', 'hivo-connector'),
        'upload_files',
        'hivo-options',
        [$this, 'hivo_plugin_page'],
        '',
        6
      );
    }

    /**
     * Renders plugin page
     * @since 0.0.1
     */
    public function hivo_plugin_page() {
      if (!current_user_can('upload_files')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
      }

      // Update the paths to the correct bundle directory
      wp_enqueue_style('hivo-connector', HIVO_CONNECTOR_URL.'bundle/index.css', null, HIVO_CONNECTOR_VERSION);
      wp_enqueue_script('hivo-connector', HIVO_CONNECTOR_URL.'bundle/index.js', null, HIVO_CONNECTOR_VERSION, true);
      wp_localize_script('hivo-connector', 'hivoConnectorSettings', array('nonce' => wp_create_nonce('wp_rest'), "url" => get_rest_url() . 'hivo-connector/v1'));
      include(plugin_dir_path(__FILE__) . 'templates/plugin.php');
    }

  }
}
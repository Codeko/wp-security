<?php
/**
 * Plugin Name: WP Security
 * Description: Improves security of WordPress
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action( 'init', 'wp_security_github_plugin_updater' );

function wp_security_github_plugin_updater() {
    include_once('updater.php');
    define( 'WP_GITHUB_FORCE_UPDATE', true );

    if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
        $config = array(
            'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
            'proper_folder_name' => 'wp-security', // this is the name of the folder your plugin lives in
            'api_url' => 'https://api.github.com/repos/Codeko/wp-security', // the GitHub API url of your GitHub repo
            'raw_url' => 'https://raw.github.com/Codeko/wp-security/main', // the GitHub raw url of your GitHub repo
            'github_url' => 'https://github.com/Codeko/wp-security', // the GitHub url of your GitHub repo
            'zip_url' => 'https://github.com/Codeko/wp-security/zipball/main', // the zip url of the GitHub repo
            'sslverify' => true, // whether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
            'requires' => '6.4.1', // which version of WordPress does your plugin require?
            'tested' => '6.4.1', // which version of WordPress is your plugin tested up to?
            'readme' => 'README.md', // which file to use as the readme for the version number
        );
        new WP_GitHub_Updater($config);
    }
}



function wp_security_custom_author_url(){
    return home_url('/');
}
add_filter('author_link', 'wp_security_custom_author_url');

function wp_security_disable_feed(){
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
    exit;
}

function wp_security_remove_feed_after_load(){
    add_action('do_feed', 'wp_security_disable_feed', 1);
    add_action('do_feed_rdf', 'wp_security_disable_feed', 1);
    add_action('do_feed_rss', 'wp_security_disable_feed', 1);
    add_action('do_feed_rss2', 'wp_security_disable_feed', 1);
    add_action('do_feed_atom', 'wp_security_disable_feed', 1);
    add_action('do_feed_rss2_comments', 'wp_security_disable_feed', 1);
    add_action('do_feed_atom_comments', 'wp_security_disable_feed', 1);
    remove_action('wp_head', 'feed_links_extra', 3);
    remove_action('wp_head', 'feed_links', 2);
}

add_action('plugins_loaded', 'wp_security_remove_feed_after_load');

function wp_security_sdxrpc_load_textdomain() {
    load_plugin_textdomain( 'simple-disable-xml-rpc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
  }
  add_action( 'plugins_loaded', 'wp_security_sdxrpc_load_textdomain' );

// Add settings page to the admin menu
function wp_security_sdxrpc_disable_menu() {
    add_options_page('Simple Disable XML-RPC', 'Simple Disable XML-RPC', 'manage_options', 'simple-disable-xml-rpc', 'sdxrpc_disable_settings_page');
}
add_action('admin_menu', 'wp_security_sdxrpc_disable_menu');

// Register plugin settings
function wp_security_sdxrpc_disable_register_settings() {
    register_setting('simple-disable-xml-rpc-group', 'xmlrpc_disable_enabled');
}
add_action('admin_init', 'wp_security_sdxrpc_disable_register_settings');

// Settings page content
function wp_security_sdxrpc_disable_settings_page() {
    ?>
    <div class="wrap">
        <h2>Simple Disable XML-RPC Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('simple-disable-xml-rpc-group'); ?>
            <?php $enabled = get_option('xmlrpc_disable_enabled'); ?>
            <label for="xmlrpc_disable_enabled">
                <input type="checkbox" id="xmlrpc_disable_enabled" name="xmlrpc_disable_enabled" <?php checked($enabled, 'on'); ?> />
                Disable XML-RPC
            </label>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Filter xmlrpc_enabled based on user settings
function wp_security_sdxrpc_disable_xmlrpc_enabled($enabled) {
    $disable = get_option('xmlrpc_disable_enabled');
    if ($disable === 'on') {
        return false;
    }
    return $enabled;
}
add_filter('xmlrpc_enabled', 'wp_security_sdxrpc_disable_xmlrpc_enabled');

// Simple Disable XML-RPC Option Links

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wp_security_sdxr_add_action_links' );

function wp_security_sdxr_add_action_links ( $actions ) {
   $mylinks = array(
      '<a href="' . admin_url( 'options-general.php?page=simple-disable-xml-rpc' ) . '">Settings</a>',
   );
   $actions = array_merge( $actions, $mylinks );
   return $actions;
}

// Redirect to settings page once the plugin is activated

function wp_security_sdxrpc_activation_redirect( $plugin ) {
    if( $plugin == plugin_basename( __FILE__ ) ) {
        wp_safe_redirect( admin_url( 'options-general.php?page=simple-disable-xml-rpc' ) );
exit;
    }
}
add_action( 'activated_plugin', 'wp_security_sdxrpc_activation_redirect' );
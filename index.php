<?php
/**
 * Plugin Name: POLA-CDK - WP Security
 * Description: Improves security of WordPress
 * Version: 1.0.3
 * Author: Pola Network
 * Author URI: https://github.com/Codeko/wp-security
 */

if (!defined('ABSPATH')) {
    exit;
}

const WPSP_HTACCESS_MARKER = "WpSecurityPlugin";
const WPSP_HTACCESS_BLOCKS = array(
    "<ifModule mod_headers.c>\nHeader set X-Frame-Options SAMEORIGIN\n</ifModule>",
    "RewriteEngine On\nRewriteCond %{REQUEST_URI} ^/wp-cron.php\nRewriteRule ^(.*)$ - [L,R=404]",
    "RewriteEngine On\nRewriteCond %{REQUEST_URI} ^/.git\nRewriteRule ^(.*)$ - [L,R=404]",
    "RewriteEngine On\nRewriteCond %{REQUEST_URI} ^/readme.html\nRewriteRule ^(.*)$ - [L,R=404]",
    "RewriteEngine On\nRewriteCond %{REQUEST_URI} ^/xmlrpc.php\nRewriteRule ^(.*)$ - [L,R=404]",
);


function wp_security_plugin_activation()
{
    if (!is_admin()) {
        return;
    }
    wp_security_execute_on_plugin_update();
}
register_activation_hook(__FILE__, 'wp_security_plugin_activation');

function wp_security_plugin_loaded()
{
    if (is_admin()) {
        $previous_version = get_option('wp-security-plugin-version', 0);
        if ($previous_version != wp_security_get_plugin_version()) {
            wp_security_execute_on_plugin_update();
        }
    }
    wp_security_secure_cookie();
    wp_security_remove_feed_after_load();
}
add_action('plugins_loaded', 'wp_security_plugin_loaded');

function wp_security_plugin_deactivate()
{
    wp_security_add_htaccess_rules(["# WP Security plugin is deactivated", "# this block can be safely removed"]);
}

add_action("deactivate_plugin", "wp_security_plugin_deactivate", 10, 1);


function wp_security_execute_on_plugin_update()
{
    update_option('wp-security-plugin-version', wp_security_get_plugin_version());
    wp_security_add_htaccess_rules();
}

function wp_security_github_plugin_updater()
{
    include_once('updater.php');
    define('WP_GITHUB_FORCE_UPDATE', true);

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
add_action('init', 'wp_security_github_plugin_updater');


function wp_security_custom_author_url()
{
    return home_url('/');
}
add_filter('author_link', 'wp_security_custom_author_url');

function wp_security_secure_cookie()
{
    @ini_set('session.cookie_httponly', true);
    @ini_set('session.cookie_secure', true);
    @ini_set('session.use_only_cookies', true);
    @ini_set('session.use_strict_mode', 1);
}

function wp_security_disable_feed()
{
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
    exit;
}

function wp_security_remove_feed_after_load()
{
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

/**
 * Unset XML-RPC Methods.
 *
 * @param array $methods Array of current XML-RPC methods.
 */
function wp_security_sar_block_xmlrpc_attacks($methods)
{
    unset($methods['pingback.ping']);
    unset($methods['pingback.extensions.getPingbacks']);
    return $methods;
}
add_filter('xmlrpc_methods', 'wp_security_sar_block_xmlrpc_attacks');

/**
 * Add rules to HTACCESS file
 */
function wp_security_add_htaccess_rules($rules = null)
{
    if (!$rules) {
        $rules = WPSP_HTACCESS_BLOCKS;
    }
    $htaccess_path = ABSPATH . '.htaccess';
    if (file_exists($htaccess_path)) {
        insert_with_markers($htaccess_path, WPSP_HTACCESS_MARKER, $rules);
    }
}

function wp_security_get_plugin_version()
{
    $plugin_data = get_plugin_data(__FILE__);
    return $plugin_data['Version'];
}


/**
 * Check WP version.
 */
if (version_compare(get_bloginfo('version'), '4.4', '>=')) {

    add_action('wp', 'wp_security_sar_remove_x_pingback_header_44', 9999);

    /**
     * Remove X-Pingback from Header for WP 4.4+.
     */
    function wp_security_sar_remove_x_pingback_header_44()
    {
        header_remove('X-Pingback');
    }
} elseif (version_compare(get_bloginfo('version'), '4.4', '<')) {

    add_filter('wp_headers', 'wp_security_sar_remove_x_pingback_header');

    /**
     * Remove X-Pingback from Header for older WP versions.
     *
     * @param array $headers Array with current headers.
     */
    function wp_security_sar_remove_x_pingback_header($headers)
    {
        unset($headers['X-Pingback']);
        return $headers;
    }
}

<?php
/**
 * @package    Orca
 * @subpackage MetaURL
 * @author     jonnu <code@jonnu.eu>
 */

/*
Plugin Name: Orca MetaURL
Plugin URI: https://github.com/jonnu/orca-wp-metaurl
Description: Attach a customisable URL to each post via a clickable button
Version: 1.0.3
Author: Jonnu
Author URI: http://jonnu.eu/
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


class Orca_Wordpress_MetaURL extends Orca_Wordpress
{

    public function __construct ($options = array())
    {
        parent::__construct($options);

        add_action('save_post', array($this, 'save'));
        add_action('add_meta_boxes', array($this, 'generate'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));

        add_filter('the_content', array($this, 'embed'), 20);
    }


    public function embed ($content)
    {
        if (!is_single()) {
            return $content;
        }

        return $content . $this->display($GLOBALS['post']->ID);
    }


    public function save ($post_id)
    {
        $nonce = plugin_basename(__FILE__);
        if (!wp_verify_nonce($_POST['orca_wp_meta_nonce'], $nonce)) {
            return $post_id;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        $current_url      = get_post_meta($post_id, 'orca_wp_meta', true);
        $current_position = get_post_meta($post_id, 'orca_wp_meta_position', true);
        $saved_url        = stripslashes($_POST['orca_wp_meta']);
        $saved_position   = $_POST['orca_wp_meta_position'];

        if (empty($saved_url)) {
            delete_post_meta($post_id, 'orca_wp_meta', $current_url);
            delete_post_meta($post_id, 'orca_wp_meta_position', $current_position);
            return;
        }

        // Apply a scheme if one is missing
        $chunked_url = parse_url($saved_url);
        if (!$chunked_url || !array_key_exists('scheme', $chunked_url) || empty($chunked_url['scheme'])) {
            $saved_url = 'http://' . $saved_url;
        }

        if (empty($saved_position)) {
            delete_post_meta($post_id, 'orca_wp_meta_position', $current_position);
        }

        if (empty($current_url) && !empty($saved_url)) {
            add_post_meta($post_id, 'orca_wp_meta', $saved_url, true);
            add_post_meta($post_id, 'orca_wp_meta_position', $saved_position, true);
            return;
        }

        if ($current_url !== $saved_url) {
            update_post_meta($post_id, 'orca_wp_meta', $saved_url);
        }

        if ($current_position !== $saved_position) {
            update_post_meta($post_id, 'orca_wp_meta_position', $saved_position);
        }
    }


    public function display ($post_id)
    {
        $metaUrl      = get_post_meta($post_id, 'orca_wp_meta', true);
        $metaPosition = get_post_meta($post_id, 'orca_wp_meta_position', true);

        if (empty($metaUrl)) {
            return null;
        }

        $metaClasses  = array('orca-meta-container');
        if (in_array($metaPosition, array('left', 'center', 'right'))) {
            $metaClasses[] = 'position-' . $metaPosition;
        }

        return sprintf('<div class="%s">', implode(' ', $metaClasses)) .
               '    <a href="' . $metaUrl . '" class="orca-wp-button" rel="nofollow">Check it out</a>' .
               '</div>';
    }


    public function administrate ($post, $box)
    {
        $nonce    = wp_create_nonce(plugin_basename(__FILE__));
        $value    = wp_specialchars(get_post_meta($post->ID, 'orca_wp_meta', true), 1);
        $position = get_post_meta($post->ID, 'orca_wp_meta_position', true);

        $html = $this->render('admin/fields', array(
            'nonce'          => $nonce,
            'value'          => $value,
            'value_position' => $position
        ));

        if (!is_wp_error($html)) {
            echo $html;
            return;
        }

        echo $html->get_error_message();
    }


    public function generate ($post_type)
    {
        if (!in_array($post_type, array('post'))) {
            return;
        }

        add_meta_box(
              'orca_wp_meta'
            , __('Orca Meta URL', 'orca-wp')
            , array($this, 'administrate')
            , $post_type
            , 'advanced'
            , 'high'
        );
    }


    public function enqueue ()
    {
        $plugin = plugin_basename(__FILE__);
        $folder = dirname($plugin);

        wp_register_style('orca_wp_meta', plugins_url($folder . '/static/css/orca-wp-metaurl.css'));
        wp_register_style('orca_wp_meta_admin', plugins_url($folder . '/static/css/orca-wp-metaurl-admin.css'));
        wp_enqueue_style('orca_wp_meta');

        if (is_admin()) {
            wp_enqueue_style('orca_wp_meta_admin');
            wp_enqueue_script('orca_wp_admin', plugins_url($folder . '/static/js/orca-wp-admin.js'), array('jquery'));
        }
    }


}


abstract class Orca_Wordpress
{

    /**
     * Options
     *
     * @var array
     */
    protected $options;


    public function __construct ($options = array())
    {
        $defaults = array(
              'repository'    => $this->translateClassToSlug()
            , 'sslverify'     => true
            , 'api_token'     => null
            , 'cache_enabled' => true
            , 'cache_ttl'     => 60
        );

        $this->options = array_merge($defaults, $options);

        add_filter('pre_set_site_transient_update_plugins', array($this, 'checkPluginUpdate'));
        add_filter('plugins_api', array($this, 'checkPluginInfo'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'postUpdateProcess'), 10, 3);
    }


    protected function translateClassToSlug ()
    {
        $class = strtolower(get_class($this));
        $slug  = str_replace(array('_', 'wordpress'), array('-', 'wp'), $class);

        return $slug;
    }


    protected function render ($view, $variables = array())
    {
        $basedir   = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname(plugin_basename(__FILE__));
        $extension = pathinfo(__FILE__, PATHINFO_EXTENSION);
        $template  = $basedir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $view . '.' . $extension;

        if (!file_exists($template)) {
            return new WP_Error('orca-render', __("Template is missing: " . $view));
        }

        ob_start();
        extract($variables);
        include_once($template);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }


    protected function callApi ($call = '', $force = false)
    {
        $force    = $force || !$this->options['cache_enabled'];
        $cacheKey = sprintf('%s-api-%s', $this->options['repository'], $call);

        if (!$force && (false !== $data = get_site_transient($cacheKey))) {
            return $data;
        }

        $apiCall  = empty($call) ? '' : '/' . $call;
        $endpoint = 'https://api.github.com/repos/jonnu/' . $this->options['repository'] . $apiCall;

        if (array_key_exists('api_token', $this->options) && null !== $this->options['api_token']) {
            $endpoint = add_query_arg(array('access_token' => $this->options['api_token']), $endpoint);
        }

        $remote = wp_remote_get($endpoint, array('sslverify' => $this->options['sslverify']));
        if (is_wp_error($remote)) {
            return false;
        }

        $data = json_decode($remote['body']);
        if (null !== $data && $this->options['cache_enabled']) {
            set_site_transient($cacheKey, $data, $this->options['cache_ttl']);
        }

        return $data;
    }


    protected function getLatestRelease ()
    {
        $index    = 0;
        $maximum  = 0;
        $releases = $this->callApi('releases', true);

        foreach ($releases as $i => $release) {
            if (version_compare($maximum, $release->tag_name) > 0) {
                $maximum = $release->tag_name;
                $index   = $i;
            }
        }

        return $releases[$index];
    }


    protected function getCurrentVersion ()
    {
        $cacheKey = $this->translateClassToSlug() . '-version';
        if (!$version = get_site_transient($cacheKey)) {
            $version = $this->parseCurrentVersion();
            set_site_transient($cacheKey, $version, $this->options['cache_ttl']);
        }

        return $version;
    }


    public function parseCurrentVersion ()
    {
        $source = file_get_contents(__FILE__);
        foreach (token_get_all($source) as $token) {

            if (!in_array($token[0], array(T_COMMENT, T_ML_COMMENT))) {
                continue;
            }

            if (!preg_match('/^[Vv]ersion:[\s\t]*(.*)$/m', $token[1], $match)) {
                continue;
            }

            return $match[1];
        }

        return null;
    }


    public function postUpdateProcess ($true, $hook_extra, $result)
    {
        global $wp_filesystem;

        // Invalidate cache
        delete_transient($this->translateClassToSlug() . '-version');

        $plugin = plugin_basename(__FILE__);
        $folder = dirname($plugin);

        // Move
        $destination = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $folder;
        $wp_filesystem->move($result['destination'], $destination);
        $result['destination'] = $destination;

        // Re-activate
        $activate = activate_plugin(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin);

        // Output the update message
        $failure = __('The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'ddd');
        $success = __('Plugin reactivated successfully.', 'ddd');

        echo is_wp_error($activate) ? $failure : $success;

        return $result;
    }


    /**
     * Check plugin update.
     *
     * Override the callback to WordPress' plugin API.
     *
     * @return stdClass
     */
    public function checkPluginUpdate ($check)
    {
        // Disabled for development...
        if (false && empty($check->checked)) {
            return $check;
        }

        $current = $this->getCurrentVersion();
        $latest  = $this->getLatestRelease();

        if (version_compare($current, $latest->tag_name) < 0) {

            $slug   = plugin_basename(__FILE__);
            $folder = dirname($slug);

            $update                 = new stdClass;
            $update->slug           = $folder;
            $update->new_version    = $latest->tag_name;
            $update->url            = 'https://github.com/jonnu/';
            $update->package        = $latest->zipball_url;
            $check->response[$slug] = $update;
        }

        return $check;
    }


    public function checkPluginInfo ()
    {
        list(, $action, $response) = func_get_args();

        $action = strtolower($action);
        $plugin = plugin_basename(__FILE__);
        $folder = dirname($plugin);
        $latest = $this->getLatestRelease();

        // Return false to fallback to WP API
        if (!isset($response->slug) || $response->slug !== $folder) {
            return false;
        }

        $response->author        = 'jonnu';
        $response->tested        = '';
        $response->version       = $latest->tag_name;
        $response->homepage      = 'http://jonnu.eu/';
        $response->sections      = array('description' => $latest->name, 'changelog' => $latest->body);
        $response->downloaded    = 0;
        $response->plugin_name   = $folder;
        $response->last_updated  = $latest->published_at;
        $response->download_link = $latest->zipball_url;

        return $response;
    }


}

new Orca_Wordpress_MetaURL();

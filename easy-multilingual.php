<?php
/*
Plugin Name: EasyMultilingual
Plugin URI: http://easyMultilingual.wordpress.com/
Version: 1.5.4
Author: Frédéric Demarle
Description: Adds multilingual capability to WordPress
Text Domain: easyMultilingual
Domain Path: /languages
*/

/*
 * Copyright 2011-2014 Frédéric Demarle
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 */

// don't access directly
if (!function_exists('add_action'))
	exit();

define('EASYMULTILINGUAL_VERSION', '1.5.4');
define('EML_MIN_WP_VERSION', '3.5');

define('EASYMULTILINGUAL_BASENAME', plugin_basename(__FILE__)); // plugin name as known by WP

define('EASYMULTILINGUAL_DIR', dirname(__FILE__)); // our directory
define('EML_INC', EASYMULTILINGUAL_DIR . '/include');
define('EML_FRONT_INC',  EASYMULTILINGUAL_DIR . '/frontend');
define('EML_ADMIN_INC',  EASYMULTILINGUAL_DIR . '/admin');

// default directory to store user data such as custom flags
if (!defined('EML_LOCAL_DIR'))
	define('EML_LOCAL_DIR', WP_CONTENT_DIR . '/easyMultilingual');

// includes local config file if exists
if (file_exists(EML_LOCAL_DIR . '/pll-config.php'))
	include_once(EML_LOCAL_DIR . '/pll-config.php');

// our url. Don't use WP_PLUGIN_URL http://wordpress.org/support/topic/ssl-doesnt-work-properly
define('EASYMULTILINGUAL_URL', plugins_url('/' . basename(EASYMULTILINGUAL_DIR)));

// default url to access user data such as custom flags
if (!defined('EML_LOCAL_URL'))
	define('EML_LOCAL_URL', content_url('/easyMultilingual'));

// cookie name. no cookie will be used if set to false
if (!defined('EML_COOKIE'))
	define('EML_COOKIE', 'pll_language');

// backward compatibility WP < 3.6
// the search form js is no more needed in WP 3.6+ except if the search form is hardcoded elsewhere than in searchform.php
if (!defined('EML_SEARCH_FORM_JS') && !version_compare($GLOBALS['wp_version'], '3.6', '<'))
	define('EML_SEARCH_FORM_JS', false);

/*
 * controls the plugin, as well as activation, and deactivation
 *
 * @since 0.1
 */
class EasyMultilingual {

	/*
	 * constructor
	 *
	 * @since 0.1
	 */
	public function __construct() {
		// manages plugin activation and deactivation
		register_activation_hook( __FILE__, array(&$this, 'activate'));
		register_deactivation_hook( __FILE__, array(&$this, 'deactivate'));

		// stopping here if we are going to deactivate the plugin (avoids breaking rewrite rules)
		if (isset($_GET['action'], $_GET['plugin']) && 'deactivate' == $_GET['action'] && plugin_basename(__FILE__) == $_GET['plugin'])
			return;

		// avoid loading easyMultilingual admin for frontend ajax requests
		// special test for plupload which does not use jquery ajax and thus does not pass our ajax prefilter
		// special test for customize_save done in frontend but for which we want to load the admin
		if (!defined('EML_AJAX_ON_FRONT')) {
			$in = isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('upload-attachment', 'customize_save'));
			define('EML_AJAX_ON_FRONT', defined('DOING_AJAX') && DOING_AJAX && empty($_REQUEST['pll_ajax_backend']) && !$in);
		}

		if (!defined('EML_ADMIN'))
			define('EML_ADMIN', defined('DOING_CRON') || (is_admin() && !EML_AJAX_ON_FRONT));

		if (!defined('EML_SETTINGS'))
			define('EML_SETTINGS', is_admin() && isset($_GET['page']) && $_GET['page'] == 'mlang');

		// blog creation on multisite
		add_action('wpmu_new_blog', array(&$this, 'wpmu_new_blog'), 5); // before WP attempts to send mails which can break on some PHP versions

		// FIXME maybe not available on every installations but widely used by WP plugins
		spl_autoload_register(array(&$this, 'autoload')); // autoload classes

		// override load text domain waiting for the language to be defined
		// here for plugins which load text domain as soon as loaded :(
		if (!defined('EML_OLT') || EML_OLT)
			new EML_OLT_Manager();

		// plugin initialization
		// take no action before all plugins are loaded
		add_action('plugins_loaded', array(&$this, 'init'), 1);

		// loads the API
		require_once(EML_INC.'/api.php');

		// WPML API
		if (!defined('EML_WPML_COMPAT') || EML_WPML_COMPAT)
			require_once (EML_INC.'/wpml-compat.php');

		// extra code for compatibility with some plugins
		if (!defined('EML_PLUGINS_COMPAT') || EML_PLUGINS_COMPAT)
			new EML_Plugins_Compat();
	}

	/*
	 * activation or deactivation for all blogs
	 *
	 * @since 1.2
	 *
	 * @param string $what either 'activate' or 'deactivate'
	 */
	protected function do_for_all_blogs($what) {
		// network
		if (is_multisite() && isset($_GET['networkwide']) && ($_GET['networkwide'] == 1)) {
			global $wpdb;

			foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
				switch_to_blog($blog_id);
				$what == 'activate' ? $this->_activate() : $this->_deactivate();
			}
			restore_current_blog();
		}

		// single blog
		else
			$what == 'activate' ? $this->_activate() : $this->_deactivate();
	}

	/*
	 * plugin activation for multisite
	 *
	 * @since 0.1
	 */
	public function activate() {
		global $wp_version;
		load_plugin_textdomain('easyMultilingual', false, basename(EASYMULTILINGUAL_DIR).'/languages'); // plugin i18n

		if (version_compare($wp_version, EML_MIN_WP_VERSION , '<'))
			die (sprintf('<p style = "font-family: sans-serif; font-size: 12px; color: #333; margin: -5px">%s</p>',
				sprintf(__('You are using WordPress %s. EasyMultilingual requires at least WordPress %s.', 'easyMultilingual'),
					esc_html($wp_version),
					EML_MIN_WP_VERSION
				)
			));

		$this->do_for_all_blogs('activate');
	}

	/*
	 * plugin activation
	 *
	 * @since 0.5
	 */
	protected function _activate() {
		global $easyMultilingual;

		if ($options = get_option('easyMultilingual')) {
			// plugin upgrade
			if (version_compare($options['version'], EASYMULTILINGUAL_VERSION, '<')) {
				$upgrade = new EML_Upgrade($options);
				$upgrade->upgrade_at_activation();
			}
		}
		// defines default values for options in case this is the first installation
		else {
			$options = array(
				'browser'       => 1, // default language for the front page is set by browser preference
				'rewrite'       => 1, // remove /language/ in permalinks (was the opposite before 0.7.2)
				'hide_default'  => 0, // do not remove URL language information for default language
				'force_lang'    => 0, // do not add URL language information when useless
				'redirect_lang' => 0, // do not redirect the language page to the homepage
				'media_support' => 1, // support languages and translation for media by default
				'sync'          => array(), // synchronisation is disabled by default (was the opposite before 1.2)
				'post_types'    => array_values(get_post_types(array('_builtin' => false, 'show_ui => true'))),
				'taxonomies'    => array_values(get_taxonomies(array('_builtin' => false, 'show_ui => true'))),
				'domains'       => array(),
				'version'       => EASYMULTILINGUAL_VERSION,
			);

			update_option('easyMultilingual', $options);
		}

		// always provide a global $easyMultilingual object and add our rewrite rules if needed
		$easyMultilingual = new StdClass();
		$easyMultilingual->options = &$options;
		$easyMultilingual->model = new EML_Admin_Model($options);
		$easyMultilingual->links_model = $easyMultilingual->model->get_links_model();
		flush_rewrite_rules();
	}

	/*
	 * plugin deactivation for multisite
	 *
	 * @since 0.1
	 */
	public function deactivate() {
		$this->do_for_all_blogs('deactivate');
	}

	/*
	 * plugin deactivation
	 *
	 * @since 0.5
	 */
	protected function _deactivate() {
		flush_rewrite_rules();
	}

	/*
	 * blog creation on multisite (to set default options)
	 *
	 * @since 0.9.4
	 *
	 * @param int $blog_id
	 */
	public function wpmu_new_blog($blog_id) {
		switch_to_blog($blog_id);
		$this->_activate();
		restore_current_blog();
	}

	/*
	 * autoload classes
	 *
	 * @since 1.2
	 *
	 * @param string $class
	 */
	public function autoload($class) {
		$class = str_replace('_', '-', strtolower(substr($class, 4)));
		foreach (array(EML_INC, EML_FRONT_INC, EML_ADMIN_INC) as $path) {
			if (file_exists($file = "$path/$class.php")) {
				require_once($file);
				break;
			}
		}
	}

	/*
	 * EasyMultilingual initialization
	 * setups models and separate admin and frontend
	 *
	 * @since 1.2
	 */
	public function init() {
		global $easyMultilingual;

		$options = get_option('easyMultilingual');

		// plugin upgrade
		if ($options && version_compare($options['version'], EASYMULTILINGUAL_VERSION, '<')) {
			$upgrade = new EML_Upgrade($options);
			if (!$upgrade->upgrade()) // if the version is too old
				return;
		}

		$class = apply_filters('pll_model', EML_SETTINGS ? 'EML_Admin_Model' : 'EML_Model');
		$model = new $class($options);
		$links_model = $model->get_links_model();

		if (EML_ADMIN) {
			$easyMultilingual = new EML_Admin($links_model);
			$easyMultilingual->init();
		}
		// do nothing on frontend if no language is defined
		elseif ($model->get_languages_list()) {
			$easyMultilingual = new EML_Frontend($links_model);
			$easyMultilingual->init();
		}

		if (!$model->get_languages_list())
			do_action('pll_no_language_defined'); // to load overriden textdomains

		// load wpml-config.xml
		if (!defined('EML_WPML_COMPAT') || EML_WPML_COMPAT)
			new EML_WPML_Config;
	}
}

new EasyMultilingual();

<?php

/*
 * base class for both admin and frontend
 *
 * @since 1.2
 */
abstract class EML_Base {
	public $links_model, $model, $options;

	/*
	 * constructor
	 *
	 * @since 1.2
	 *
	 * @param object $links_model
	 */
	public function __construct(&$links_model) {
		$this->links_model = &$links_model;
		$this->model = &$links_model->model;
		$this->options = &$this->model->options;

		add_action('widgets_init', array(&$this, 'widgets_init'));

		// user defined strings translations
		add_action('eml_language_defined', array(&$this, 'load_strings_translations'), 5);

		// switch_to_blog
		add_action('switch_blog', array(&$this, 'switch_blog'), 10, 2);
	}

	/*
	 * registers our widgets
	 *
	 * @since 0.1
	 */
	public function widgets_init() {
		register_widget('EML_Widget_Languages');

		// overwrites the calendar widget to filter posts by language
		if (!defined('EML_WIDGET_CALENDAR') || EML_WIDGET_CALENDAR) {
			unregister_widget('WP_Widget_Calendar');
			register_widget('EML_Widget_Calendar');
		}

		// overwrites the recent posts and recent comments widget to use a language dependant cache key
		// useful only if using a cache plugin
		if (defined('WP_CACHE') && WP_CACHE) {
			if (!defined('EML_WIDGET_RECENT_POSTS') || EML_WIDGET_RECENT_POSTS) {
				unregister_widget('WP_Widget_Recent_Posts');
				register_widget('EML_Widget_Recent_Posts');
			}

			if (!defined('EML_WIDGET_RECENT_COMMENTS') || EML_WIDGET_RECENT_COMMENTS) {
				unregister_widget('WP_Widget_Recent_Comments');
				register_widget('EML_Widget_Recent_Comments');
			}
		}
	}

	/*
	 * loads user defined strings translations
	 *
	 * @since 1.2
	 */
	public function load_strings_translations() {
		$mo = new EML_MO();
		$mo->import_from_db($this->model->get_language(get_locale()));
		$GLOBALS['l10n']['eml_string'] = &$mo;
	}

	/*
	 * resets some variables when switching blog
	 * applies only if EasyMultilingual is active on the new blog
	 *
	 * @since 1.5.1
	 *
	 * @return bool not used by WP but by child class
	 */
	public function switch_blog($new_blog, $old_blog) {
		$plugins = ($sitewide_plugins = get_site_option('active_sitewide_plugins')) && is_array($sitewide_plugins) ? array_keys($sitewide_plugins) : array();
		$plugins = array_merge($plugins, get_option('active_plugins', array()));

		// 2nd test needed when EasyMultilingual is not networked activated
		// 3rd test needed when EasyMultilingual is networked activated and a new site is created
		if ($new_blog != $old_blog && in_array(EASYMULTILINGUAL_BASENAME, $plugins) && get_option('easyMultilingual')) {
			$this->model->blog_id = $new_blog;
			$this->options = get_option('easyMultilingual'); // needed for menus
			$this->links_model = $this->model->get_links_model();
			return true;
		}
		return false;
	}

	/*
	 * some backward compatibility with EasyMultilingual < 1.2
	 * allows for example to call $easyMultilingual->get_languages_list() instead of $easyMultilingual->model->get_languages_list()
	 * this works but should be slower than the direct call, thus an error is triggered in debug mode
	 *
	 * @since 1.2
	 *
	 * @param string $func function name
	 * @param array $args function arguments
	 */
	public function __call($func, $args) {
		foreach ($this as $prop => &$obj)
			if (is_object($obj) && method_exists($obj, $func)) {
				if (WP_DEBUG) {
					$debug = debug_backtrace();
					trigger_error(sprintf(
						'%1$s was called incorrectly in %3$s on line %4$s: the call to $easyMultilingual->%1$s() has been deprecated in EasyMultilingual 1.2, use $easyMultilingual->%2$s->%1$s() instead.' . "\nError handler",
						$func, $prop, $debug[1]['file'], $debug[1]['line']
					));
				}
				return call_user_func_array(array($obj, $func), $args);
			}

		$debug = debug_backtrace();
		trigger_error(sprintf('Call to undefined function $easyMultilingual->%1$s() in %2$s on line %3$s' . "\nError handler", $func, $debug[0]['file'], $debug[0]['line']), E_USER_ERROR);
	}
}

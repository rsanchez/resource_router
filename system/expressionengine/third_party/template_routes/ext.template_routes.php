<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Template Routes Extension
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Rob Sanchez
 * @link		https://github.com/rsanchez
 */

class Template_routes_ext {
	
	public $settings 		= array();
	public $description		= 'Routes to templates';
	public $docs_url		= '';
	public $name			= 'Template Routes';
	public $settings_exist	= 'n';
	public $version			= '1.0.1';
	
	private $EE;
	
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	public function __construct($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;

		if ( ! $this->cache_path = $this->EE->config->slash_item('cache_path'))
		{
			$this->cache_path = APPPATH.'cache/';
		}

		$this->cache_path .= 'template_routes/';
	}// ----------------------------------------------------------------------
	
	/**
	 * Activate Extension
	 *
	 * This function enters the extension into the exp_extensions table
	 *
	 * @see http://codeigniter.com/user_guide/database/index.html for
	 * more information on the db class.
	 *
	 * @return void
	 */
	public function activate_extension()
	{
		// Setup custom settings in this array.
		$this->settings = array();

		if ( ! @mkdir($this->cache_path))
		{
			show_error('Could not create cache path: '.$this->cache_path);
		}

		$this->EE->load->helper('file');

		write_file($this->cache_path.'cached_routes', '');
		
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'core_template_route',
			'hook'		=> 'core_template_route',
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y',
			'priority'  => 1,
		);

		$this->EE->db->insert('extensions', $data);			
		
	}

	// ----------------------------------------------------------------------
	
	/**
	 * core_template_route
	 *
	 * @param string $uri_string
	 * @return array
	 */
	public function core_template_route($uri_string)
	{
		// get the routes array from the config file
		$routes = $this->EE->config->item('template_routes');

		$caching_enabled = $this->EE->config->item('template_routes_caching_enabled');

		// set all the {route_X} variables to blank by default
		for ($i = 0; $i <= 10; $i++)
		{
			$this->EE->config->_global_vars['route_'.$i] = '';
		}

		// normalize the uri_string
		$uri_string = rtrim($uri_string, '/');

		// get all the Pages/Structure URIs
		$site_pages = $this->EE->config->item('site_pages');

		$site_id = $this->EE->config->item('site_id');

		// check if this URI is a Pages URI
		$is_page = isset($site_pages[$site_id]['uris']) ? in_array('/'.$uri_string, $site_pages[$site_id]['uris']) : FALSE;
/*
		// set the {route_1} to the pages URI, which should be a common usage
		if ($is_page)
		{
			$this->EE->config->_global_vars['route_1'] = $uri_string;
		}
*/

		$route = FALSE;

		// ensure that this is not a Pages URI and that we have good routes
		if (is_array($routes))//if ($is_page === FALSE && is_array($routes))
		{
			if ($caching_enabled)
			{
				// get our route cache, so we don't have to go through a bunch of regexes
				$this->EE->load->helper('file');

				$config_hash = md5(json_encode($routes));

				$cache_file = $this->cache_path.md5($uri_string);

				if (file_exists($this->cache_path.'cached_routes'))
				{
					$cached_routes = file_get_contents($this->cache_path.'cached_routes');

					//routes have changed, clear the cache
					if ($cached_routes !== $config_hash)
					{
						delete_files($this->cache_path, TRUE);

						write_file($this->cache_path.'cached_routes', md5(json_encode($routes)));
					}

					if (file_exists($cache_file))
					{
						if ($cache = @file_get_contents($cache_file))
						{
							$route = @json_decode($cache, TRUE);
						}
					}
				}
			}

			// didn't find a cached route, go through the list of routes
			if ( ! $route)
			{
				$found_match = FALSE;

				// loop through all the defined routes and check if the uri_string is a match
				foreach($routes as $rule => $template)
				{
					// normalize the rule
					$rule = rtrim($rule, '/');

					// does the rule have any wildcards?
					$wildcard = strpos($rule, ':');

					// build the regex from the rule wildcard(s)
					if ($wildcard !== FALSE)
					{
						// check for a :page:XX wildcard
						if (preg_match('/\(?:page:(\d+)\)?/', $rule, $match) && isset($site_pages[$site_id]['uris'][$match[1]]))
						{
							$rule = str_replace($match[0], '('.ltrim($site_pages[$site_id]['uris'][$match[1]], '/').')', $rule);

							// don't count a page uri as wildcard
							$wildcard = strpos($rule, ':');
						}

						$regex = str_replace(
							array(
								'(:any)',
								':any',
								'(:num)',
								':num',
								'(:year)',
								':year',
								'(:month)',
								':month',
								'(:day)',
								':day',
								'(:category)',
								':category',
								'/(:pagination)',
								'/:pagination',
								'(:pagination)',
								':pagination',
								'/(:all)',
								'/:all',
							),
							array(
								'([^/]+)',
								'([^/]+)',
								'(\d+)',
								'(\d+)',
								'(\d{4})',
								'(\d{4})',
								'(\d{2})',
								'(\d{2})',
								'(\d{2})',
								'(\d{2})',
								preg_quote($this->EE->config->item('reserved_category_word')).'/'.($this->EE->config->item('use_category_name') === 'y' ? '([^/]+)' : '(\d+)'),
								preg_quote($this->EE->config->item('reserved_category_word')).'/'.($this->EE->config->item('use_category_name') === 'y' ? '([^/]+)' : '(\d+)'),
								'(/P\d+)?',
								'(/P\d+)?',
								'(/P\d+)?',
								'(/P\d+)?',
								'(/.*)?',
								'(/.*)?',
							),
							$rule
						);
					}
					else
					{
						$regex = $rule;
					}

					$regex = '#^'.trim($regex, '/').'$#';

					// check if the uri_string matches this route
					if (preg_match($regex, $uri_string, $matches))
					{
						$found_match = TRUE;

						$route['template'] = $template;
						//remove trailing/leading slashes from matches
						$route['matches'] = array_map(array($this, 'trim_slashes'), $matches);

						// check if it has wildcards
						if ($wildcard !== FALSE)
						{
							// the channel module uses this query_string property to do its dynamic stuff
							// normally gets set in Template::parse_template_uri(), but we are overriding that function here
							// let's grab the bits of the uri that are dynamic and set that as the query_string
							// e.g. blog/nested/here/:any => _blog/_view will yield a query_string of that final segment
							$route['query_string'] = preg_replace('#^'.preg_quote(str_replace(array('(', ')'), '', substr($rule, 0, $wildcard))).'#', '', $uri_string);
						}

						if ($caching_enabled)
						{
							write_file($cache_file, json_encode($route));
						}

						break;
					}
				}

				if ( ! $found_match)
				{
					$route = array('template' => '');

					if ($caching_enabled)
					{
						write_file($cache_file, json_encode($route));
					}
				}
			}
		}

		if ( ! empty($route['template']))
		{
			if (isset($route['query_string']))
			{
				$this->EE->uri->query_string = $route['query_string'];
			}

			// I want Structure's global variables set on urls that start with a pages URI
			// so we tell structure that the uri_string is the first match in the regex
			if ( ! $is_page && isset($route['matches'][1]) && isset($this->EE->extensions->OBJ['Structure_ext']) && isset($site_pages[$site_id]['uris']) && in_array('/'.$route['matches'][1], $site_pages[$site_id]['uris']))
			{
				$temp_uri_string = $this->EE->uri->uri_string;

				$this->EE->uri->uri_string = $route['matches'][1];

				$this->EE->extensions->OBJ['Structure_ext']->sessions_start($this->EE->session);

				$this->EE->uri->uri_string = $temp_uri_string;
			}

			// loop through the matched sub-strings
			foreach ($route['matches'] as $i => $match)
			{
				// set each sub-string as a global template variable
				$this->EE->config->_global_vars['route_'.$i] = $match;

				// replace any sub-string matches in the template definition
				$route['template'] = str_replace('$'.$i, $match, $route['template']);
			}

			// don't override the template, since it's a page URI
			if ( ! $is_page)
			{
				// prevent other extensions from messing with us
				$this->EE->extensions->end_script = TRUE;
				
				// set the route as array from the template string
				return explode('/', $route['template']);
			}
		}

		// set the default route to any other extension calling this hook
		return $this->EE->extensions->last_call;
	}

	public function trim_slashes($string)
	{
		return trim($string, '/');
	}

	// ----------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * This method removes information from the exp_extensions table
	 *
	 * @return void
	 */
	function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}

	// ----------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * This function performs any necessary db updates when the extension
	 * page is visited
	 *
	 * @return 	mixed	void on update / false if none
	 */
	function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
	}	
	
	// ----------------------------------------------------------------------
}

/* End of file ext.template_routes.php */
/* Location: /system/expressionengine/third_party/template_routes/ext.template_routes.php */
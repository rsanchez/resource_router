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
	public $version			= '1.0.0';
	
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
		
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'core_template_route',
			'hook'		=> 'core_template_route',
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y'
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

		// set the default route to any other extension calling this hook
		$route = $this->EE->extensions->last_call;

		// set all the {route_X} variables to blank by default
		for ($i = 0; $i <= 10; $i++)
		{
			$this->EE->config->_global_vars['route_'.$i] = '';
		}

		if (is_array($routes))
		{
			// loop through all the defined routes and check if the uri_string is a match
			foreach($routes as $rule => $template)
			{
				// check if the uri_string matches this route
				if (preg_match($this->rule_to_regex($rule), rtrim($uri_string, '/'), $matches))
				{
					// loop through the matched sub-strings
					foreach ($matches as $i => $match)
					{
						// set each sub-string as a global template variable
						$this->EE->config->_global_vars['route_'.$i] = $match;

						// replace any sub-string matches in the template definition
						$template = str_replace('$'.$i, $match, $template);
					}
					
					// set the route as array from the template string
					$route = explode('/', $template);

					break;
				}
			}
		}

		return $route;
	}

	/**
	 * convert a CI-style route definition into regex
	 * 
	 * Wildcards: :any, :num, :year, :month, :day, :pagination
	 * 
	 * @param string $rule a URI rule, eg. "products/base/:any"
	 */
	protected function rule_to_regex($rule)
	{
		$rule = str_replace(
			array(
				':any',
				':num',
				':year',
				':month',
				':day',
				'/(:pagination)',
				'/:pagination',
				'(:pagination)',
				':pagination',
			),
			array(
				'[^/]+',
				'\d+',
				'\d{4}',
				'\d{2}',
				'\d{2}',
				'(/P\d+)?',
				'(/P\d+)?',
				'(/P\d+)?',
				'(/P\d+)?',
			),
			$rule
		);

		return '#^'.trim($rule, '/').'$#';
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
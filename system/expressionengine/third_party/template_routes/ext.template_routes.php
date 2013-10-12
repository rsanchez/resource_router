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
		$this->EE->load->add_package_path(PATH_THIRD.'template_routes/');

		$this->EE->load->library('template_router');

		$this->EE->load->remove_package_path(PATH_THIRD.'template_routes/');

		$this->EE->template_router->run($uri_string);

		if ($this->EE->template_router->template && ! $this->EE->template_router->is_page)
		{
			// prevent other extensions from messing with us
			$this->EE->extensions->end_script = TRUE;
			
			// set the route as array from the template string
			return explode('/', $this->EE->template_router->template);
		}

		// set the default route to any other extension calling this hook
		return $this->EE->extensions->last_call;
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
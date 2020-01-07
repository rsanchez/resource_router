<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Resource Router Extension
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Rob Sanchez
 * @link		https://github.com/rsanchez
 */

class Resource_router_ext {

	public $settings 		= array();
	public $description		= 'Map URI patterns to resources';
	public $docs_url		= '';
	public $name			= 'Resource Router';
	public $settings_exist	= 'n';
	public $version			= '1.2.0';

	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	public function __construct($settings = '')
	{
		$this->settings = $settings;
	}

	// ----------------------------------------------------------------------

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
			'enabled'	=> 'y',
			'priority'  => 1,
		);

		ee()->db->insert('extensions', $data);
		
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'publish_live_preview_route',
			'hook'		=> 'publish_live_preview_route',
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y',
			'priority'  => 1,
		);

		ee()->db->insert('extensions', $data);

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
		//since EE2 doesn't have an autoloader
		require_once PATH_THIRD.'resource_router/libraries/ResourceRouter/Router.php';
		require_once PATH_THIRD.'resource_router/libraries/ResourceRouter/Wildcard.php';

		$router = new \rsanchez\ResourceRouter\Router($uri_string);

		if ($router->isRoutable())
		{
			// set the route as array from the template string
			return $router->template();
		}

		// set the default route to any other extension calling this hook
		return ee()->extensions->last_call;
	}

	// ----------------------------------------------------------------------

	/**
	 * publish_live_preview_route
	 *
	 * @param array  $post_data
	 * @param string $uri
	 * @param int    $template_id
	 * @return array
	 */
	public function publish_live_preview_route(array $post_data, $uri, $template_id)
	{
		//since EE2 doesn't have an autoloader
		require_once PATH_THIRD.'resource_router/libraries/ResourceRouter/Router.php';
		require_once PATH_THIRD.'resource_router/libraries/ResourceRouter/Wildcard.php';

		$router = new \rsanchez\ResourceRouter\Router($uri);

		if ($router->isRoutable())
		{
			// get the route as array from the template string
			$route = $router->template();
			
			$template = ee('Model')->get('Template')
				->with('TemplateGroup')
				->filter('TemplateGroup.group_name', $route[0])
				->filter('template_name', $route[1])
				->first();
			
			if ($template) {
				return array(
					'uri'		=> $uri,
					'template_id'	=> $template->template_id,
				);
			}
		}

		// set the default route to any other extension calling this hook
		return ee()->extensions->last_call;
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
		ee()->db->where('class', __CLASS__);
		ee()->db->delete('extensions');
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

		if (version_compare($current, '1.2.0', '<')) {
			$data = array(
				'class'		=> __CLASS__,
				'method'	=> 'publish_live_preview_route',
				'hook'		=> 'publish_live_preview_route',
				'settings'	=> serialize($this->settings),
				'version'	=> $this->version,
				'enabled'	=> 'y',
				'priority'  => 1,
			);

			ee()->db->insert('extensions', $data);
		}
	}

	// ----------------------------------------------------------------------
}

/* End of file ext.resource_router.php */
/* Location: /system/expressionengine/third_party/resource_router/ext.resource_router.php */

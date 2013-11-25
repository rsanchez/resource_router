<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Template_routes
{
	public function __construct()
	{
		ee()->load->add_package_path(PATH_THIRD.'template_routes/');

		ee()->load->library('template_router');

		ee()->load->remove_package_path(PATH_THIRD.'template_routes/');
	}

	public function __call($name, $args)
	{
		if ( ! isset(ee()->template_router->variables[$name]))
		{
			return ee()->TMPL->no_results();
		}

		$data = ee()->template_router->variables[$name];

		if (is_array($data))
		{
			if ( ! $data)
			{
				return ee()->TMPL->no_results();
			}

			$method = 'parse_variables';

			//is it associative?
			foreach (array_keys($data) as $key => $val)
			{
				if ($key !== $val)
				{
					$method = 'parse_variables_row';
					break;
				}
			}

			return ee()->TMPL->$method(ee()->TMPL->tagdata, $data);
		}

		return $data;
	}
}
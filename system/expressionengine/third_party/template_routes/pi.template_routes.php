<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Template_routes
{
	public function __construct()
	{
		$this->EE =& get_instance();

		$this->EE->load->add_package_path(PATH_THIRD.'template_routes/');

		$this->EE->load->library('template_router');

		$this->EE->load->remove_package_path(PATH_THIRD.'template_routes/');
	}

	public function __call($name, $args)
	{
		if ( ! isset($this->EE->template_router->variables[$name]))
		{
			return $this->EE->TMPL->no_results();
		}

		$data = $this->EE->template_router->variables[$name];

		if (is_array($data))
		{
			if ( ! $data)
			{
				return $this->EE->TMPL->no_results();
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

			return $this->EE->TMPL->$method($this->EE->TMPL->tagdata, $data);
		}

		return $data;
	}
}
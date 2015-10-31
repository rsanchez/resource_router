<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Resource Router Plugin
 *
 * @package   ExpressionEngine
 * @subpackage  Addons
 * @category  Plugin
 * @author    Rob Sanchez
 * @link    https://github.com/rsanchez
 */

$plugin_info = array(
	'pi_name'   => 'resource_router',
	'pi_version'  => '1.1.1',
	'pi_author'   => 'Rob Sanchez',
	'pi_author_url' => 'https://github.com/rsanchez',
	'pi_description'=> 'Tags for Resource Router variables',
	'pi_usage'    => '{exp:resource_router:foo}{bar}-{baz}{/exp:resource_router:foo}'
);

class Resource_router
{
	public function __call($name, $args)
	{
		$data = ee()->session->cache('resource_router', $name);

		if ( ! $data)
		{
			return ee()->TMPL->no_results();
		}

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

/* End of file pi.resource_router.php */
/* Location: /system/expressionengine/third_party/resource_router/pi.resource_router.php */
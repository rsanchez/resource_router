<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Template_router {

	/**
	 * List of routes
	 *
	 * 'this/is/a/uri/:any' => 'template_group/template_name',
	 * 
	 * @var array
	 */
	protected $routes = array();

	/**
	 * List of site Page URIs
	 * @var array
	 */
	protected $uris = array();

	/**
	 * HTTP Status code
	 * @var integer
	 */
	protected $status = 200;

	/**
	 * Template variables
	 * @var array
	 */
	protected $variables = array();

	/**
	 * Is the matched URI a page URI?
	 * @var boolean
	 */
	protected $page = FALSE;

	/**
	 * The template to load
	 * @var null|string
	 */
	protected $template = NULL;

	/**
	 * List of matched wildcards
	 * @var array
	 */
	protected $wildcards = array();

	public function __construct()
	{
		// get the routes array from the config file
		$routes = ee()->config->item('template_routes');

		if (is_array($routes))
		{
			$this->routes = $routes;
		}

		// get all the Pages/Structure URIs
		$site_pages = ee()->config->item('site_pages');

		$site_id = ee()->config->item('site_id');

		if (isset($site_pages[$site_id]['uris']))
		{
			$this->uris = $site_pages[$site_id]['uris'];
		}

		ee()->load->helper(array('file', 'string'));
	}

	/**
	 * Does this route match a page URI?
	 * 
	 * @return boolean
	 */
	public function isPage()
	{
		return $this->page;
	}

	/**
	 * Check if the given category is valid
	 *
	 * if ($router->isValidCategory(array(
	 * 	 'cat_url_title' => $router->wildcard(1),
	 * 	 'channel' => 'blog',
	 * ))
	 * {
	 *   $router->setTemplate('blog/category');
	 * }
	 * 
	 * @param  array   $where  where / where_in provided to CodeIgniter Active Record class
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean|mixed is this a valid category|the value of the $return column, if specified
	 */
	public function isValidCategory($where, $return = NULL)
	{
		$joined = FALSE;

		if (isset($where['channel']) || isset($where['channel_id']))
		{
			if (isset($where['channel']))
			{
				$channel = is_array($where['channel']) ? $where['channel'] : array($where['channel']);

				ee()->db->where_in('channel_name', $channel);
			}

			if (isset($where['channel_id']))
			{
				$channel_id = is_array($where['channel_id']) ? $where['channel_id'] : array($where['channel_id']);

				ee()->db->where_in('channel_id', $channel_id);
			}

			ee()->db->select('cat_group');

			$query = ee()->db->get('channels');

			if ($query->num_rows() > 0 && ! isset($where['group_id']))
			{
				$where['group_id'] = array();
			}

			foreach ($query->result() as $row)
			{
				foreach (explode('|', $row->cat_group) as $group_id)
				{
					$where['group_id'][] = $group_id;
				}
			}

			unset($where['channel'], $where['channel_id']);			
		}

		foreach ($where as $key => $value)
		{
			if ($joined === FALSE && strncmp($key, 'field_id_', 9) === 0)
			{
				ee()->db->join('category_field_data', 'category_field_data.cat_id = categories.cat_id');

				$joined = TRUE;
			}

			if (is_array($value))
			{
				ee()->db->where_in($key, $value);
			}
			else
			{
				ee()->db->where($key, $value);
			}
		}

		ee()->db->from('categories');

		if ($return)
		{
			$query = ee()->db->get();

			if ($query->num_rows() > 0)
			{
				$return = $query->row($return);
			}
			else
			{
				$return = FALSE;
			}

			$query->free_result();

			return $return;
		}

		return ee()->db->count_all_results() > 0;
	}

	/**
	 * Check if the given category ID is valid
	 *
	 * if ($router->isValidCategoryId($router->wildcard(1)))
	 * {
	 *   $router->setTemplate('blog/category');
	 * }
	 * 
	 * @param  string|int  $cat_id a category id
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean is this a valid category
	 */
	public function isValidCategoryId($cat_id, $return = NULL)
	{
		return $this->isValidCategory(array('cat_id' => $cat_id), $return);
	}

	/**
	 * Check if the given category url title is valid
	 *
	 * if ($router->isValidCategoryUrlTitle($router->wildcard(1)))
	 * {
	 *   $router->setTemplate('blog/category');
	 * }
	 * 
	 * @param  string  $cat_url_title a category url title
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean is this a valid category
	 */
	public function isValidCategoryUrlTitle($cat_url_title, $return = NULL)
	{
		return $this->isValidCategory(array('cat_url_title' => $cat_url_title), $return);
	}

	/**
	 * Check if the given entry is valid
	 *
	 * if ($router->isValidEntry(array(
	 * 	 'url_title' => $router->wildcard(1),
	 * 	 'channel' => 'blog',
	 * 	 'status' => 'open',
	 * ))
	 * {
	 *   $router->setTemplate('blog/detail');
	 * }
	 * 
	 * @param  array   $where  where / where_in provided to CodeIgniter Active Record class
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean is this a valid entry
	 */
	public function isValidEntry($where, $return = NULL)
	{
		$joined_data = FALSE;
		$joined_channel = FALSE;

		foreach ($where as $key => $value)
		{
			if ($joined_data === FALSE && strncmp($key, 'field_id_', 9) === 0)
			{
				ee()->db->join('channel_data', 'channel_data.entry_id = channel_titles.entry_id');

				$joined_data = TRUE;
			}

			if ($key === 'channel' || $key === 'channel_name')
			{
				if ($joined_channel === FALSE)
				{
					ee()->db->join('channels', 'channels.channel_id = channel_titles.channel_id');

					$joined_channel = TRUE;
				}

				$key = 'channel_name';
			}

			if (is_array($value))
			{
				ee()->db->where_in($key, $value);
			}
			else
			{
				ee()->db->where($key, $value);
			}
		}

		ee()->db->from('channel_titles');

		if ($return)
		{
			$query = ee()->db->get();

			if ($query->num_rows() > 0)
			{
				$return = $query->row($return);
			}
			else
			{
				$return = FALSE;
			}

			$query->free_result();

			return $return;
		}

		return ee()->db->count_all_results() > 0;
	}

	/**
	 * Check if the given entry id is valid
	 *
	 * if ($router->isValidEntryId($router->wildcard(1)))
	 * {
	 *   $router->setTemplate('blog/detail');
	 * }
	 * 
	 * @param  string  $entry_id
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean is this a valid entry
	 */
	public function isValidEntryId($entry_id, $return = NULL)
	{
		return $this->isValidEntry(array('entry_id' => $entry_id), $return);
	}

	/**
	 * Check if the given member is valid
	 *
	 * if ($router->isValidMember(array(
	 * 	 'username' => $router->wildcard(1),
	 * 	 'group_id' => 6,
	 * ))
	 * {
	 *   $router->setTemplate('users/detail');
	 * }
	 * 
	 * @param  array   $where  where / where_in provided to CodeIgniter Active Record class
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean is this a valid member
	 */
	public function isValidMember($where, $return = NULL)
	{
		foreach ($where as $key => $value)
		{
			if (is_array($value))
			{
				ee()->db->where_in($key, $value);
			}
			else
			{
				ee()->db->where($key, $value);
			}
		}

		ee()->db->from('members');

		if ($return)
		{
			$query = ee()->db->get();

			if ($query->num_rows() > 0)
			{
				$return = $query->row($return);
			}
			else
			{
				$return = FALSE;
			}

			$query->free_result();

			return $return;
		}

		return ee()->db->count_all_results() > 0;
	}

	/**
	 * Check if the given member_id is valid
	 *
	 * if ($router->isValidMemberId($router->wildcard(1)))
	 * {
	 *   $router->setTemplate('users/detail');
	 * }
	 * 
	 * @param  int  $member_id
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean is this a valid member
	 */
	public function isValidMemberId($member_id, $return = NULL)
	{
		return $this->isValidMember(array('member_id' => $member_id), $return);
	}

	/**
	 * Check if the given url title is valid
	 *
	 * if ($router->isValidUrlTitle($router->wildcard(1)))
	 * {
	 *   $router->setTemplate('blog/detail');
	 * }
	 * 
	 * @param  string  $url_title an entry url title
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean is this a valid entry
	 */
	public function isValidUrlTitle($url_title, $return = NULL)
	{
		return $this->isValidEntry(array('url_title' => $url_title), $return);
	}

	/**
	 * Check if the given username is valid
	 *
	 * if ($router->isValidUsername($router->wildcard(1)))
	 * {
	 *   $router->setTemplate('users/detail');
	 * }
	 * 
	 * @param  string  $username
	 * @param  null|string $return  a column to return or NULL if you wish to return bool
	 * @return boolean is this a valid member
	 */
	public function isValidUsername($username, $return = NULL)
	{
		return $this->isValidMember(array('username' => $username), $return);
	}

	/**
	 * Send this data as a JSON response
	 *
	 * return $router->json(array('foo' => 'bar'));
	 * 
	 * @param  mixed $data
	 * @return void
	 */
	public function json($data)
	{
		$this->setOutputType('json');

		return $this->output(json_encode($data));
	}

	/**
	 * Get the matched wildcards
	 * 
	 * @return array
	 */
	public function wildcards()
	{
		return $this->wildcards;
	}

	/**
	 * Get the specified matched wildcard
	 * 
	 * @param  int $which the wildcard index
	 * @return mixed|null null if doesn't exist
	 */
	public function wildcard($which)
	{
		return array_key_exists($which, $this->wildcards) ? $this->wildcards[$which] : NULL;
	}

	/**
	 * Output a string that will be output as the body content for this router endpoint
	 *
	 * $router->output('Hello world!');
	 * 
	 * @param string $output
	 */
	public function output($output)
	{
		ee()->output->final_output = $output;

		$output_type = ee()->output->out_type;

		ee()->output->set_status_header($this->status);

		$override_types = array('webpage', 'css', 'js', 'xml', 'json');

		// dont send those weird pragma no-cache headers
		if (in_array($output_type, $override_types))
		{
			switch ($output_type)
			{
				case 'json':
					$this->setContentType('application/json');
					ee()->output->enable_profiler = FALSE;
					break;
				case 'webpage':
					$this->setContentType('text/html; charset='.ee()->config->item('charset'));
					break;
				case 'css':
					$this->setContentType('text/css');
					break;
				case 'js':
					$this->setContentType('text/javascript');
					ee()->output->enable_profiler = FALSE;
					break;
				case 'xml':
					$this->setContentType('text/xml');
					ee()->output->final_output = trim(ee()->output->final_output);
					break;
			}

			ee()->output->out_type = 'cp_asset';
		}

		// Start from CodeIgniter.php
		ee()->benchmark->mark('controller_execution_time_( EE / index )_end');

		ee()->hooks->_call_hook('post_controller');

		if (ee()->hooks->_call_hook('display_override') === FALSE)
		{
			ee()->output->_display();
		}
		
		ee()->hooks->_call_hook('post_system');

		if (class_exists('CI_DB') AND isset(ee()->db))
		{
			ee()->db->close();
		}
		// End from CodeIgniter.php

		exit;
	}

	/**
	 * Run the router against a uri_string
	 * 
	 * @param  string $uri_string
	 * @return this
	 */
	public function run($uri_string)
	{
		// set all the {route_X} variables to blank by default
		for ($i = 0; $i <= 10; $i++)
		{
			$this->setGlobal('route_'.$i);
		}

		// normalize the uri_string
		$uri_string = rtrim($uri_string, '/');

		// start with an empty query_string
		$query_string = '';

		// check if this URI is a Pages URI
		$this->page = in_array('/'.$uri_string, $this->uris);

		$found_match = FALSE;

		// loop through all the defined routes and check if the uri_string is a match
		foreach($this->routes as $rule => $template)
		{
			// normalize the rule
			$rule = rtrim($rule, '/');

			// does the rule have any wildcards?
			$wildcard = strpos($rule, ':');

			// build the regex from the rule wildcard(s)
			if ($wildcard !== FALSE)
			{
				// check for a :page:XX wildcard
				if (preg_match('/\(?:page:(\d+)\)?/', $rule, $match) && isset($this->uris[$match[1]]))
				{
					$rule = str_replace($match[0], '('.ltrim($this->uris[$match[1]], '/').')', $rule);

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
						preg_quote(ee()->config->item('reserved_category_word')).'/'.(ee()->config->item('use_category_name') === 'y' ? '([^/]+)' : '(\d+)'),
						preg_quote(ee()->config->item('reserved_category_word')).'/'.(ee()->config->item('use_category_name') === 'y' ? '([^/]+)' : '(\d+)'),
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
			if (preg_match($regex, $uri_string, $this->wildcards))
			{
				//remove trailing/leading slashes from matches
				$this->wildcards = array_map('trim_slashes', $this->wildcards);

				if (is_callable($template))
				{
					$output = call_user_func($template, $this);

					if ($output && is_string($output))
					{
						return $this->output($output);
					}
				}

				if ($this->template)
				{
					// check if it has wildcards
					if ($wildcard !== FALSE)
					{
						// the channel module uses this query_string property to do its dynamic stuff
						// normally gets set in Template::parse_template_uri(), but we are overriding that function here
						// let's grab the bits of the uri that are dynamic and set that as the query_string
						// e.g. blog/nested/here/:any => _blog/_view will yield a query_string of that final segment
						$query_string = preg_replace('#^'.preg_quote(str_replace(array('(', ')'), '', substr($rule, 0, $wildcard))).'#', '', $uri_string);
					}

					break;
				}
			}
		}

		if ($this->template)
		{
			if ($query_string)
			{
				ee()->uri->query_string = $query_string;
			}

			// I want Structure's global variables set on urls that start with a pages URI
			// so we tell structure that the uri_string is the first match in the regex
			if ( ! $this->page && isset($this->wildcards[1]) && isset(ee()->extensions->OBJ['Structure_ext']) && in_array('/'.$this->wildcards[1], $this->uris))
			{
				$temp_uri_string = ee()->uri->uri_string;

				ee()->uri->uri_string = $this->wildcards[1];

				ee()->extensions->OBJ['Structure_ext']->sessions_start(ee()->session);

				ee()->uri->uri_string = $temp_uri_string;
			}

			// loop through the matched sub-strings
			foreach ($this->wildcards as $i => $wildcard)
			{
				// set each sub-string as a global template variable
				$this->setGlobal('route_'.$i, $wildcard);

				// replace any sub-string matches in the template definition
				$this->template = str_replace('$'.$i, $wildcard, $this->template);
			}
		}

		return $this;
	}

	/**
	 * Trigger a 404 using the built-in EE 404 template
	 *
	 * @return this
	 */
	public function set404()
	{
		//all the conditions to trigger a 404 in the TMPL class
		$hidden_indicator = ee()->config->item('hidden_template_indicator') === FALSE ? '.' : ee()->config->item('hidden_template_indicator');
		
		ee()->uri->page_query_string = '';
		
		ee()->config->set_item('hidden_template_404', 'y');

		$this->template = '/'.$hidden_indicator;
	}

	/**
	 * Set the HTTP Content-Type header
	 *
	 * $router->setContentType('application/json');
	 * 
	 * @param string $content_type
	 * @return this
	 */
	public function setContentType($content_type)
	{
		$this->setHeader('Content-Type', $content_type);

		return $this;
	}

	/**
	 * Set a global template variable
	 *
	 * $router->setGlobal
	 * 
	 * @param string $key   the variable name
	 * @param string|bool|int $value
	 * @return this
	 */
	public function setGlobal($key, $value = '')
	{
		ee()->config->_global_vars[$key] = $value;

		return $this;
	}

	/**
	 * Set an HTTP header
	 *
	 * $router->setHeader('Content-Type: application/json');
	 * $router->setHeader('Content-Type', 'application/json');
	 * 
	 * @param string $header the full header string if using one parameter, the header name if using two parameters
	 * @param string $content [optional] the header content if using two parameters
	 * @return this
	 */
	public function setHeader($header, $content = NULL)
	{
		if (func_num_args() === 1)
		{
			ee()->output->set_header($header);
		}
		else
		{
			ee()->output->set_header(sprintf('%s: %s', $header, func_get_arg(1)));
		}

		return $this;
	}

	/**
	 * Set an HTTP status code
	 *
	 * $router->setHttpStatus(401);
	 * 
	 * @param int $code a valid HTTP status code
	 * @return this
	 */
	public function setHttpStatus($code)
	{
		$this->status = $code;

		return $this;
	}

	/**
	 * Set the EE output class type
	 *
	 * $router->setOutputType('css');
	 * 
	 * @param string $type one of the following: webpage, css, js, json, xml, feed, 404
	 * @return this
	 */
	public function setOutputType($type)
	{
		ee()->output->out_type = $type;

		return $this;
	}

	/**
	 * Set the template to use for this router endpoint
	 *
	 * $router->setTemplate('foo/bar');
	 * 
	 * @param string $template a template_group/template_name pair
	 * @return this
	 */
	public function setTemplate($template)
	{
		$this->template = $template;

		return $this;
	}

	/**
	 * Set a template single or pair variable for use with the {exp:template_routes} plugin
	 *
	 * $router->setVariable('foo', 'bar');
	 *
	 * {exp:template_routes:foo} -> bar
	 *
	 * $router->setVariable('foo', array('bar' => 1, 'baz' => 2));
	 *
	 * {exp:template_routes:foo}{bar}-{baz}{/exp:template_routes:foo} -> 1-2
	 *
	 * $router->setVariable('foo', array(array('bar' => 1, 'baz' => 2), array('bar' => 3, 'baz' => 4)));
	 *
	 * {exp:template_routes:foo}{bar}-{baz}|{/exp:template_routes:foo} -> 1-2|3-4
	 * 
	 * @param string $name the key or identifier of the variable
	 * @param string|array $data an array for a tag pair or a single value
	 * @return this
	 */
	public function setVariable($name, $data)
	{
		$this->variables[$name] = $data;

		return $this;
	}

	/**
	 * Get the set template
	 * 
	 * @return mixed
	 */
	public function template()
	{
		return $this->template;
	}

	/**
	 * Get the specified set variable
	 * 
	 * @param  string $which the variable key
	 * @return mixed|null null if doesn't exist
	 */
	public function variable($which)
	{
		return array_key_exists($which, $this->variables) ? $this->variables[$which] : NULL;
	}

	/**
	 * Get all set variables
	 * 
	 * @return array
	 */
	public function variables()
	{
		return $this->variables;
	}
}

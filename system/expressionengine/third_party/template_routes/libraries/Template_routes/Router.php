<?php

namespace Template_routes;

use \Template_routes\Wildcard;

class Router {

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
	protected $pageUris = array();

	/**
	 * HTTP Status code
	 * @var integer
	 */
	protected $httpStatus = 200;

	/**
	 * Is the matched URI a page URI?
	 * @var boolean
	 */
	protected $isPage = FALSE;

	/**
	 * The template to load
	 * @var null|string
	 */
	protected $template;

	/**
	 * List of matched wildcards
	 * @var array
	 */
	protected $wildcards = array();

	/**
	 * Constructor
	 * 
	 * @param  string $uri_string
	 * @return void
	 */
	public function __construct($uri_string)
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
			$this->pageUris = $site_pages[$site_id]['uris'];
		}

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
		$this->isPage = in_array('/'.$uri_string, $this->pageUris);

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
				if (preg_match('/\(?:page:(\d+)\)?/', $rule, $match) && isset($this->pageUris[$match[1]]))
				{
					$rule = str_replace($match[0], '('.ltrim($this->pageUris[$match[1]], '/').')', $rule);

					// don't count a page uri as wildcard
					$wildcard = strpos($rule, ':');
				}

				// find all the wildcards and parenthesized regexes
				$wildcardsByType = array();

				if (preg_match_all('/(:(any|num|year|month|day|category_url_title|category_id|category|pagination|all|url_title|entry_id|member_id|username)|\(.*?\))/', $rule, $matches))
				{
					// store the type of each wildcard so we can specify it later
					foreach ($matches[0] as $i => $match)
					{
						$wildcardsByType[$i + 1] = $match[0] === ':' ? substr($match, 1) : NULL;
					}
				}

				$regex = str_replace(
					array(
						':any',
						':num',
						':year',
						':month',
						':day',
						'/:pagination',
						':pagination',
						'/:all',
						':entry_id',
						':url_title',
						':category_id',
						':category_url_title',
						':member_id',
						':username',
						':category',
					),
					array(
						'([^/]+)',
						'(\d+)',
						'(\d{4})',
						'(\d{2})',
						'(\d{2})',
						'(/P\d+)?',
						'(/P\d+)?',
						'(/.*)?',
						'(\d+)',
						'([^/]+)',
						'(\d+)',
						'([^/]+)',
						'(\d+)',
						'([^/]+)',
						preg_quote(ee()->config->item('reserved_category_word')).'/'.(ee()->config->item('use_category_name') === 'y' ? '([^/]+)' : '(\d+)'),
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
			if (preg_match($regex, $uri_string, $match))
			{
				array_shift($match);

				foreach ($match as $i => $segment)
				{
					$index = $i + 1;

					$type = isset($wildcardsByType[$index]) ? $wildcardsByType[$index] : NULL;

					$this->wildcards[$index] = new Wildcard($this, $index, trim($segment, '/'), $type);
					
					//if it wasn't a callback (where it's assumed you ran your own validation),
					//validate all the wildcards and bail if it fails
					if ( ! is_callable($template) && ! $this->wildcards[$index]->isValid())
					{
						$this->template = null;

						continue;
					}
				}

				if (is_string($template))
				{
					$this->setTemplate($template);
				}

				if (is_callable($template))
				{
					$args = $this->wildcards;

					array_unshift($args, $this);

					$output = call_user_func_array($template, $args);

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
			if ( ! $this->isPage && isset($this->wildcards[1]) && isset(ee()->extensions->OBJ['Structure_ext']) && in_array('/'.$this->wildcards[1], $this->pageUris))
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
				$this->setGlobal('route_'.$i, (string) $wildcard);

				// replace any sub-string matches in the template definition
				$this->template = str_replace('$'.$i, $wildcard, $this->template);
			}
		}
	}

	/**
	 * Does this route match a page URI?
	 * 
	 * @return boolean
	 */
	public function isPage()
	{
		return $this->isPage;
	}

	public function redirect($url, $statusCode = 301)
	{
		// if it doesn't start with:
		// a) a slash
		// b) a dot
		// c) a valid protocol (eg. http://)
		// 
		// assume it's a EE template url
		if ( ! preg_match('#^(/|\.|[a-z]+://)#', $url))
		{
			$url = ee()->functions->create_url($url);
		}

		$this->setHeader('Location: '.$url);

		$this->setHttpStatus($statusCode);

		$this->output();
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
	public function output($output = '')
	{
		ee()->output->final_output = $output;

		$output_type = ee()->output->out_type;

		ee()->output->set_status_header($this->httpStatus);

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
		$this->httpStatus = $code;

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
		ee()->session->set_cache('template_routes', $name, $data);

		return $this;
	}

	/**
	 * Set a wildcard variable
	 *
	 * $router->setWildcard(1, 'foo');
	 *
	 * @param  int $which from 1 -> 10
	 * @param  string $value the wildcard variable value
	 * @return this
	 */
	public function setWildcard($which, $value)
	{
		if (isset($this->wildcards[$which]))
		{
			$this->wildcards[$which]->value = $value;
		}

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
	 * @return mixed|false false if doesn't exist
	 */
	public function variable($which)
	{
		return ee()->session->cache('template_routes', $which);
	}
}

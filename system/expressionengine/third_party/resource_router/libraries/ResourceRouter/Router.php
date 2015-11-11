<?php

namespace rsanchez\ResourceRouter;

use rsanchez\ResourceRouter\Wildcard;

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
	 * The request URI
	 * @var string
	 */
	protected $uriString;

	/**
	 * Is the matched URI a page URI?
	 * @var boolean
	 */
	protected $isPage = FALSE;

	/**
	 * The template group
	 * @var null|string
	 */
	protected $templateGroup;

	/**
	 * The template name
	 * @var null|string
	 */
	protected $templateName;

	/**
	 * List of matched wildcards
	 * @var array
	 */
	protected $wildcards = array();

	/**
	 * Content-Type header
	 * @var string
	 */
	protected $contentType;

	/**
	 * Constructor
	 * 
	 * @param  string $uri_string
	 * @return void
	 */
	public function __construct($uri_string)
	{
		if ($package_path = ee()->config->item('resource_router:package_path'))
		{
			ee()->load->add_package_path($package_path);
		}

		// get the routes array from the config file
		$routes = ee()->config->item('resource_router');

		// in case anyone tries to serialize ee()->config
		unset(ee()->config->config['resource_router'], ee()->config->default_ini['resource_router']);

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
		$this->uriString = rtrim($uri_string, '/');

		// start with an empty query_string
		$query_string = '';

		// check if this URI is a Pages URI
		$this->isPage = in_array('/'.$this->uriString, $this->pageUris);

		$found_match = FALSE;

		if (isset($this->routes[':before']))
		{
			if (is_callable($this->routes[':before']))
			{
				call_user_func($this->routes[':before'], $this);
			}

			unset($this->routes[':before']);
		}

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
					$rule = str_replace($match[0], '('.trim($this->pageUris[$match[1]], '/').')', $rule);

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
						'((?:/P\d+)?)',
						'((?:/P\d+)?)',
						'((?:/.*)?)',
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
			if (preg_match($regex, $this->uriString, $match))
			{
				array_shift($match);

				foreach ($match as $i => $segment)
				{
					$index = $i + 1;

					$type = isset($wildcardsByType[$index]) ? $wildcardsByType[$index] : NULL;

					$segment = trim($segment, '/');

					if ($segment === '')
					{
						$segment = NULL;
					}

					if ($type === 'all')
					{
						$segs = explode('/', rtrim($segment, '/'));

						$index--;

						foreach ($segs as $j => $seg)
						{
							$index++;

							$this->wildcards[$index] = new Wildcard($this, $index, $seg, 'any');
						}
					}
					else
					{
						$this->wildcards[$index] = new Wildcard($this, $index, $segment, $type);
					}

					//if it wasn't a callback (where it's assumed you ran your own validation),
					//validate all the wildcards and bail if it fails
					if ( ! is_callable($template) && ! $this->wildcards[$index]->isValid())
					{
						$template = null;

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

				if ($this->hasTemplate())
				{
					// check if it has wildcards
					if ($wildcard !== FALSE)
					{
						// the channel module uses this query_string property to do its dynamic stuff
						// normally gets set in Template::parse_template_uri(), but we are overriding that function here
						// let's grab the bits of the uri that are dynamic and set that as the query_string
						// e.g. blog/nested/here/:any => _blog/_view will yield a query_string of that final segment
						$query_string = preg_replace('#^'.preg_quote(str_replace(array('(', ')'), '', substr($rule, 0, $wildcard))).'#', '', $this->uriString);
					}

					break;
				}
			}
		}

		if ($this->hasTemplate())
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
				$this->templateName = str_replace('$'.$i, $wildcard, $this->templateName);
				$this->templateGroup = str_replace('$'.$i, $wildcard, $this->templateGroup);
			}
		}
	}

	/**
	 * Set uriString property
	 *
	 * @param string $uriString the new URI
	 * @return void
	 */
	public function setUri($uriString)
	{
		$this->uriString = rtrim($uriString, '/');
	}

	/**
	 * Get uriString property
	 *
	 * @return string
	 */
	public function getUri()
	{
		return $this->uriString;
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

	/**
	 * Redirect to the specified url or path
	 * @param  string $url
	 * @param  int    $statusCode
	 * @return void
	 */
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

		ee()->functions->redirect($url, false, $statusCode);
	}

	/**
	 * Load a CI view
	 * @param  string $view name of the view file (sans file extension)
	 * @param  array  $variables
	 * @return string
	 */
	public function view($view, $variables = array())
	{
		return ee()->load->view($view, $variables, TRUE);
	}

	/**
	 * Send this data as a JSON response
	 *
	 * return $router->json(array('foo' => 'bar'));
	 * 
	 * @param  mixed $data
	 * @return void
	 */
	public function json($data, $options = 0)
	{
		if (is_object($data) && ! $data instanceof \JsonSerializable)
		{
			if (method_exists($data, 'toJson'))
			{
				$output = $data->toJson($options);
			}
			elseif (method_exists($data, 'toArray'))
			{
				$output = json_encode($data->toArray(), $options);
			}
			else
			{
				$output = json_encode($data, $options);
			}
		}
		else
		{
			$output = json_encode($data, $options);
		}

		return $this->quit(200, $output, array(
			'Content-Type' => 'application/json',
		));
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

		$output_type = $this->contentType ? 'custom' : ee()->output->out_type;

		$override_types = array('webpage', 'css', 'js', 'xml', 'json', 'custom');

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

		if (version_compare(APP_VER, '3', '<'))
		{
			return $this->legacyDisplay();
		}

		ee()->output->_display();

		exit;
	}

	/**
	 * Display output in EE2
	 * @return void
	 */
	protected function legacyDisplay()
	{
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
		if (version_compare(APP_VER, '3', '<'))
		{
			return $this->legacy404();
		}

        ee()->load->library('template', NULL, 'TMPL');

        ee()->TMPL->show_404();

        return $this;
	}

	/**
	 * Trigger a 404 in EE2
	 * @return this
	 */
	public function legacy404()
	{
		//all the conditions to trigger a 404 in the TMPL class
		$hidden_template_indicator = ee()->config->item('hidden_template_indicator') ?: '.';
		
		ee()->uri->page_query_string = '';
		
		ee()->config->set_item('hidden_template_404', 'y');

		$this->templateGroup = $hidden_template_indicator;
		$this->templateName = $hidden_template_indicator;

		return $this;
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
		if ($value instanceof Wildcard) {
			$value = (string) $value;
		}

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

			if (strncmp($header, 'Content-Type: ', 14) === 0)
			{
				$this->contentType = substr($header, 14);
			}
		}
		else
		{
			ee()->output->set_header(sprintf('%s: %s', $header, $content));

			if ($header === 'Content-Type')
			{
				$this->contentType = $content;
			}
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
		// if you don't do this, EE_Output will override with a 200 status
		ee()->config->set_item('send_headers', FALSE);

		ee()->output->set_status_header($code);

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
		if ( ! is_array($template))
		{
			$template = explode('/', trim($template, '/'));
		}

		$this->templateGroup = $template[0];
		$this->templateName = isset($template[1]) ? $template[1] : 'index';

		$default_hidden_template_indicator = version_compare(APP_VER, '2.9.0', '<') ? '.' : '_';

		$hidden_template_indicator = ee()->config->item('hidden_template_indicator') ?: $default_hidden_template_indicator;

		//allow you to set hidden templates
		if (isset($this->templateName[0]) && $this->templateName[0] === $hidden_template_indicator)
		{
			ee()->config->set_item('hidden_template_indicator', '');
		}

		return $this;
	}

	/**
	 * Set a template single or pair variable for use with the {exp:resource_router} plugin
	 *
	 * $router->setVariable('foo', 'bar');
	 *
	 * {exp:resource_router:foo} -> bar
	 *
	 * $router->setVariable('foo', array('bar' => 1, 'baz' => 2));
	 *
	 * {exp:resource_router:foo}{bar}-{baz}{/exp:resource_router:foo} -> 1-2
	 *
	 * $router->setVariable('foo', array(array('bar' => 1, 'baz' => 2), array('bar' => 3, 'baz' => 4)));
	 *
	 * {exp:resource_router:foo}{bar}-{baz}|{/exp:resource_router:foo} -> 1-2|3-4
	 * 
	 * @param string $name the key or identifier of the variable
	 * @param string|array $data an array for a tag pair or a single value
	 * @return this
	 */
	public function setVariable($name, $data)
	{
		ee()->session->set_cache('resource_router', $name, $data);

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
		return array($this->templateGroup, $this->templateName);
	}

	/**
	 * Check if a template has been set
	 * @return bool
	 */
	public function hasTemplate()
	{
		return $this->templateName && $this->templateGroup;
	}

	/**
	 * Check if the current uri is routable
	 *
	 * @return bool is there a valid template set, and does it not match a page uri exactly
	 */
	public function isRoutable()
	{
		return $this->hasTemplate() && ! $this->isPage;
	}

	/**
	 * Get the specified set variable
	 * 
	 * @param  string $which the variable key
	 * @return mixed|false false if doesn't exist
	 */
	public function variable($which)
	{
		return ee()->session->cache('resource_router', $which);
	}

	/**
	 * Exit the application immediately
	 * @param  int    $statusCode
	 * @param  string $message
	 * @param  array  $headers
	 * @return void
	 */
	protected function quit($statusCode, $message = '', $headers = array())
	{
		set_status_header($statusCode);

		$hasContentLength = false;

		foreach ($headers as $key => $value)
		{
			header(sprintf('%s: %s', $key, $value));
		}

		exit($message);
	}
}

<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Template_router {

	protected $routes = array();
	protected $page_uris = array();

	public $variables = array();
	public $is_page = FALSE;
	public $template = NULL;
	public $query_string = '';
	public $matches = array();

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
			$this->page_uris = $site_pages[$site_id]['uris'];
		}

		ee()->load->helper(array('file', 'string'));
	}

	public function run($uri_string)
	{
		// set all the {route_X} variables to blank by default
		for ($i = 0; $i <= 10; $i++)
		{
			$this->setGlobal('route_'.$i);
		}

		// normalize the uri_string
		$uri_string = rtrim($uri_string, '/');

		// check if this URI is a Pages URI
		$this->is_page = in_array('/'.$uri_string, $this->page_uris);

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
				if (preg_match('/\(?:page:(\d+)\)?/', $rule, $match) && isset($this->page_uris[$match[1]]))
				{
					$rule = str_replace($match[0], '('.ltrim($this->page_uris[$match[1]], '/').')', $rule);

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
			if (preg_match($regex, $uri_string, $this->matches))
			{
				//remove trailing/leading slashes from matches
				$this->matches = array_map('trim_slashes', $this->matches);

				if (is_callable($template))
				{
					$template = call_user_func($template, $this);
				}

				if (is_null($this->template))
				{
					$this->template = $template;
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
						$this->query_string = preg_replace('#^'.preg_quote(str_replace(array('(', ')'), '', substr($rule, 0, $wildcard))).'#', '', $uri_string);
					}

					break;
				}
			}
		}

		if ($this->template)
		{
			if ($this->query_string)
			{
				ee()->uri->query_string = $this->query_string;
			}

			// I want Structure's global variables set on urls that start with a pages URI
			// so we tell structure that the uri_string is the first match in the regex
			if ( ! $this->is_page && isset($this->matches[1]) && isset(ee()->extensions->OBJ['Structure_ext']) && in_array('/'.$this->matches[1], $this->page_uris))
			{
				$temp_uri_string = ee()->uri->uri_string;

				ee()->uri->uri_string = $this->matches[1];

				ee()->extensions->OBJ['Structure_ext']->sessions_start(ee()->session);

				ee()->uri->uri_string = $temp_uri_string;
			}

			// loop through the matched sub-strings
			foreach ($this->matches as $i => $match)
			{
				// set each sub-string as a global template variable
				$this->setGlobal('route_'.$i, $match);

				// replace any sub-string matches in the template definition
				$this->template = str_replace('$'.$i, $match, $this->template);
			}
		}
	}

	public function matches()
	{
		return $this->matches;
	}

	public function match($which)
	{
		return array_key_exists($which, $this->matches) ? $this->matches[$which] : FALSE;
	}

	public function setGlobal($key, $value = '')
	{
		ee()->config->_global_vars[$key] = $value;

		return $this;
	}

	public function setVariable($name, $data)
	{
		$this->variables[$name] = $data;

		return $this;
	}

	public function set404()
	{
		//all the conditions to trigger a 404 in the TMPL class
		$hidden_indicator = ee()->config->item('hidden_template_indicator') === FALSE ? '.' : ee()->config->item('hidden_template_indicator');
		
		ee()->uri->page_query_string = '';
		
		ee()->config->set_item('hidden_template_404', 'y');

		$this->template = '/'.$hidden_indicator;
	}
}

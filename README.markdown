# Template Routes

Control your URLs by remapping URI routes to a specific template, using [CodeIgniter-style](http://ellislab.com/codeigniter/user-guide/general/routing.html) routing rules.

## Installation

*NOTE:* ExpressionEngine 2.6+ and PHP 5.3 are required

* Copy the /system/expressionengine/third_party/template_routes/ folder to your /system/expressionengine/third_party/ folder
* Install the extension

## Usage

### Setting up your routing rules

Your routing rules must be set in your system/expressionengine/config/config.php file.

	$config['template_routes'] = array(
		'blog/:category' => 'site/blog-category',
		'blog/:year/:pagination' => 'site/blog-yearly-archive',
		'blog/:any' => 'site/blog-single',
	);

On the left are the URIs you wish to match, and on the right are `template_group/template_name` pairs. You may use wildcard matching in your rule definitions.

### Wildcards

#### :any

Matches any non-backslash character(s).

#### :num

Matches a numeric value.

#### :year

Matches 4 digits in a row.

#### :month

Matches 2 digits in a row.

#### :day

Matches 2 digits in a row.

#### :pagination

Matches a P:num segment.

#### :category

Matches `<your_reserved_category_word>/<category_id_or_url_title>`.

#### :page:XX

Matches a Pages/Structure URI for the specified entry_id, where XX is the entry_id

	$config['template_routes'] = array(
		':page:123/:pagination' => 'site/page',
	);

#### :all

Matches all possible segments.

### Matches

All wildcards and any parenthesized regular expression pattersn will be available within your template as a tag variable:

	{route_1} - the first wildcard/parenthesized match
	{route_2} - the 2nd, and so forth

These matches are also available in your template definition, using `$1`, `$2` and so forth:

	$config['template_routes'] = array(
		'blog/:any/:any' => 'site/$1_$2',
	);

### Regular Expressions

Like standard CodeIgniter routing, you may also use regular expressions in your routing rules:

	$config['template_routes'] = array(
		'blog/([A-Z])/:any' => 'blog/alphabetized',
	);

Don't forget to wrap in parentheses if you would like your regular expression to become a `{route_X}` variable.

### Callbacks

You can use callbacks in your routes:

	$config['template_routes'] = array(
		'blog/:any' => function($router) {
			$router->setTemplate('blog/single');
		} 
	);

Your callback should set a valid `template_group/template` string using the `$router->setTemplate()` method.

Or you can avoid setting a template to signify that this url does *not* match the route:

	'blog/:any' => function($router) {
		if ($router->wildcard(1) === 'foo') {
			return;
		}
		$router->setTemplate('blog/single');
	}

Return a string to immediately output that string and avoid the template engine:

	'blog/:any' => function($router) {
		return 'You found: '.$router->wildcard(1);
	}


The first argument in the callback is the router object. It has a few methods you can use.

#### $router->wildcard($which)

	'blog/:any' => function($router) {
		// get the value of :any
		$url_title = $router->wildcard(1);
	}

#### $router->setTemplate($template)

	'blog/:any' => function($router) {
		// set the template to use for this URI
		$router->setTemplate('template_group/template_name');
	}

#### $router->set404()

	'blog/:any' => function($router) {
		// invoke a 404 page using EE's 404 template
		$router->set404();
	}

#### $router->setGlobal($key, $value)

	'blog/:any' => function($router) {
		// use this as a global variable in your template {foo} -> bar
		$router->setGlobal('foo', 'bar');
	}

#### $router->setVariable($key, $value)

	'blog/:any' => function($router) {
		// use this as a plugin variable in your template {exp:template_routes:foo} -> bar
		$router->setGlobal('foo', 'bar');

		// use this as a plugin variable in your template {exp:template_routes:foo}{bar}{/exp:template_routes:foo} -> baz
		$router->setGlobal('foo', array('bar' => 'baz'));
	}

#### $router->setWildcard($which, $value)

	'blog/:any' => function($router) {
		// change a wildcard global variable {route_1} -> bar
		$router->setWildcard(1, 'bar');
	}

#### $router->setContentType($content_type)

	'blog/:any' => function($router) {
		$router->setContentType('application/json');
		return '{"foo":"bar"}';
	}

#### $router->setHeader($name, $value)

	'blog/:any' => function($router) {
		$router->setHeader('Content-Type', 'application/json');
		return '{"foo":"bar"}';
	}

#### $router->setHttpStatus($code)

	'blog/:any' => function($router) {
		// set a valid HTTP status code
		$router->setHttpStatus(401);
	}

#### $router->json($data)

	'blog/:any' => function($router) {
		// return the specified data, json encoded, with Content-Type: application/json headers
		$router->json(array('foo' => 'bar'));
	}

#### $router->isValidEntryId($entry_id)

	'blog/:any' => function($router) {
		// check if the entry_id is valid
		$entry_id = $router->wildcard(1);

		if ($router->isValidEntryId($entry_id))
		{
			$router->setTemplate('site/_blog_detail');
		}
		else
		{
			$router->set404();
		}
	}

#### $router->isValidUrlTitle($url_title)

	'blog/:any' => function($router) {
		// check if the url_title is valid
		$url_title = $router->wildcard(1);

		if ($router->isValidUrlTitle($url_title))
		{
			$router->setTemplate('site/_blog_detail');
		}
		else
		{
			$router->set404();
		}
	}

#### $router->isValidEntry($where)

	'blog/:any' => function($router) {
		// check if the url_title is valid
		$url_title = $router->wildcard(1);

		if ($router->isValidEntry(array('url_title' => $url_title, 'status' => 'open')))
		{
			$router->setTemplate('site/_blog_detail');
		}
		else
		{
			$router->set404();
		}
	}

#### $router->isValidCategoryId($cat_id)

	'blog/:any' => function($router) {
		// check if the cat_id is valid
		$cat_id = $router->wildcard(1);

		if ($router->isValidCategoryId($cat_id))
		{
			$router->setTemplate('site/_blog_category');
		}
		else
		{
			$router->set404();
		}
	}

#### $router->isValidCategoryUrlTitle($url_title)

	'blog/:any' => function($router) {
		// check if the cat_url_title is valid
		$cat_url_title = $router->wildcard(1);

		// use the second parameter to specify a column to retrieve data from
		$cat_id = $router->isValidCategoryUrlTitle($cat_url_title, 'cat_id');

		if ($cat_id !== FALSE)
		{
			$router->setWildcard(1, $cat_id);
			$router->setTemplate('site/_blog_category');
		}
		else
		{
			$router->set404();
		}
	}

#### $router->isValidCategory($where)

	'blog/:any' => function($router) {
		// check if the cat_url_title is valid
		$cat_url_title = $router->wildcard(1);

		// use the second parameter to specify a column to retrieve data from
		$cat_id = $router->isValidCategory(array(
			'cat_url_title' => $cat_url_title,
			'channel' => 'blog',
		), 'cat_id');

		if ($cat_id !== FALSE)
		{
			$router->setWildcard(1, $cat_id);
			$router->setTemplate('site/_blog_category');
		}
		else
		{
			$router->set404();
		}
	}

#### $router->isValidMemberId($member_id)

	'users/:num' => function($router) {
		// check if the member_id is valid
		$member_id = $router->wildcard(1);

		if ($router->isValidMemberId($member_id))
		{
			$router->setTemplate('site/_user_detail');
		}
		else
		{
			$router->set404();
		}
	}

#### $router->isValidUsername($username)

	'users/:any' => function($router) {
		// check if the username is valid
		$username = $router->wildcard(1);

		// use the second parameter to specify a column to retrieve data from
		$member_id = $router->isValidUsername($username, 'member_id');

		if ($member_id !== FALSE)
		{
			$router->setWildcard(1, $member_id);
			$router->setTemplate('site/_user_detail');
		}
		else
		{
			$router->set404();
		}
	}

#### $router->isValidMember($where)

	'users/:any' => function($router) {
		// check if the username is valid
		$username = $router->wildcard(1);

		// use the second parameter to specify a column to retrieve data from
		$member_id = $router->isValidMember(array(
			'username' => $username,
			'group_id' => 5,
		), 'member_id');

		if ($member_id !== FALSE)
		{
			$router->setWildcard(1, $member_id);
			$router->setTemplate('site/_user_detail');
		}
		else
		{
			$router->set404();
		}
	}

### Examples

Add pagination, category, and yearly/monthly/daily archives to a Pages/Structure page:

	$config['template_routes'] = array(
		':page:123/:pagination' => 'site/_blog-index',
		':page:123/:category' => 'site/_blog-category',
		':page:123/:year' => 'site/_blog-yearly',
		':page:123/:year/:month' => 'site/_blog-monthly',
		':page:123/:year/:month/:day' => 'site/_blog-daily',
	);

Use callbacks for highly custom URLs:

	$config['template_routes'] = array(
		'blog/:any' => function($router) {
			$segment = $router->wildcard(1);

			// is it a url title?
			if ($router->isValidUrlTitle($segment))
			{
				$router->setTemplate('site/_blog_detail');
			}
			else if (FALSE !== ($cat_id = $router->isValidCategoryUrlTitle($segment)))
			{
				$router->setWildcard(1, $cat_id);
				$router->setTemplate('site/_blog_category');
			}
			else if (FALSE !== ($member_id = $router->isValidUsername($segment)))
			{
				$router->setWildcard(1, $member_id);
				$router->setTemplate('site/_blog_author');
			}
			else
			{
				$router->set404();
			}
		}
	);
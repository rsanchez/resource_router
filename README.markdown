# Template Routes

Control your URLs by remapping URI routes to a specific template, using [CodeIgniter-style](http://ellislab.com/codeigniter/user-guide/general/routing.html) routing rules.

## Installation

*NOTE:* ExpressionEngine 2.6+ and PHP 5.3+ are required

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

Matches any non-backslash character(s). The equivalent regular expression is `([^/]+)`;

#### :num

Matches a numeric value. The equivalent regular expression is `(\d+)`;

#### :year

Matches 4 digits in a row. The equivalent regular expression is `(\d{4})`;

#### :month

Matches 2 digits in a row. The equivalent regular expression is `(\d{2})`;

#### :day

Matches 2 digits in a row. The equivalent regular expression is `(\d{2})`;

#### :pagination

Matches a P:num segment. The equivalent regular expression is `(/P\d+)?`;

#### :category

Matches `<Category URL Indicator>/<category_id or category_url_title>`. The Category URL Indicator is set in Admin > Channel Administration > Global Preferences. The second segment value depends on the "Use Category URL Titles In Links?" setting.

#### :page:XX

Matches a Pages/Structure URI for the specified entry_id, where XX is the entry_id

	$config['template_routes'] = array(
		':page:123/:pagination' => 'site/page',
	);

#### :all

Matches all possible segments. The equivalent regular expression is `(/.*)?`;

#### :entry_id

Matches an entry id. Does not match if the entry id is not found in the database. To validate on additional columns (ex. `status` or `channel`) you should use [Callbacks](#callbacks) and [`$wildcard->isValidEntryId()`](#wildcard-isvalidentryidwhere--array).

#### :url_title

Matches a url title. Does not match if the url title is not found in the database. To validate on additional columns (ex. `status` or `channel`) you should use [Callbacks](#callbacks) and [`$wildcard->isValidUrlTitle()`](#wildcard-isvalidurltitlewhere--array).

#### :category_id

Matches a category id. Does not match if the category id is not found in the database. To validate on additional columns (ex. `group_id` or `channel`) you should use [Callbacks](#callbacks) and [`$wildcard->isValidCategoryId()`](#wildcard-isvalidcategoryidwhere--array).

#### :category_url_title

Matches a category url title. Does not match if the category url title is not found in the database. To validate on additional columns (ex. `group_id` or `channel`) you should use [Callbacks](#callbacks) and [`$wildcard->isValidCategoryUrlTitle()`](#wildcard-isvalidcategoryurltitlewhere--array).

#### :member_id

Matches a member id. Does not match if the member id is not found in the database. To validate on additional columns (ex. `group_id` or `channel`) you should use [Callbacks](#callbacks) and [`$wildcard->isValidMemberId()`](#wildcard-isvalidmemberidwhere--array).

#### :username

Matches a username. Does not match if the username is not found in the database. To validate on additional columns (ex. `group_id` or `channel`) you should use [Callbacks](#callbacks) and [`$wildcard->isValidUsername()`](#wildcard-isvalidusernamewhere--array).

### Matches

All wildcards and any parenthesized regular expression patterns will be available within your template as a tag variable:

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
		'blog/:any/:any' => function($router, $wildcard_1, $wildcard_2) {
			$router->setTemplate('blog/single');
		} 
	);

Your callback should set a valid `template_group/template` string using the `$router->setTemplate()` method.

Or you can avoid setting a template to signify that this url does *not* match the route:

	'blog/:any' => function($router, $wildcard) {
		if ($wildcard->value === 'foo') {
			return;
		}
		$router->setTemplate('blog/single');
	}

Return a string to immediately output that string and avoid the template engine:

	'blog/:any' => function($router, $wildcard) {
		return 'You found: '.$wildcard;
	}

#### $router

The first argument in the callback is a `\Template_routes\Router` object. It has a few methods you can use.

##### $router->setTemplate(string $template)

Set the `template_group/template_name` to use for this URI

	'blog/:any' => function($router) {
		$router->setTemplate('template_group/template_name');
	}

##### $router->set404()

Trigger your EE 404 template.

	'blog/:any' => function($router) {
		$router->set404();
	}

##### $router->setGlobal(string $key, string|int|bool $value)

Set a global variable to use in your template.

	'blog/:any' => function($router) {
		// {foo} -> bar
		$router->setGlobal('foo', 'bar');
	}

##### $router->setVariable(string $key, mixed $value)

Set tag pair arrays to use as variables in your template. These variables are accessible using the `{exp:template_routes:your_var_name}` template tags.

	'blog/:any' => function($router) {
		// {exp:template_routes:foo} -> bar
		$router->setVariable('foo', 'bar');

		// {exp:template_routes:foo}{bar}-{baz}{/exp:template_routes:foo} -> abc-def
		$router->setVariable('foo', array('bar' => 'abc', 'baz' => 'def'));

		// {exp:template_routes:foo}{bar}-{baz},{/exp:template_routes:foo} -> abc-def,ghi-jkl,
		$router->setVariable('foo', array(
			array('bar' => 'abc', 'baz' => 'def'),
			array('bar' => 'ghi', 'baz' => 'jkl'),
		));
	}

##### $router->setWildcard(int $which, string|int|bool $value)

Change the value of a wildcard at the specified index.

	'blog/:any' => function($router) {
		// change a wildcard global variable {route_1} -> bar
		$router->setWildcard(1, 'bar');
	}

##### $router->setContentType(string $content_type)

Change the Content-Type HTTP response header.

	'blog/:any' => function($router) {
		$router->setContentType('application/json');
		return '{"foo":"bar"}';
	}

##### $router->setHeader(string $name, string $value)

Set an HTTP response header.

	'blog/:any' => function($router) {
		$router->setHeader('Content-Type', 'application/json');
		return '{"foo":"bar"}';
	}

##### $router->setHttpStatus(int $code)

Set the HTTP response status code.

	'blog/:any' => function($router) {
		$router->setHttpStatus(401);
	}

##### $router->json(mixed $data)

Send a JSON response of the data wwith `Content-Type: application/json` headers.

	'blog/:any' => function($router) {
		$router->json(array('foo' => 'bar'));
	}

##### $router->redirect(string $url, int $statusCode = 301)

Redirect to the specified URL or `template_group/template_name`.

	'blog/:any' => function($router) {
		$router->redirect('foo/bar');
	}

#### $wildcard

The second and subsequent callback arguments are `\Template_routes\Wildcard` objects.

##### $wildcard->value

Get the value of the wildcard match.

  'blog/:any/:any' => function($router, $wildcard_1, $wildcard_2) {
	  $last_segment = $wildcard_2->value;
	}

##### $wildcard->isValidEntryId($where = array())

Check if the specified entry_id exists.

	'blog/:num' => function($router, $wildcard) {
		if ($wildcard->isValidEntryId())
		{
			$router->setTemplate('site/_blog_detail');
		}
		else
		{
			$router->set404();
		}
	}

In the second parameter, you can specify other columns/values to use in the query WHERE statement.

	'blog/:num' => function($router, $wildcard) {
		$where = array(
			'status' => 'open',
			'channel' => 'blog',
		);
	
		if ($wildcard->isValidEntryId($where))
		{
			$router->setTemplate('site/_blog_detail');
		}
		else
		{
			$router->set404();
		}
	}

##### $wildcard->isValidUrlTitle($where = array())

Check if the specified url_title exists.

	'blog/:any' => function($router, $wildcard) {
		if ($wildcard->isValidUrlTitle())
		{
			$router->setTemplate('site/_blog_detail');
		}
		else
		{
			$router->set404();
		}
	}

##### $router->isValidEntry(array $where)

Check if the specified entry exists.

	'blog/:any' => function($router, $wildcard) {
		if ($router->isValidEntry(array('url_title' => $wildcard, 'status' => 'open')))
		{
			$router->setTemplate('site/_blog_detail');
		}
		else
		{
			$router->set404();
		}
	}

##### $wildcard->isValidCategoryId($where = array())

Check if the specified cat_id exists. If a match is found, this will automatically set global variables for the category data in the form {route_X_column_name}, eg. {route_1_cat_id} {route_2_cat_name}.

	'blog/:any' => function($router, $wildcard) {
		if ($wildcard->isValidCategoryId())
		{
			$router->setTemplate('site/_blog_category');
		}
		else
		{
			$router->set404();
		}
	}

##### $wildcard->isValidCategoryUrlTitle($where = array())

Check if the specified category url_title exists. If a match is found, this will automatically set global variables for the category data in the form {route_X_column_name}, eg. {route_1_cat_id} {route_2_cat_name}.

	'blog/:any' => function($router, $wildcard) {
		if ($wildcard->isValidCategoryUrlTitle())
		{
			$router->setTemplate('site/_blog_category');
		}
		else
		{
			$router->set404();
		}
	}

##### $wildcard->isValidCategory(array $where)

Check if the specified category exists. If a match is found, this will automatically set global variables for the category data in the form {route_X_column_name}, eg. {route_1_cat_id} {route_2_cat_name}.

	'blog/:any' => function($router, $wildcard) {
		// use the second parameter to specify a column to retrieve data from
		$valid = $wildcard->isValidCategory(array(
			'cat_url_title' => $wildcard->value,
			'channel' => 'blog',
		));

		if ($valid)
		{
			$router->setTemplate('site/_blog_category');
		}
		else
		{
			$router->set404();
		}
	}

##### $wildcard->isValidMemberId($where = array())

Check if the specified member_id exists.

	'users/:num' => function($router, $wildcard) {
		if ($wildcard->isValidMemberId())
		{
			$router->setTemplate('site/_user_detail');
		}
		else
		{
			$router->set404();
		}
	}

##### $wildcard->isValidUsername($where = array())

Check if the specified username exists.

	'users/:any' => function($router, $wildcard) {
		if ($wildcard->isValidUsername())
		{
			$router->setTemplate('site/_user_detail');
		}
		else
		{
			$router->set404();
		}
	}

##### $wildcard->isValidMember(array $where)

Check if the specified member exists.

	'users/:any' => function($router, $wildcard) {
		// use the second parameter to specify a column to retrieve data from
		$where = array(
			'username' => $wildcard,
			'group_id' => 5,
		);

		if ($router->isValidMember($where))
		{
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
		'blog/:any' => function($router, $wildcard) {
			// is it a category url title?
			if ($wildcard->isValidCategoryUrlTitle())
			{
				$router->setTemplate('site/_blog_category');
			}
			// is it a username?
			else if ($wildcard->isValidUsername())
			{
				$router->setTemplate('site/_blog_author');
			}
			// is it a url title?
			else if ($wildcard->isValidUrlTitle())
			{
				$router->setTemplate('site/_blog_detail');
			}
			else
			{
				$router->set404();
			}
		}
	);
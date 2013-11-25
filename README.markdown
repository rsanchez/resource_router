# Template Routes

Control your URLs by remapping URI routes to a specific template, using [CodeIgniter-style](http://ellislab.com/codeigniter/user-guide/general/routing.html) routing rules.

## Installation

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

With PHP 5.3+, you can use callbacks in your routes:

	$config['template_routes'] = array(
		'blog/:any' => function($router, $route_1) {
			return 'blog/single';
		} 
	);

Your callback should return a valid `template_group/template` string.

Or you can return `FALSE` to signify that this url does *not* match the route:

	$config['template_routes'] = array(
		'blog/:any' => function($router, $route_1) {
			if ($route_1 === 'foo') {
				return FALSE;
			}
			return 'blog/single';
		} 
	);

The first argument in the callback is the router object. It has a few methods you can use.

	$config['template_routes'] = array(
		'blog/:any' => function($router, $route_1) {
			// creates a {foo} global variable to use in your templates
			$router->set_global('foo', 'bar');

			// creates a set of variables to use in your templates
			// @TODO replace with stash
			$router->set_variable('foo', array(
				''
			));

			$router->set_404();
		} 
	);

### Examples

Add pagination, category, and yearly/monthly/daily archives to a Pages/Structure page:

	$config['template_routes'] = array(
		':page:123/:pagination' => 'site/_blog-index',
		':page:123/:category' => 'site/_blog-category',
		':page:123/:year' => 'site/_blog-yearly',
		':page:123/:year/:month' => 'site/_blog-monthly',
		':page:123/:year/:month/:day' => 'site/_blog-daily',
	);

Use callbacks to validate categories in URLs and set a global variable:

	$config['template_routes'] = array(
		'blog/:any' => function($router) {
			$segment = $router->match(1);

			$query = ee()->db->select('cat_id')
											 ->where('cat_url_title', $segment)
											 ->where_in('group_id', array(1, 2))
											 ->get('categories');

			$cat_id = $query->row('cat_id');

			$query->free_result();

			if ($cat_id) {
				// so you can:
				// {exp:channel:entries channel="blog" category="{route_1_cat_id}"}
				$router->set_global('route_1_cat_id', $query->row('cat_id'));

				return 'blog/category';
			}

			return 'blog/single';
		}
	);

	$config['template_routes'] = array(
		'blog/:any' => function($router) {
			$cat_id = $router->get_category(1, array('group_id' => 1));

			if ($cat_id) {
				// so you can:
				// {exp:channel:entries channel="blog" category="{route_1_cat_id}"}
				$router->set_global('route_1_cat_id', $query->row('cat_id'));

				return 'blog/category';
			}

			return 'blog/single';
		}
	);
# Resource Router

Control your URLs by remapping URI routes to a specific HTTP response, using [CodeIgniter-style](http://ellislab.com/codeigniter/user-guide/general/routing.html) routing rules.

## Why?

* You need to break out of the `template_group/template_name` paradigm of traditional EE routing
* You need to nest urls on a Structure/Pages URI (pagination, categories, etc.)
* You need to point multiple url patterns to a single template
* You need custom JSON/HTML endpoints that do not warrant a full EE template
* You want to remove excess conditional logic in your templates

Replace this:

    {if segment 2 == "category"}
        {embed="blog/.category"}
    {if:elseif segment_2 == "view"}
        {embed="blog/.single"}
    {if:else}
        {embed="blog/.listing"}
    {/if}

With this:

    'blog/:any' => function ($router, $wildcard) {
        if ($wildcard->is('category')) {
            $router->setTemplate('blog/.category');
        } elseif ($wildcard->is('view')) {
            $router->setTemplate('blog/.single');
        } else {
            $router->setTemplate('blog/.listing');
        }
    },

## Installation

*NOTE:* ExpressionEngine 2.6+ and PHP 5.3+ are required

* Copy the /system/expressionengine/third_party/resource_router/ folder to your /system/expressionengine/third_party/ folder
* Install the extension

## Updating from Template Routes

This add-on was formerly known as Template Routes. If you are updating from Template Routes, you should:

* Uninstall Template Routes
* Install Resource Router
* Change your `$config['template_routes']` to `$config['resource_router']`

## Basic Usage

### Setting up your routing rules

Your routing rules must be set in your system/expressionengine/config/config.php file.

    $config['resource_router'] = array(
        'blog/:category' => 'site/blog-category',
        'blog/:year/:pagination' => 'site/blog-yearly-archive',
    );

On the left is the URI pattern you wish to match, and on the right is one of two things: a) a string representing a `template_group/template_name` pair, or b) a callback receiving Router and Wilcard objects where you can craft a response for the URL. The callback functionality is covered in the [Advanced Usage](#advanced-usage) section.

NOTE: Pages module or Structure module URLs take precendence over Resource Router.

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

Matches a P:num segment. The equivalent regular expression is `((?:/P\d+)?)`. This is an *optional* segment. If not present in the URI, the URI will still be considered a match. If so, the Wildcard object will have a `null` value.

#### :category

Matches `<Category URL Indicator>/<category_id or category_url_title>`. The Category URL Indicator is set in Admin > Channel Administration > Global Preferences. The second segment value depends on the "Use Category URL Titles In Links?" setting.

#### :page:XX

Matches a Pages/Structure URI for the specified entry_id, where XX is the entry_id

    $config['resource_router'] = array(
        ':page:123/:pagination' => 'site/page',
    );

#### :all

Matches all possible segments. The equivalent regular expression is `((?:/.*)?)`. This is an *optional* segment. If not present in the URI, the URI will still be considered a match. If so, the Wildcard object will have a `null` value. When multiple segments are detected, the callback will receive many Wildcard objects.

#### :before

Register a callback to be run **before** ALL routes. For instance, this is a good place to set global variables that you need on all pages.

NOTE: This is a special wildcard. Your route must be exactly `':before'` with no other segments.

### Validating Wildcards

These wildcards will perform a database operation to ensure that the wildcard value exists in the database. To validate on additional columns (ex. `status` or `channel`) you should use [Callbacks](#callbacks)). If you provide a callback, as opposed to a `template_group/template_name` string, the wildcard will *not* be validated. You must validate it yourself in your callback.

Validating wildcards will have corresponding meta data attached to the wildcard object, and will set `{route_X_foo}` variables, where `foo` is a column from the database, such as cat_id. (Think Low Seg2cat).

#### :entry_id

Matches an entry id. Does not match if the entry id is not found in the database. To validate on additional columns, you should use a [Callback](#callbacks) and [`$wildcard->isValidEntryId()`](#wildcard-isvalidentryidwhere--array). Adds the following variables: `{route_X_entry_id}`, `{route_X_title}`, `{route_X_url_title}`, `{route_X_channel_id}`, where X is the specified URL segment.

#### :url_title

Matches a url title. Does not match if the url title is not found in the database. To validate on additional columns, you should use a [Callback](#callbacks) and [`$wildcard->isValidUrlTitle()`](#wildcard-isvalidurltitlewhere--array). Adds the following variables: `{route_X_entry_id}`, `{route_X_title}`, `{route_X_url_title}`, `{route_X_channel_id}`, where X is the specified URL segment.

#### :category_id

Matches a category id. Does not match if the category id is not found in the database. To validate on additional columns (ex. `group_id` or `channel`), you should use a [Callback](#callbacks) and [`$wildcard->isValidCategoryId()`](#wildcard-isvalidcategoryidwhere--array). Adds the following variables: `{route_X_cat_id}`, `{route_X_site_id}`, `{route_X_group_id}`, `{route_X_parent_id}`, `{route_X_cat_name}`, `{route_X_cat_url_title}`, `{route_X_cat_description}`, `{route_X_cat_image}`, `{route_X_cat_order'}`, where X is the specified URL segment.

#### :category_url_title

Matches a category url title. Does not match if the category url title is not found in the database. To validate on additional columns (ex. `group_id` or `channel`), you should use a [Callback](#callbacks) and [`$wildcard->isValidCategoryUrlTitle()`](#wildcard-isvalidcategoryurltitlewhere--array). Adds the following variables: `{route_X_cat_id}`, `{route_X_site_id}`, `{route_X_group_id}`, `{route_X_parent_id}`, `{route_X_cat_name}`, `{route_X_cat_url_title}`, `{route_X_cat_description}`, `{route_X_cat_image}`, `{route_X_cat_order'}`, where X is the specified URL segment.

#### :member_id

Matches a member id. Does not match if the member id is not found in the database. To validate on additional columns (ex. `group_id` or `channel`), you should use a [Callback](#callbacks) and [`$wildcard->isValidMemberId()`](#wildcard-isvalidmemberidwhere--array). Adds the following variables: `{route_X_member_id}`, `{route_X_group_id}`, `{route_X_email}`, `{route_X_username}`, `{route_X_screen_name}`, where X is the specified URL segment.

#### :username

Matches a username. Does not match if the username is not found in the database. To validate on additional columns (ex. `group_id` or `channel`), you should use a [Callback](#callbacks) and [`$wildcard->isValidUsername()`](#wildcard-isvalidusernamewhere--array). Adds the following variables: `{route_X_member_id}`, `{route_X_group_id}`, `{route_X_email}`, `{route_X_username}`, `{route_X_screen_name}`, where X is the specified URL segment.

### Matches

All wildcards and any parenthesized regular expression patterns will be available within your template as a tag variable:

    {route_1} - the first wildcard/parenthesized match
    {route_2} - the 2nd, and so forth

These matches are also available in your template definition, using `$1`, `$2` and so forth:

    $config['resource_router'] = array(
        'blog/:any/:any' => 'site/$1_$2',
    );

### Regular Expressions

Like standard CodeIgniter routing, you may also use regular expressions in your routing rules:

    $config['resource_router'] = array(
        'blog/([A-Z])/:any' => 'blog/alphabetized',
    );

Don't forget to wrap in parentheses if you would like your regular expression to become a Wildcard and `{route_X}` variable.

## Advanced Usage

### Callbacks

You can use callbacks in your routes:

    $config['resource_router'] = array(
        'blog/:any/:any' => function($router, $wildcard_1, $wildcard_2) {
            $router->setTemplate('blog/single');
        }
    );

If you wish to output an EE template at the specified url pattern, your callback should set a valid `template_group/template` string using the `$router->setTemplate()` method.

Or you can avoid setting a template to signify that this url does *not* match the route:

    'blog/:any' => function($router, $wildcard) {
        if ($wildcard->is('foo')) {
            return;
        }
        $router->setTemplate('blog/single');
    }

Return a string to immediately output that string and avoid the template engine:

    'blog/:any' => function($router, $wildcard) {
        return 'You found: '.$wildcard;
    }

#### $router

The first argument in the callback is a `rsanchez\ResourceRouter\Router` object. It has a few methods you can use.

##### $router->setTemplate(string $template)

Set the `template_group/template_name` to use for this URI

    'blog/:any' => function($router) {
        $router->setTemplate('template_group/template_name');
    }

##### $router->set404()

Trigger your EE 404 template. You must set a 404 Page template in your EE Global Template Preferences.

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

Set tag pair arrays to use as variables in your template. These variables are accessible using the `{exp:resource_router:your_var_name}` template tags.

    'blog/:any' => function($router) {
        // {exp:resource_router:foo} -> bar
        $router->setVariable('foo', 'bar');

        // {exp:resource_router:foo}{bar}-{baz}{/exp:resource_router:foo} -> abc-def
        $router->setVariable('foo', array('bar' => 'abc', 'baz' => 'def'));

        // {exp:resource_router:foo}{bar}-{baz},{/exp:resource_router:foo} -> abc-def,ghi-jkl,
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

##### $router->view(string $view, array $variables)

Render a CodeIgniter view file

    'blog/:any' => function($router) {
        $router->view('myview', array('foo' => 'bar'));
    }

By default, view files are read from `system/expressionengine/third_party/resource_router/views/`. If you wish to change this, you can do so in your config file:

    $config['resource_router:package_path'] = '/path/to/directory/';

Your defined package path must have a `views` folder within it. You should not add `views/` to your defined package path. In other words, if your views are in `/var/www/assets/views/`, your package path should be set to `/var/www/assets/`.

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

The second and subsequent callback arguments are `rsanchez\ResourceRouter\Wildcard` objects.

##### $wildcard->value

Get the value of the wildcard match.

    'blog/:any/:any' => function($router, $wildcard_1, $wildcard_2) {
        $last_segment = $wildcard_2->value;

        // you may also simply cast to string
        $last_segment = (string) $wildcard_2;
    }

##### $wildcard->is($value)

Check if the wildcard equals the specified value. This uses the PHP `==` operator internally.

```
    'blog/:url_title' => function($router, $wildcard) {
        if ($wildcard->is('some_special_post')) {
            $router->setTemplate('site/_blog_special');
        } else {
            $router->setTemplate('site/_blog_detail');
        }
    }
```

##### $wildcard->isNot($value)

Check if the wildcard is not equal to the specified value. This uses the PHP `!=` operator.

##### $wildcard->isExactly($value)

Check if the wildcard is exactly equal to the specified value. This uses the PHP `===` operator.

##### $wildcard->isNotExactly($value)

Check if the wildcard is not exactly equal to the specified value. This uses the PHP `!==` operator.

##### $wildcard->isGreaterThan($value)

Check if the wildcard is greater than the specified value. This uses the PHP `>` operator.

##### $wildcard->isGreaterThanOrEquals($value)

Check if the wildcard is greater than or equal to the specified value. This uses the PHP `>=` operator.

##### $wildcard->isLessThan($value)

Check if the wildcard is less than the specified value. This uses the PHP `<` operator.

##### $wildcard->isLessThanOrEquals($value)

Check if the wildcard is less than or equal to the specified value. This uses the PHP `<=` operator.

##### $wildcard->isValidEntryId($where = array())

Check if the specified entry_id exists. Adds the following variables: `{route_X_entry_id}`, `{route_X_title}`, `{route_X_url_title}`, `{route_X_channel_id}`, where X is the specified URL segment.

    'blog/:num' => function($router, $wildcard) {
        if ($wildcard->isValidEntryId()) {
            $router->setTemplate('site/_blog_detail');
        } else {
            $router->set404();
        }
    }

In the second parameter, you can specify other columns/values to use in the query WHERE statement.

    'blog/:num' => function($router, $wildcard) {
        $where = array(
            'status' => 'open',
            'channel' => 'blog',
        );
    
        if ($wildcard->isValidEntryId($where)) {
            $router->setTemplate('site/_blog_detail');
        } else {
            $router->set404();
        }
    }

##### $wildcard->isValidUrlTitle($where = array())

Check if the specified url_title exists. Adds the following variables: `{route_X_entry_id}`, `{route_X_title}`, `{route_X_url_title}`, `{route_X_channel_id}`, where X is the specified URL segment.

    'blog/:any' => function($router, $wildcard) {
        if ($wildcard->isValidUrlTitle()) {
            $router->setTemplate('site/_blog_detail');
        } else {
            $router->set404();
        }
    }

##### $wildcard->isValidEntry($where = array())

Check if the specified entry exists. Adds the following variables: `{route_X_entry_id}`, `{route_X_title}`, `{route_X_url_title}`, `{route_X_channel_id}`, where X is the specified URL segment.

    'blog/:any' => function($router, $wildcard) {
        if ($wildcard->isValidEntry(array('url_title' => $wildcard, 'status' => 'open'))) {
            $router->setTemplate('site/_blog_detail');
        } else {
            $router->set404();
        }
    }

##### $wildcard->isValidCategoryId($where = array())

Check if the specified cat_id exists. Adds the following variables: `{route_X_cat_id}`, `{route_X_site_id}`, `{route_X_group_id}`, `{route_X_parent_id}`, `{route_X_cat_name}`, `{route_X_cat_url_title}`, `{route_X_cat_description}`, `{route_X_cat_image}`, `{route_X_cat_order'}`, where X is the specified URL segment.

    'blog/:any' => function($router, $wildcard) {
        if ($wildcard->isValidCategoryId()) {
            $router->setTemplate('site/_blog_category');
        } else {
            $router->set404();
        }
    }

##### $wildcard->isValidCategoryUrlTitle($where = array())

Check if the specified category url_title exists. Adds the following variables: `{route_X_cat_id}`, `{route_X_site_id}`, `{route_X_group_id}`, `{route_X_parent_id}`, `{route_X_cat_name}`, `{route_X_cat_url_title}`, `{route_X_cat_description}`, `{route_X_cat_image}`, `{route_X_cat_order'}`, where X is the specified URL segment.

    'blog/:any' => function($router, $wildcard) {
        if ($wildcard->isValidCategoryUrlTitle()) {
            $router->setTemplate('site/_blog_category');
        } else {
            $router->set404();
        }
    }

##### $wildcard->isValidCategory(array $where)

Check if the specified category exists. Adds the following variables: `{route_X_cat_id}`, `{route_X_site_id}`, `{route_X_group_id}`, `{route_X_parent_id}`, `{route_X_cat_name}`, `{route_X_cat_url_title}`, `{route_X_cat_description}`, `{route_X_cat_image}`, `{route_X_cat_order'}`, where X is the specified URL segment.

    'blog/:any' => function($router, $wildcard) {
        // use the second parameter to specify a column to retrieve data from
        $valid = $wildcard->isValidCategory(array(
            'cat_url_title' => $wildcard,
            'channel' => 'blog',
        ));

        if ($valid) {
            $router->setTemplate('site/_blog_category');
        } else {
            $router->set404();
        }
    }

##### $wildcard->isValidMemberId($where = array())

Check if the specified member_id exists. Adds the following variables: `{route_X_member_id}`, `{route_X_group_id}`, `{route_X_email}`, `{route_X_username}`, `{route_X_screen_name}`, where X is the specified URL segment.

    'users/:num' => function($router, $wildcard) {
        if ($wildcard->isValidMemberId()) {
            $router->setTemplate('site/_user_detail');
        } else {
            $router->set404();
        }
    }

##### $wildcard->isValidUsername($where = array())

Check if the specified username exists. Adds the following variables: `{route_X_member_id}`, `{route_X_group_id}`, `{route_X_email}`, `{route_X_username}`, `{route_X_screen_name}`, where X is the specified URL segment.

    'users/:any' => function($router, $wildcard) {
        if ($wildcard->isValidUsername()) {
            $router->setTemplate('site/_user_detail');
        } else {
            $router->set404();
        }
    }

##### $wildcard->isValidMember(array $where)

Check if the specified member exists. Adds the following variables: `{route_X_member_id}`, `{route_X_group_id}`, `{route_X_email}`, `{route_X_username}`, `{route_X_screen_name}`, where X is the specified URL segment.

    'users/:any' => function($router, $wildcard) {
        // use the second parameter to specify a column to retrieve data from
        $where = array(
            'username' => $wildcard,
            'group_id' => 5,
        );

        if ($wildcard->isValidMember($where)) {
            $router->setTemplate('site/_user_detail');
        } else {
            $router->set404();
        }
    }

#### $wildcard->getMeta(string $column)

Get the specified meta data from the wildcard. This is only applicable to validating wildcards.

    'posts/:url_title' => function($router, $wildcard) {
        if ($wildcard->isValidUrlTitle()) {
            // do something custom with this entry id
            $entry_id = $wildcard->getMeta('entry_id');

            $router->setTemplate('posts/_detail');
        } else {
            $router->set404();
        }
    }

### Examples

Add pagination, category, and yearly/monthly/daily archives to a Pages/Structure page:

    $config['resource_router'] = array(
        ':page:123/:pagination' => 'site/_blog-index',
        ':page:123/:category' => 'site/_blog-category',
        ':page:123/:year' => 'site/_blog-yearly',
        ':page:123/:year/:month' => 'site/_blog-monthly',
        ':page:123/:year/:month/:day' => 'site/_blog-daily',
    );

Suppose you wanted this scheme for blog urls:

* `/blog`
* `/blog/<your-cagtegory>`
* `/blog/<your-individual-post>`
* `/blog/<your-author-name>`

```
$config['resource_router'] = array(
    'blog/:any' => function($router, $wildcard) {
        // is it a category url title?
        if ($wildcard->isValidCategoryUrlTitle()) {
            $router->setTemplate('site/_blog_category');
        // is it a username?
        } elseif ($wildcard->isValidUsername()) {
            $router->setTemplate('site/_blog_author');
        // is it a url title?
        } elseif ($wildcard->isValidUrlTitle()) {
            $router->setTemplate('site/_blog_detail');
        } else {
            $router->set404();
        }
    }
);
````

A member group dependent endpoint:

    $config['resource_router'] = array(
        'restricted-area' => function($router) {
            if (ee()->session->userdata('group_id') != 1) {
                $router->set404();
            }
            $router->setTemplate('site/.restricted-area');
        }
    );


A custom JSON endpoint:

    $config['resource_router'] = array(
        'api/latest-news' => function($router) {
            $query = ee()->db->select('title, url_title')
                ->where('status', 'open')
                ->where('channel_id', 1)
                ->order_by('entry_date', 'ASC')
                ->limit(10)
                ->get('channel_titles');
            
            $result = $query->result();
            
            $router->json($result);
        }
    );


A callback used before all routes:
```
$config['resource_router'] = array(
    ':before' => function($router) {
        $admin_groups = array(1, 6, 7);
        $group_id = ee()->session->userdata('group_id');
        $is_admin = in_array($group_id, $admin_groups);
        $router->setGlobal('is_admin', $is_admin);
    },
);
```
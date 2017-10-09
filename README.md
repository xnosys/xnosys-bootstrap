# Xnosys: JSON API Framework for PHP

## Required framework structure
- /composer.json
- /config.env.development.ini
- /config.env.production.ini
- /config.routes.ini
- /index.php

**/composer.json:**
Application meta and package dependencies.

**/config.env.development.ini**
Environment variables for development: `domain`, `ssl`, and `origins[]` (allowed CORS origins).

**/config.env.production.ini:**
Environment variables for production: `domain`, `ssl`, and `origins[]` (allowed CORS origins). *The application will enforce all requests are made to the env `domain`, and not the server ip or alias*

**/config.routes.ini:**
Application routes, organized by request method (delete, get, patch, post, put, head, options, trace), listed as key-value (route-controller) pairs.

**/index.php:**
Initializes the application upon request, and routes requests to the proper controller. *It is not necessary to ever edit this file.*

## Installation and Setup

1.) Clone this repository

2.) Via the command line, CD into the project root directory and run `composer install`

## Env config files
- `domain`: must be a valid domain, like "localhost:3001" or "api.example.app"
- `ssl`: if set to true, the application will require all requests are made via https
- `origins`: array of allowed CORS origins, like "localhost:3000" or "www.example.app"

## Adding a new route and controller

1.) Open `/config.routes.ini`

2.) Below the proper *method* section, add the new *route* and set it equal to the *controller* file path
  * Routes accept named parameters (lowercase, regex [a-z0-9\-_] proceeded by a colon ":")
  * The `all` section which catch a request for any requested route that is NOT defined in the other method sections.
  * Specifically named routes must be listed before more vague dynamic routes (`/members/me` must come before `/members/:id`)
  * Controller file path must end in `.html` or `.php`
  * Example `/config.routes.ini`

```
...
[get]; Handle GET requests
/ = "app/documentation.html"
/me = "app/components/members/me/select/controller.php"
/members/:id = "app/components/members/select/controller.php"
...
```

3.) Add the new controller to your application
  * File must be an `.html` file (such as documentation), or a `.php` file
  * PHP files must return a single anonymous function which takes a single parameter `<?php return function ($app) { ... }; ?>`, and returns an array with one element `return array($httpStatusErrorCodeInt);` or two elements `return array($httpStatusSuccessCodeInt, $dataArray);`

```
<?php
	return function ($app) {
		$data = array('member' => array('id' => 'abc'));
		return array(200, array($data));
	};
?>
```

4.) The controller has access to the environment settings, named parameters, and request variables via the `$app` input array

```
echo $app['env']; // environment settings
echo $app['req']['params']; // vars extracted from named url parameters
echo $app['req']['body']; // request body
echo $app['req']['query']; // url parameters
echo $app['req']['cookie']; // cookies
echo $app['req']['ip']; // client ip address
echo $app['req']['agent']; // client user agent
```

## Serve the development application with PHP

1.) Open `/config.env.development.ini` and make sure to:
  * set `domain` to "localhost:3001"
  * set `ssl` to false
  * add any front-end domains (that your application will use to access the api) to the `origins` array, like "localhost:3000"

2.) Via the command line, CD into the project root directory and run `DEPLOYMENT=development php -d variables_order=EGPCS -S localhost:3001 index.php`

3.) Open a browser and navigate to "http://localhost:3001"

## Serve the production application with PHP

1.) Open `/config.env.production.ini` and make sure to:
  * set `domain` to your back-end domain, like "api.example.app"
  * set `ssl` to true
  * add any front-end domains (that your application will use to access the api) to the `origins` array, like "www.example.app"

2.) Make sure to set `$_ENV['DEPLOYMENT'] = 'production';` in your server config before serving your application (with Apache, in .htaccess: `SetEnv DEPLOYMENT production`, etc.).

3.) Serve your application

4.) Open a browser and navigate to your front-end domain, like "https://www.example.app"
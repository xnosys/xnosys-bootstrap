<?php
	
	# - PHP configuration:
	error_reporting(0);
	@ini_set('display_errors', 0);
	ignore_user_abort(1);
	date_default_timezone_set('UTC');
	
	# - Import:
	if (!file_exists(__DIR__.'/vendor/xnosys/xnosys-router/func.php')) { die('Run `composer install`'); }
	extract(array_map(function ($import) { return $import(); }, array(
		'__router' => require(__DIR__.'/vendor/xnosys/xnosys-router/func.php')
	)));
	
	# - Reformat server / request variables:
	$_ENV['DEPLOYMENT'] = isset($_ENV['DEPLOYMENT']) ? $_ENV['DEPLOYMENT'] : getenv('DEPLOYMENT');
	if (in_array(strtolower((string)$_SERVER['REQUEST_METHOD']), array('delete','patch','put')) || (strtolower($_SERVER['REQUEST_METHOD']) === 'post' && empty($_POST))) {
		$input = file_get_contents('php://input');
		$array = json_decode($input, true);
		if (!$array) {
			$array = array();
			parse_str($input, $array);
		}
		$_REQUEST = $array + $_REQUEST;
	}
	
	# - Route request:
	list ($error, $response) = $__router['route'](
		$_ENV['DEPLOYMENT'],
		((strtolower($_ENV['DEPLOYMENT']) === 'production' ) ? parse_ini_file(__DIR__.'/config.env.production.ini', true) : parse_ini_file(__DIR__.'/config.env.development.ini', true)),
		parse_ini_file(__DIR__.'/config.routes.ini', true),
		$_SERVER,
		$_REQUEST,
		$_GET,
		$_FILES,
		$_COOKIE,
		__DIR__
	); if (!!$error) { die($error); } exit();
	
?>
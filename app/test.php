<?php return function ($app) { return (call_user_func(function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - PHP configuration:
error_reporting(error_reporting()&~E_NOTICE);
set_time_limit(0);
ini_set('max_execution_time', 0);

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__fs' => $app['env']['root'].'/vendor/xnosys/xnosys-fs/func.php',
	'__xnostycs' => $app['env']['root'].'/vendor/xnosys/xnosys-xnostycs/func.php',
	'_config' => $app['env']['root'].'/app/common/config/'.$app['env']['deployment'].'.php'
)));

# - Add database credentials:
list($error, $xnostycs) = $__xnostycs['new'](
	$_config['dbs']['default']['host'],
	$_config['dbs']['default']['port'],
	$_config['dbs']['default']['char'],
	$_config['dbs']['default']['name'],
	$_config['dbs']['default']['user'],
	$_config['dbs']['default']['pass']
);
if (!!$error) { return array(503); }

//////////////////////////////////////////////////////////////////////////////////////////

# - Assign: load tests
$results = array(
	'tests' => array()
);
$tests = $__fs['globRecursive']($app['env']['root'].'/app/components/*/test.php');
foreach ($tests as $test) {
	list($error, $success) = $xnostycs['test']($xnostycs, require($test), $app);
	$results['tests'][str_replace($app['env']['root'], '', $test)] = ((!!$error) ? 'FAILURE: '.$error : 'SUCCESS');
}

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
list($error, $success) = $xnostycs['clean']();
return array(200, $results);

//////////////////////////////////////////////////////////////////////////////////////////

}, array(
	'env' => array(
		'deployment' => 'testing'
	)+$app['env'],
	'req' => array(
		'params' => array(),
		'body' => array(),
		'query' => array(),
		'cookie' => array(),
		'ip' => '::1',
		'agent' => 'xnostycs'
	)+$app['req']
)+$app)); }; ?>
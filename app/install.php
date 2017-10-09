<?php return function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__database' => $app['env']['root'].'/vendor/xnosys/xnosys-database/func.php',
	'__fs' => $app['env']['root'].'/vendor/xnosys/xnosys-fs/func.php',
	'__modelSlim' => $app['env']['root'].'/vendor/xnosys/xnosys-model-slim/func.php',
	'_config' => $app['env']['root'].'/app/common/config/'.$app['env']['deployment'].'.php'
)));

# - Add database credentials:
list($error, $db) = $__database['new']();
if (!!$error) { return array(503); }
$db['add'](
	'default',
	$_config['dbs']['default']['host'],
	$_config['dbs']['default']['port'],
	$_config['dbs']['default']['char'],
	$_config['dbs']['default']['name'],
	$_config['dbs']['default']['user'],
	$_config['dbs']['default']['pass']
);

//////////////////////////////////////////////////////////////////////////////////////////

# - Assign: load models with schemas
$schemas = $__fs['globRecursive']($app['env']['root'].'/app/components/*/schema.json');
foreach ($schemas as $schema) {
	list($error, $model) = $__modelSlim['load'](file_get_contents($schema));
	if (!!$error) { $db['close'](); return array(500); }
	list($error, $query) = $model['table']['create']();
	if (!!$error) { $db['close'](); return array(500); }
	list($error, $result) = $db['create']('default', $query['q'], $query['p']);
	if (!!$error) { $db['close'](); return array(500); }
}

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
return array(201);

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
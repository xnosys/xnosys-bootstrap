<?php return function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__database' => $app['env']['root'].'/vendor/xnosys/xnosys-database/func.php',
	'__modelSlim' => $app['env']['root'].'/vendor/xnosys/xnosys-model-slim/func.php',
	'_config' => $app['env']['root'].'/app/common/config/'.$app['env']['deployment'].'.php'
)));

# - Load schemas:
extract(array_map(function ($path) use ($__modelSlim) { list($error, $model) = $__modelSlim['load'](file_get_contents($path)); if (!!$error) { die(); } return $model; }, array(
	'profilesModel' => $app['env']['root'].'/app/components/profiles/schema.json'
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

# - Action: search profiles
list($error, $query, $limit, $offset, $params) = $profilesModel['table']['search'](
	$app['req']['body']['limit'],
	$app['req']['body']['offset'],
	(is_array($app['req']['body']['fields']) ?: json_decode($app['req']['body']['fields'], true))
);
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['select']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

# - Assign:
$next = ((count($result) >= $limit) ? $app['env']['domain'].'/profiles?limit='.$limit.'&offset='.($offset+$limit).'&fields='.json_encode($params) : null);

# - Assign:
$profiles = array();
foreach ($result as $profile) {
	$profiles[] = $profilesModel['wrap']($profile);
}

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
return array(200, array(
	'profiles' => (call_user_func(function ($profiles) {
		for ($i = 0, $n = count($profiles), $_ = array(); $i < $n; $i++) {
			$_[] = $profiles[$i]['getAttrs']();
		}
		return $_;
	}, $profiles)),
	'next' => $next
));

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
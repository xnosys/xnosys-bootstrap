<?php return function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__database' => $app['env']['root'].'/vendor/xnosys/xnosys-database/func.php',
	'__modelSlim' => $app['env']['root'].'/vendor/xnosys/xnosys-model-slim/func.php',
	'_auth' => $app['env']['root'].'/app/common/functions/auth.php',
	'_upload' => $app['env']['root'].'/app/common/functions/upload.php',
	'_config' => $app['env']['root'].'/app/common/config/'.$app['env']['deployment'].'.php'
)));

# - Load schemas:
extract(array_map(function ($path) use ($__modelSlim) { list($error, $model) = $__modelSlim['load'](file_get_contents($path)); if (!!$error) { die(); } return $model; }, array(
	'membersModel' => $app['env']['root'].'/app/components/members/schema.json',
	'sessionsModel' => $app['env']['root'].'/app/components/sessions/schema.json',
	'xMembersSessionsModel' => $app['env']['root'].'/app/components/x_members_sessions/schema.json',
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

# - Authentication:
list($error, $me, $session, $xMemberSession) = $_auth(
	$app,
	$db,
	$membersModel,
	$sessionsModel,
	$xMembersSessionsModel
);
if (!!$error) { $db['close'](); return array($error); }

//////////////////////////////////////////////////////////////////////////////////////////

# - Checks: verify email_confirmed
list($error, $success) = $me['verify'](array('email_confirmed' => true));
if (!!$error) { $db['close'](); return array(401); }

# - Action: select profile by me:id
list($error, $result) = $db['select'](
	'default',
	'SELECT * FROM `'.$profilesModel['table']['getName']().'` WHERE id=:id',
	array(':id' => $me['getAttr']('id'))
);
if (!!$error) { $db['close'](); return array(500); }
if (count($result) !== 1) { $db['close'](); return array(404); }
$profile = $profilesModel['wrap']($result[0]);

# - Checks:
if (isset($app['req']['body']['background']) || (isset($app['req']['files']['background']) && isset($app['req']['files']['background']['tmp_name']))) {
	
	# - Assign: edit background
	list($error, $query) = $profile['edit'](array(
		'background' => ((isset($app['req']['files']['background']) && isset($app['req']['files']['background']['tmp_name'])) ? call_user_func(function ($_config, $_upload, $app) {
			list($error, $url) = $_upload(
				$_config,
				$app,
				$app['req']['files']['background']['tmp_name'],
				hash('sha512', microtime().rand()).'.'.strtolower(pathinfo($app['req']['files']['background']['name'], PATHINFO_EXTENSION))
			);
			if (!!$error) { return ''; }
			return $url;
		}, $_config, $_upload, $app) : (isset($app['req']['body']['background']) ? $app['req']['body']['background'] : ''))
	));
	if (!!$error) { $db['close'](); return array(400); }
	
	# - Action: update datbase
	list($error, $result) = $db['update']('default', $query['q'], $query['p']);
	if (!!$error) { $db['close'](); return array(500); }
	
}

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
return array(201, array(
	'profile' => $profile['getAttrs']()
));

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
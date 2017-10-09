<?php return function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - Disabled:
return array(501); exit();

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__database' => $app['env']['root'].'/vendor/xnosys/xnosys-database/func.php',
	'__modelSlim' => $app['env']['root'].'/vendor/xnosys/xnosys-model-slim/func.php',
	'_auth' => $app['env']['root'].'/app/common/functions/auth.php',
	'_config' => $app['env']['root'].'/app/common/config/'.$app['env']['deployment'].'.php'
)));

# - Load schemas:
extract(array_map(function ($path) use ($__modelSlim) { list($error, $model) = $__modelSlim['load'](file_get_contents($path)); if (!!$error) { die(); } return $model; }, array(
	'membersModel' => $app['env']['root'].'/app/components/members/schema.json',
	'sessionsModel' => $app['env']['root'].'/app/components/sessions/schema.json',
	'xMembersSessionsModel' => $app['env']['root'].'/app/components/x_members_sessions/schema.json'
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

# - Checks: password correct
list($error, $success) = $me['verify'](array(
	'password' => $app['req']['body']['password']
));
if (!!$error) { $db['close'](); return array(401); }

# - Action: unauthenticate all member sessions
list($error, $result) = $db['update'](
	'default',
	'UPDATE `'.$xMembersSessionsModel['table']['getName']().'` SET status=:status WHERE member_id=:id',
	array(
		':status' => 0,
		':id' => $me['getAttr']('id')
	)
);
if (!!$error) { $db['close'](); return array(500); }

# - Action: delete member
list($error, $me, $query) = $me['destroy']();
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['delete']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
return array(204);

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
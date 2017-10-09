<?php return function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__database' => $app['env']['root'].'/vendor/xnosys/xnosys-database/func.php',
	'__modelSlim' => $app['env']['root'].'/vendor/xnosys/xnosys-model-slim/func.php',
	'__string' => $app['env']['root'].'/vendor/xnosys/xnosys-string/func.php',
	'__uuid' => $app['env']['root'].'/vendor/xnosys/xnosys-uuid/func.php',
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
	'password' => $app['req']['body']['oldpassword']
));
if (!!$error) { $db['close'](); return array(401); }

# - Action: build member update query
list($error, $query) = $me['edit'](array(
	'password' => $app['req']['body']['newpassword'],
	'password_code_datetime' => '0000-00-00 00:00:00'
));
if (!!$error) { $db['close'](); return array(400); }

# - update database
list($error, $result) = $db['update']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

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

# - Assign: create a new session and insert into database
$code_salt = $__string['random'](16, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
$code = $__string['random'](24, 'abcdefghijklmnopqrstuvwxyz0123456789');
list($error, $session, $query) = $sessionsModel['new'](array(
	'id' => $__uuid['generate']($app['req']['ip'], getmyinode()),
	'name' => 'password',
	'code_salt' => $code_salt,
	'code_hash' => $code_salt.$code,
	'ip' => $app['req']['ip'],
	'agent' => $app['req']['agent']
));
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['insert']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

# - Assign: create a new x_member_session and insert into database
list($error, $xMemberSession, $query) = $xMembersSessionsModel['new'](array(
	'member_id' => $me['getAttr']('id'),
	'session_id' => $session['getAttr']('id'),
	'status' => true
));
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['insert']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
return array(200, array(
	'_xa' => $__uuid['pack']($me['getAttr']('id')).$__uuid['pack']($session['getAttr']('id')).$code
));

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
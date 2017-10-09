<?php return function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__database' => $app['env']['root'].'/vendor/xnosys/xnosys-database/func.php',
	'__modelSlim' => $app['env']['root'].'/vendor/xnosys/xnosys-model-slim/func.php',
	'__string' => $app['env']['root'].'/vendor/xnosys/xnosys-string/func.php',
	'__uuid' => $app['env']['root'].'/vendor/xnosys/xnosys-uuid/func.php',
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

//////////////////////////////////////////////////////////////////////////////////////////

# - Action: select member with provided email address
list($error, $result) = $db['select'](
	'default',
	'SELECT * FROM `'.$membersModel['table']['getName']().'` WHERE email=:email',
	array(':email' => $app['req']['body']['email'])
);
if (!!$error) { $db['close'](); return array(500); }

# - Checks: ensures a single matching account
if (count($result) !== 1) { $db['close'](); return array(404); }

# - Assign: wrap db result attributes as member object
$member = $membersModel['wrap']($result[0]);

# - Checks: verify activated
list($error, $success) = $member['verify'](array('activated' => true));
if (!!$error) { $db['close'](); return array(404); }

# - verify member attributes
list($error, $success) = $member['verify'](array(
	'password' => $app['req']['body']['password']
));
if (!!$error) { $db['close'](); return array(401); }

# - Assign: create a new session and insert into database
$code_salt = $__string['random'](16, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
$code = $__string['random'](24, 'abcdefghijklmnopqrstuvwxyz0123456789');
list($error, $session, $query) = $sessionsModel['new'](array(
	'id' => $__uuid['generate']($app['req']['ip'], getmyinode()),
	'name' => 'login',
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
	'member_id' => $member['getAttr']('id'),
	'session_id' => $session['getAttr']('id'),
	'status' => true
));
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['insert']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
return array(201, array(
	'_xa' => $__uuid['pack']($member['getAttr']('id')).$__uuid['pack']($session['getAttr']('id')).$code
));

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
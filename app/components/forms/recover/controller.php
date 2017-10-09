<?php return function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__database' => $app['env']['root'].'/vendor/xnosys/xnosys-database/func.php',
	'__modelSlim' => $app['env']['root'].'/vendor/xnosys/xnosys-model-slim/func.php',
	'__string' => $app['env']['root'].'/vendor/xnosys/xnosys-string/func.php',
	'__uuid' => $app['env']['root'].'/vendor/xnosys/xnosys-uuid/func.php',
	'_mail' => $app['env']['root'].'/app/common/functions/mail.php',
	'_config' => $app['env']['root'].'/app/common/config/'.$app['env']['deployment'].'.php'
)));

# - Load schemas:
extract(array_map(function ($path) use ($__modelSlim) { list($error, $model) = $__modelSlim['load'](file_get_contents($path)); if (!!$error) { die(); } return $model; }, array(
	'membersModel' => $app['env']['root'].'/app/components/members/schema.json'
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

# - Action: update member password code
$password_code_salt = $__string['random'](16, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
$password_code = $__string['random'](24, 'abcdefghijklmnopqrstuvwxyz0123456789');
list($error, $query) = $member['edit'](array(
	'password_code_salt' => $password_code_salt,
	'password_code_hash' => $password_code_salt.$password_code,
	'password_code_datetime' => date('Y-m-d H:i:s')
));
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['update']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

# - Action: send recovery email
$link = $_config['links']['wwwReset'].'?_xr='.$__uuid['pack']($member['getAttr']('id')).$password_code;
list($error, $result) = $_mail($_config, $app, array(
	'name' => $_config['siteName'],
	'from' => $_config['mail']['templates']['default']['address'],
	'to' => $member['getAttr']('email'),
	'subject' => 'Password Reset',
	'text' => 'To reset your password, please click the following link:'
		."\r\n".$link
		."\r\n\r\n".'If clicking the link does not work, please copy and paste it into the browser window address bar.'
		."\r\n\r\n".'If you did not request a password reset, you can safely ignore this email.'
		."\r\n\r\n".'Thank you,'
		."\r\n\r\n".'- '.$_config['mail']['templates']['default']['signature']
));
if (!!$error) { $db['close'](); return array($error); }

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
if ($app['env']['deployment'] === 'development' || $app['env']['deployment'] === 'testing') {
	return array(200, array(
		'_xr' => $__uuid['pack']($member['getAttr']('id')).$password_code
	));
} else {
	return array(200);
}

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
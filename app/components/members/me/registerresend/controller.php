<?php return function ($app) {

//////////////////////////////////////////////////////////////////////////////////////////

# - Import packages and configurations:
extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
	'__database' => $app['env']['root'].'/vendor/xnosys/xnosys-database/func.php',
	'__modelSlim' => $app['env']['root'].'/vendor/xnosys/xnosys-model-slim/func.php',
	'__string' => $app['env']['root'].'/vendor/xnosys/xnosys-string/func.php',
	'__uuid' => $app['env']['root'].'/vendor/xnosys/xnosys-uuid/func.php',
	'_auth' => $app['env']['root'].'/app/common/functions/auth.php',
	'_mail' => $app['env']['root'].'/app/common/functions/mail.php',
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

# - Checks: email not confirmed
list($error, $success) = $me['verify'](array(
	'email_confirmed' => false
));
if (!!$error) { $db['close'](); return array(400); }

# - Action: update member email and password code
$email_code_salt = $__string['random'](16, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
$email_code = $__string['random'](24, 'abcdefghijklmnopqrstuvwxyz0123456789');
$password_code_salt = $__string['random'](16, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
$password_code = $__string['random'](24, 'abcdefghijklmnopqrstuvwxyz0123456789');
list($error, $query) = $me['edit'](array(
	'email_code_salt' => $email_code_salt,
	'email_code_hash' => $email_code_salt.$email_code,
	'email_code_datetime' => date('Y-m-d H:i:s'),
	'password_code_salt' => $password_code_salt,
	'password_code_hash' => $password_code_salt.$password_code,
	'password_code_datetime' => date('Y-m-d H:i:s')
));
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['update']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

# - Action: resend welcome email
$link = $_config['links']['wwwVerification'].'?_xv='.$__uuid['pack']($me['getAttr']('id')).$email_code.'&_xr='.$__uuid['pack']($me['getAttr']('id')).$password_code;
list($error, $result) = $_mail($_config, $app, array(
	'name' => $_config['siteName'],
	'from' => $_config['mail']['templates']['default']['address'],
	'to' => $me['getAttr']('email'),
	'subject' => 'Welcome!',
	'text' => 'Please click the following link to confirm your email address and activate your account:'
		."\r\n".$link
		."\r\n\r\n".'If clicking the link does not work, please copy and paste it into the browser window address bar.'
		."\r\n\r\n".'Thank you,'
		."\r\n\r\n".'- '.$_config['mail']['templates']['default']['signature']
));
if (!!$error) { $db['close'](); return array($error); }

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
if ($app['env']['deployment'] === 'development' || $app['env']['deployment'] === 'testing') {
	return array(200, array(
		'_xv' => $__uuid['pack']($me['getAttr']('id')).$email_code,
		'_xr' => $__uuid['pack']($me['getAttr']('id')).$password_code
	));
} else {
	return array(200);
}

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
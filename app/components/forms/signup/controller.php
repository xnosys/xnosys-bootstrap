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

//////////////////////////////////////////////////////////////////////////////////////////

# - Assign: create a new member
$email_code_salt = $__string['random'](16, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
$email_code = $__string['random'](24, 'abcdefghijklmnopqrstuvwxyz0123456789');
list($error, $member, $query) = $membersModel['new'](array(
	'id' => $__uuid['generate']($app['req']['ip'], getmyinode()),
	'email' => $app['req']['body']['email'],
	'email_code_salt' => $email_code_salt,
	'email_code_hash' => $email_code_salt.$email_code,
	'email_code_datetime' => date('Y-m-d H:i:s'),
	'password' => $app['req']['body']['password'],
	'activated' => true
));
if (!!$error) { $db['close'](); return array(400); }

# - Checks: make sure email address is not taken
list($error, $result) = $db['select'](
	'default',
	'SELECT * FROM `'.$membersModel['table']['getName']().'` WHERE email=:email',
	array(':email' => $member['getAttr']('email'))
);
if (!!$error) { $db['close'](); return array(500); }
# - Checks:
if (count($result) > 0) {
	# - Assign:
	$member = $membersModel['wrap']($result[0]);
	# - Checks: verify not activated
	list($error, $success) = $member['verify'](array('activated' => false));
	if (!!$error) {
		$db['close']();
		return array(403);
	} else {
		# - Action: update member
		list($error, $query) = $member['edit'](array(
			'email_code_salt' => $email_code_salt,
			'email_code_hash' => $email_code_salt.$email_code,
			'email_code_datetime' => date('Y-m-d H:i:s'),
			'password' => $app['req']['body']['password'],
			'activated' => true
		));
		if (!!$error) { $db['close'](); return array(400); }
		list($error, $result) = $db['update']('default', $query['q'], $query['p']);
		if (!!$error) { $db['close'](); return array(500); }
	}
} else {
	# - Action: insert member into database
	list($error, $result) = $db['insert']('default', $query['q'], $query['p']);
	if (!!$error) { $db['close'](); return array(500); }
	# - Assign: create a new profile
	list($error, $profile, $query) = $profilesModel['new'](array(
		'id' => $member['getAttr']('id')
	));
	if (!!$error) { $db['close'](); return array(400); }
	list($error, $result) = $db['insert']('default', $query['q'], $query['p']);
	if (!!$error) { $db['close'](); return array(500); }
}

# - Action: send verification email
$link = $_config['links']['wwwVerification'].'?_xv='.$__uuid['pack']($member['getAttr']('id')).$email_code;
list($error, $result) = $_mail($_config, $app, array(
	'name' => $_config['siteName'],
	'from' => $_config['mail']['templates']['default']['address'],
	'to' => $member['getAttr']('email'),
	'subject' => 'Email Verification',
	'text' => 'Please verify your email address by clicking the following link:'
		."\r\n".$link
		."\r\n\r\n".'If clicking the link does not work, please copy and paste it into the browser window address bar.'
		."\r\n\r\n".'Thank you,'
		."\r\n\r\n".'- '.$_config['mail']['templates']['default']['signature']
));
if (!!$error) { $db['close'](); return array($error); }

# - Assign: create a new session and insert into database
$code_salt = $__string['random'](16, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
$code = $__string['random'](24, 'abcdefghijklmnopqrstuvwxyz0123456789');
list($error, $session, $query) = $sessionsModel['new'](array(
	'id' => $__uuid['generate']($app['req']['ip'], getmyinode()),
	'name' => 'signup',
	'code_salt' => $code_salt,
	'code_hash' => $code_salt.$code,
	'ip' => $app['req']['ip'],
	'agent' => $app['req']['agent']
));
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['insert']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

# - Assign: create a new x_member_session and insert into database
list($error, $x, $query) = $xMembersSessionsModel['new'](array(
	'member_id' => $member['getAttr']('id'),
	'session_id' => $session['getAttr']('id'),
	'status' => true
));
if (!!$error) { $db['close'](); return array(400); }
list($error, $result) = $db['insert']('default', $query['q'], $query['p']);
if (!!$error) { $db['close'](); return array(500); }

//////////////////////////////////////////////////////////////////////////////////////////

# - Return:
if ($app['env']['deployment'] === 'development' || $app['env']['deployment'] === 'testing') {
	return array(201, array(
		'_xa' => $__uuid['pack']($member['getAttr']('id')).$__uuid['pack']($session['getAttr']('id')).$code,
		'_xv' => $__uuid['pack']($member['getAttr']('id')).$email_code
	));
} else {
	return array(201, array(
		'_xa' => $__uuid['pack']($member['getAttr']('id')).$__uuid['pack']($session['getAttr']('id')).$code
	));
}

//////////////////////////////////////////////////////////////////////////////////////////

}; ?>
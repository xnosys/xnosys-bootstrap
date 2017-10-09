<?php
	
	return function () {
		
		return function ($app, &$db, $membersModel, $sessionsModel, $xMembersSessionsModel) {
			
			# - Import:
			extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
				'__uuid' => $app['env']['root'].'/vendor/xnosys/xnosys-uuid/func.php'
			)));
			
			# - Action: authentication
			list($error, $result) = $db['select'](
				'default',
				'SELECT * FROM `'.$membersModel['table']['getName']().'` WHERE id=:id',
				array(':id' => $__uuid['unpack'](substr($app['req']['body']['_xa'], 0, 43)))
			);
			if (!!$error) { $db['close'](); return array(500); }
			if (count($result) !== 1) { $db['close'](); return array(404); }
			$me = $membersModel['wrap']($result[0]);
			list($error, $result) = $db['select'](
				'default',
				'SELECT * FROM `'.$sessionsModel['table']['getName']().'` WHERE id=:id',
				array(':id' => $__uuid['unpack'](substr($app['req']['body']['_xa'], 43, 43)))
			);
			if (!!$error) { $db['close'](); return array(500); }
			if (count($result) !== 1) { $db['close'](); return array(404); }
			$session = $sessionsModel['wrap']($result[0]);
			if (time() - strtotime($__uuid['toDatetime']($session['getAttr']('id'))) > 30*86400) { $db['close'](); return array(403); }
			list($error, $success) = $session['verify'](array(
				'code_hash' => $session['getAttr']('code_salt').substr($app['req']['body']['_xa'], 86)
			));
			if (!!$error) { $db['close'](); return array(401); }
			list($error, $result) = $db['select'](
				'default',
				'SELECT * FROM `'.$xMembersSessionsModel['table']['getName']().'` WHERE member_id=:member_id AND session_id=:session_id',
				array(':member_id' => $me['getAttr']('id'), ':session_id' => $session['getAttr']('id'))
			);
			if (!!$error) { $db['close'](); return array(500); }
			if (count($result) !== 1) { $db['close'](); return array(404); }
			$xMemberSession = $xMembersSessionsModel['wrap']($result[0]);
			list($error, $success) = $xMemberSession['verify'](array(
				'status' => true
			));
			if (!!$error) { $db['close'](); return array(401); }
			
			# - Checks: last day used
			if (intval(date('Ymd')) - intval($session['getAttr']('used')) > 0) {
				# - Assign: edit session
				list($error, $query) = $session['edit'](array(
					'used' => intval(date('Ymd'))
				));
				if (!!$error) { $db['close'](); return array(400); }
				# - Action: update datbase
				list($error, $result) = $db['update']('default', $query['q'], $query['p']);
				if (!!$error) { $db['close'](); return array(500); }
			}
			
			# - Return:
			return array(null, $me, $session, $xMemberSession);
			
		};
		
	};
	
?>
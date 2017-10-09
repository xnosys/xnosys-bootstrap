<?php
	
	return function () {
		
		return function ($_config, $app, $email) {
			
			# - Import:
			extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
				'__log' => $app['env']['root'].'/vendor/xnosys/xnosys-log/func.php',
				'__mail' => $app['env']['root'].'/vendor/xnosys/xnosys-mail/func.php'
			)));
			
			# - Checks:
			if ($app['env']['deployment'] === 'production') {
				list($error, $result) = $__mail['send']($email, $_config['mail']['service'], $_config['mail']['credentials'][$_config['mail']['service']]);
				if (!!$error) { return array($error); }
			} else {
				if ($app['env']['deployment'] !== 'testing') {
					if (!$__log['write'](json_encode($email), 'mail')) { return array(500); }
				}
			}
			
			# - Return:
			return array(null, true);
			
		};
		
	};
	
?>
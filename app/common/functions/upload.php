<?php
	
	return function () {
		
		return function ($_config, $app, $file, $saveas) {
			
			# - Import: upload service
			// extract(array_map(function ($path) { $import = require($path); return is_callable($import) ? $import() : $import; }, array(
			// )));
			
			# - Checks:
			$object = '';
			if ($app['env']['deployment'] === 'production') {
				$object = $saveas; // results of upload service
				if (!!$error) { return array($error); }
			} else {
				$object = $saveas;
			}
			
			# - Return:
			return array(null, $object);
			
		};
		
	};
	
?>
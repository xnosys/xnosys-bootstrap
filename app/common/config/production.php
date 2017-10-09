<?php
	
	return array(
		'siteName' => '',
		'links' => array(
			'www' => '',
			'wwwReset' => '',
			'wwwVerification' => ''
		),
		'mail' => array(
			'service' => false,
			'credentials' => array(
				'mailgun' => array(
					'key' => '',
					'username' => ''
				)
			),
			'templates' => array(
				'default' => array(
					'address' => '',
					'signature' => ''
				)
			)
		),
		'dbs' => array(
			'default' => array(
				'host' => '',
				'port' => '',
				'char' => '',
				'name' => '',
				'user' => '',
				'pass' => ''
			)
		)
	);
	
?>
<?php

/* Function autoload package classes */
function class_autoload( $className ) {
	// Split the class name into parts using the namespace separator
	$parts = explode('\\', $className);
	// if not Bitweaver namespace kick out
	if ( !($parts[0] == 'Bitweaver') ) return;

	// Ensure there are at least three parts (namespace, package, class)
	if (count($parts) == 2) {
		$parts[2] = $parts[1];
		$parts[1] = 'kernel/includes/classes/';
	} elseif ($parts[1] == 'Plugins' ) {
		$parts[1] = 'themes/smartyplugins/';
	} elseif (count($parts) <> 3) {
		return;
	} else {
		$parts[1] .= '/includes/classes/';
	}

	$base_dir = BIT_ROOT_PATH . strtolower( $parts[1] );

	$file = $base_dir . $parts[2] . '.php';

	if (file_exists($file)) {
		require $file;
	}
}

spl_autoload_register('class_autoload');
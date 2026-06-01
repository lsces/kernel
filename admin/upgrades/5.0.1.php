<?php
/**
 * @package kernel
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => 'kernel',
		'version'     => '5.0.1',
		'description' => 'Bump stored bitweaver_version to 5.0.1',
	],
	[
		[ 'PHP' => '$this->storeVersion( null, \'5.0.1\' );' ],
	]
);

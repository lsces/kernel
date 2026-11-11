<?php
/**
 * @package kernel
 */

global $gBitInstaller;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => 'kernel',
		'version'     => '5.1.1',
		'description' => 'Bump stored bitweaver_version to 5.1.1',
	],
	[
		[ 'PHP' => '$this->storeVersion( null, \'5.1.1\' );' ],
	]
);

<?php

// $Header$

// Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See below for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.

// Process Packages form
$fPackage = &$_REQUEST['fPackage'];   // emulate register_globals

# rescan to include all packages, installed and not installed
$gBitSystem->scanPackages(
	'bit_setup_inc.php', true, 'all', true, true,
);

// make a copy of mPackages - expensive, but this is low use code

if( !empty( $_REQUEST['features'] ) ) {
	$pkgArray = $gBitSystem->mPackages;
	foreach( array_keys( $pkgArray ) as $pkgKey ) {
		$pkg = $pkgArray[$pkgKey];
		if( !empty( $pkg['name'] )) {
			$pkgName = strtolower( $pkg['name'] );
			// can only change already installed packages that are not required
			if( $gBitSystem->isPackageInstalled( $pkgName ) && empty( $pkg['required'] )) {
				if( isset( $_REQUEST['fPackage'][$pkgName] )) {
					// mark installed and active
					$gBitSystem->storeConfig( 'package_'.$pkgName, 'y', $pkgName );
					unset( $pkgArray[$pkgKey] );
				} else {
					// mark installed but not active
					$gBitSystem->storeConfig( 'package_'.$pkgName, 'i', $pkgName );
					unset( $pkgArray[$pkgKey] );
				}
			}
		}
	}
}

// Detect whether the installer is accessible (symlink present on production sites)
$gBitSmarty->assign( 'install_pkg_accessible', is_readable( BIT_ROOT_PATH . 'install/install.php' ) );

global $gBitInstaller;
$gBitInstaller = &$gBitSystem;
$gBitInstaller->verifyInstalledPackages();
$gBitSmarty->assign( 'requirements', $gBitInstaller->calculateRequirements( true ) );
$gBitSmarty->assign( 'requirementsMap', $gBitInstaller->drawRequirementsGraph( true, 'cmapx' ));

$upgradable = [];
foreach( $gBitSystem->mPackages as $name => &$pkg ) {
	if( $gBitSystem->isPackageInstalled( $name ) && !empty( $pkg['info']['upgrade'] )) {
		// A pending upgrade's steps may have nothing to do with this package's own
		// registered tables at all (a QUERY-only change against another package's
		// tables, an xref registration, dropping a table down to none) - whether the
		// package currently owns any tables is not a valid signal for whether there's
		// real work to apply, so always surface it and let the real upgrade run.
		$upgradable[$name]['info']['version'] = $pkg['info']['version'];
		$upgradable[$name]['info']['upgrade'] = $pkg['info']['upgrade'];
	}
}

$gBitSmarty->assign( 'upgradable', $upgradable );

// So packages will be listed in alphabetical order
ksort( $gBitSystem->mPackages );

<?php
/**
 * @package kernel
 * @subpackage modules
 */
global $gBitSystem, $gBitUser, $moduleParams;

// if we're on any page in an admin/ dir, we'll simply set this to kernel
$admin = ( $gBitUser->isAdmin() ) ? preg_match( "!/admin/!", $_SERVER['SCRIPT_NAME'] ) : false;

$package = !empty($moduleParams->values['package'])?$moduleParams->values['package']:$gBitSystem->getActivePackage();

if( !empty( $gBitSystem->mAppMenu[$package]['menu_template'] ) && !$admin ) {
	$gBitSmarty->assign( 'packageMenu',  $gBitSystem->mAppMenu[$package]  );
}

if( empty( $module_title )) {
	$pkgName = constant( strtoupper( $package ).'_PKG_NAME' );

	if( $pkgName == 'kernel' || $admin ) {
		$pkgName = 'Administration';
	}
	$title = $gBitSystem->getConfig( "{$pkgName}_menu_text", ucfirst( $pkgName ));
	$gBitSmarty->assign( 'moduleTitle',  $title  );
}

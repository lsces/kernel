<?php

use Bitweaver\Liberty\LibertyStructure;
/**
 * $Header$
 *
 * Copyright ( c ) 2004 bitweaver.org
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 *
 * @package kernel
 * @subpackage modules
 */
if( !empty( $module_params ) ) {
	extract( $moduleParams );
	$gBitSmarty->assign( 'modParams', $module_params );
}

global $gStructure, $gContent;
if( !$gStructure and $gContent ) {
	$structs = $gContent->getStructures();
	if ( count($structs)  > 1 ) {
		$gStructure = new LibertyStructure( $structs[0]['structure_id'] );
		if( $gStructure->load() ) {
			$gStructure->loadNavigation();
			$gStructure->loadPath();
			$gBitSmarty->assign( 'structureInfo', $gStructure->mInfo );
		}
	}
}

if( $gStructure and !empty($gStructure->mInfo['structure_path']) ) {
	$secondbox = 0;
	$tree = 1;
	$gStructure->mInfo['structure_path'][0]['structure_id'];
	if( $gStructure->mInfo['parent']['structure_id'] == 4 ) $sidebox = $gStructure->mInfo['content_id'] - 3;
	elseif( $gStructure->mInfo['parent']['content_id'] > 4 ) $sidebox = $gStructure->mInfo['parent']['content_id'] - 3;
	else $sidebox = 1;
	if( $gStructure->mInfo['content_id'] != 4 ) {
		$menu = $gStructure->buildTreeToc( $tree );
		$gBitSmarty->assign( 'menu', $menu[0]['sub'] );
		$gBitSmarty->assign( 'sidebox', $sidebox );
		if ($secondbox) {
			$secondmenu = $gStructure->buildTreeToc( $secondbox );
			$gBitSmarty->assign( 'secondmenu', $secondmenu[0]['sub'] );
		}
	}
} else {
	$gStructure = new LibertyStructure( 1 );
	$menu = $gStructure->buildTreeToc( 1 );
	$gBitSmarty->assign( 'menu', $menu[0]['sub'] );
}
<?php
/**
 * Base class for all objects where only one object should be created
 *
 * @version $Header$
 *
 * Copyright (c) 2004 bitweaver.org
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 *
 * Virtual base class (as much as one can have such things in PHP) for all
 * derived tikiwiki classes that require database access.
 *
 * created 2004/8/15
 *
 * @author spider <spider@steelsun.com>
 * @package  kernel
 */

namespace Bitweaver;

/**
 * required setup
 */

/**
 * @package  kernel
 */
abstract class BitSingleton extends BitBase implements BitCacheable {

	protected static $singletons = null;

	function __construct() {
		parent::__construct();
	}

	public static function loadSingleton( $pVarName = null ) {
		$class = static::getClass();
		$split = explode('\\', $class);
		$globalVarName = !empty( $pVarName ) ? $pVarName : ( isset($split[2]) ? 'g'.$split[2] : 'g'.$split[1] );
		global $$globalVarName;

		if( !($$globalVarName = static::loadFromCache( 'Singleton' )) ) {
			$$globalVarName = new $class();
		}
		if(!isset(static::$singletons[$globalVarName])) {
			static::$singletons[$globalVarName] = $$globalVarName;
			global $gBitSmarty;
			if( $gBitSmarty ) {
				$gBitSmarty->assign( $globalVarName, $$globalVarName );
			}
		}
		return static::$singletons[$globalVarName];
	}

	public function getCacheKey() {
		return 'Singleton';
	}

	public static function isCacheableClass() {
		return true;
	}

	public function isCacheableObject() {
		return true;
	}

}
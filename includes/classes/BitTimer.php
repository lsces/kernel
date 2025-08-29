<?php
/**
 * @version $Header$
 * Basic processes timer
 *
 * @package kernel
 */

 namespace Bitweaver;

/**
 * @package kernel
 */
class BitTimer {
	/**
	 * Store of active timers
	 * @public
	 */
	public $mTimer = [];

	public function parseMicro( $micro ) {
		list( $micro, $sec ) = explode( ' ', microtime() );
		return $sec + $micro;
	}

	public function start( $timer = 'default' ) {
		$this->mTimer[$timer] = $this->parseMicro( microtime() );
	}

	public function current( $timer = 'default' ) {
		return $this->mTimer[$timer] ?? 0;
	}

	public function stop( $timer = 'default' ) {
		return $this->current( $timer );
	}

	public function elapsed( $timer = 'default' ) {
		return $this->parseMicro( microtime() ) - $this->mTimer[$timer];
	}
}

/**
 * Create timer
 */
global $gBitTimer;
if (!isset($gBitTimer)) {
	$gBitTimer = new BitTimer();
	$gBitTimer->start();
}
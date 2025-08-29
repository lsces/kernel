<?php
/**
 * @version $Header$
 * @package kernel
 * @subpackage functions
 */

/**
 * required setup
 */
namespace Bitweaver;
require_once '../kernel/includes/setup_inc.php';

// Display the template
$gBitSmarty->assign( 'msg', strip_tags( $_REQUEST["error"] ));
$gBitSystem->display( 'error.tpl' , KernelTools::tra( 'Error' ), array( 'display_mode' => 'display' ));

<?php

$tables = [

'adodb_logsql' => "
	created T NOT null,
	sql0 C(250) NOTNULL,
	sql1 X NOTNULL,
	params X NOTNULL,
	tracer X NOTNULL,
	timer N(16.6) NOTNULL
",

'kernel_config' => "
	config_name C(40) PRIMARY,
	package C(100),
	config_value C(250)
",

'mail_notifications' => "
	event C(200),
	object C(200),
	email C(200)
",

'sessions' => "
	sesskey C(32) PRIMARY,
	expiry I NOTNULL,
	expireref C(64),
	session_data X not null
",

];

global $gBitInstaller;

foreach( array_keys( $tables ) AS $tableName ) {
	$gBitInstaller->registerSchemaTable( KERNEL_PKG_NAME, $tableName, $tables[$tableName], true );
}

$gBitInstaller->registerPackageInfo( KERNEL_PKG_NAME, [
	'description' => "This is the heart of the application. Without this --&gt; nothing.",
	'license' => '<a href="http://www.gnu.org/licenses/licenses.html#LGPL">LGPL</a>',
] );

// Default Preferences
$gBitInstaller->registerPreferences( KERNEL_PKG_NAME, [
	[ KERNEL_PKG_NAME, 'site_online_help'            , 'y' ],
	[ KERNEL_PKG_NAME, 'site_edit_help'              , 'y' ],
	[ KERNEL_PKG_NAME, 'site_form_help'              , 'y' ],
	[ KERNEL_PKG_NAME, 'site_short_date_format'      , '%d %b %Y' ],
	[ KERNEL_PKG_NAME, 'site_short_time_format'      , '%H:%M %Z' ],
	[ KERNEL_PKG_NAME, 'site_upload_dir'             , 'storage' ],
	[ KERNEL_PKG_NAME, 'site_closed_msg'             , 'Site is closed for maintainance; please come back later.' ],
	[ KERNEL_PKG_NAME, 'site_http_port'              , '80' ],
	[ KERNEL_PKG_NAME, 'site_http_prefix'            , '/' ],
	[ KERNEL_PKG_NAME, 'site_https_port'             , '443' ],
	[ KERNEL_PKG_NAME, 'site_https_prefix'           , '/' ],
	[ KERNEL_PKG_NAME, 'users_count_admin_pageviews' , 'y' ],
	[ KERNEL_PKG_NAME, 'site_display_utc'            , 'UTC' ],
	[ KERNEL_PKG_NAME, 'site_long_date_format'       , '%A %d of %B, %Y' ],
	[ KERNEL_PKG_NAME, 'site_long_time_format'       , '%H:%M:%S %Z' ],
	[ KERNEL_PKG_NAME, 'site_top_column'             , 'y' ],
	[ KERNEL_PKG_NAME, 'site_right_column'           , 'y' ],
	[ KERNEL_PKG_NAME, 'site_left_column'            , 'y' ],
	[ KERNEL_PKG_NAME, 'site_bottom_column'          , 'y' ],
	[ KERNEL_PKG_NAME, 'site_display_reltime'        , 'y' ],
	[ KERNEL_PKG_NAME, 'max_records'                 , '10' ],
	[ KERNEL_PKG_NAME, 'language'                    , 'en' ],
	[ KERNEL_PKG_NAME, 'site_sender_email'           , '' ],
	[ KERNEL_PKG_NAME, 'site_url_index'              , '' ],
] );

$moduleHash = [
	[
		'title' => null,
		'pos' => 5,
		'layout_area' => 't',
		'module_rsrc' => 'bitpackage:kernel/mod_site_title.tpl',
	],
	[
		'title' => null,
		'pos' => 10,
		'layout_area' => 't',
		'module_rsrc' => 'bitpackage:kernel/mod_top_menu.tpl',
	],
	[
		'title' => null,
		'pos' => 5,
		'layout_area' => 'r',
		'module_rsrc' => 'bitpackage:kernel/mod_package_menu.tpl',
	],
	[
		'title' => null,
		'pos' => 5,
		'layout_area' => 'b',
		'module_rsrc' => 'bitpackage:kernel/mod_bottom_bar.tpl',
	],
];
$gBitInstaller->registerModules( $moduleHash );

// ### Default UserPermissions
$gBitInstaller->registerUserPermissions( KERNEL_PKG_NAME, [
	[ 'p_admin'              , 'Can manage users groups and permissions and all aspects of site management' , 'admin' , KERNEL_PKG_NAME ],
	[ 'p_access_closed_site' , 'Can access site when closed'                                                , 'admin' , KERNEL_PKG_NAME ]
] );

// Package requirements
$gBitInstaller->registerRequirements( KERNEL_PKG_NAME, [
	'liberty'   => [ 'min' => '5.0.0' ],
	'users'     => [ 'min' => '5.0.0' ],
	'themes'    => [ 'min' => '5.0.0' ],
	'languages' => [ 'min' => '5.0.0' ],
] );

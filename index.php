<?php
error_reporting( E_ALL );
ini_set( 'display_errors', true );

require_once './ENV/CREDENTIALS.php';
include_once './routing/Route.php';

Route::add( '/metadata', function() {
	include './metadata/metadata.php';
});

Route::run( '/' );

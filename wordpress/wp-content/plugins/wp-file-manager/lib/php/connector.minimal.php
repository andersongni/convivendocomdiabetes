<?php

error_reporting(0); // Set E_ALL for debuging

// load composer autoload before load elFinder autoload If you need composer
require '../vendor/autoload.php';
//require '../vendor/nao-pon/flysystem-google-drive/src/GoogleDriveAdapter.php';
// elFinder autoload
require './autoload.php';
// https://www.dropbox.com/developers/apps
// ===============================================
elFinder::$netDrivers['dropbox2'] = 'Dropbox2';
define('ELFINDER_DROPBOX_APPKEY', getenv('ELFINDER_DROPBOX_APPKEY') ?: '');
define('ELFINDER_DROPBOX_APPSECRET', getenv('ELFINDER_DROPBOX_APPSECRET') ?: '');
elFinder::$netDrivers['googledrive'] = 'GoogleDrive';
define('ELFINDER_GOOGLEDRIVE_CLIENTID', getenv('ELFINDER_GOOGLEDRIVE_CLIENTID') ?: '');
define('ELFINDER_GOOGLEDRIVE_CLIENTSECRET', getenv('ELFINDER_GOOGLEDRIVE_CLIENTSECRET') ?: '');
/**
 * Simple function to demonstrate how to control file access using "accessControl" callback.
 * This method will disable accessing files/folders starting from '.' (dot)
 *
 * @param  string    $attr    attribute name (read|write|locked|hidden)
 * @param  string    $path    absolute file path
 * @param  string    $data    value of volume option `accessControlData`
 * @param  object    $volume  elFinder volume driver object
 * @param  bool|null $isDir   path is directory (true: directory, false: file, null: unknown)
 * @param  string    $relpath file path relative to volume root directory started with directory separator
 * @return bool|null
 **/
function access($attr, $path, $data, $volume, $isDir, $relpath) {
	$basename = basename($path);
	return $basename[0] === '.'                  // if file/folder begins with '.' (dot)
			 && strlen($relpath) !== 1           // but with out volume root
		? !($attr == 'read' || $attr == 'write') // set read+write to false, other (locked+hidden) set to true
		:  null;                                 // else elFinder decide it itself
}


// Documentation for connector options:
// https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options

$opts = array(
	 'debug' => true,
	'roots' => array(
		// Items volume
		array(
			'driver'        => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
			'path'          => '../files/',                 // path to files (REQUIRED)
			'URL'           => dirname($_SERVER['PHP_SELF']) . '/../files/', // URL to files (REQUIRED)
			'uploadDeny'    => array('all'),                // All Mimetypes not allowed to upload
			'uploadAllow'   => array('all'),// Mimetype `image` and `text/plain` allowed to upload
			'uploadOrder'   => array('deny', 'allow'),      // allowed Mimetype `image` and `text/plain` only
			'accessControl' => 'access'                     // disable and hide dot starting files (OPTIONAL)
		),
		
		// Dropbox/GoogleDrive volumes intentionally omitted — do not hardcode access tokens.
	)
);

// run elFinder
$connector = new elFinderConnector(new elFinder($opts));
$connector->run();
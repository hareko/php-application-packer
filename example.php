<?php

/**
 * PHP Application Packer (PackApp.php)
 * 
 * The usage sample
 *
 * @package Packer
 * @author Vallo Reima
 * @copyright (C)2015
 */
if (version_compare(PHP_VERSION, '5.3', '<')) {
  die('PHP 5.3+ version is required');
} else {
  error_reporting(E_ALL | E_STRICT);
  ini_set('display_errors', true);
  ini_set('log_errors', false);
}

require('PackApp.php'); // main class, loads the others if needed

$old = 'tests'; // source folder
$new = 'tests.zip'; // destination zipped

$obj = new PackApp(1); // minify the source and obfuscate JavaScript code
$rlt = $obj->Pack($old, $new, true);  // pack the source and get result data; replace existing data

header('Content-Type: text/html; charset=utf-8');
echo (str_replace(["\t", "\n"], ['&nbsp;&nbsp;', '<br>'], $rlt['string'])); // display information in html

if ($rlt['code'] == 'ok') {//success
  file_put_contents(pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt', $rlt['string']); // save the protocol
}

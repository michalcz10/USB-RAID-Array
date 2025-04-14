<?php
require '../../vendor/autoload.php';
use phpseclib3\Net\SFTP;

$defPath = $_SESSION['defPath'] ?? '/';

// SFTP Configuration
$host = 'localhost';
$username = 'UNAME';
$password = 'PSWD';
$defaultPath = $defPath;

function initializeSFTP($host, $username, $password) {
    $sftp = new SFTP($host);
    if (!$sftp->login($username, $password)) {
        die('Login Failed');
    }
    return $sftp;
}

function normalizePath($path) {
    $parts = array_filter(explode('/', $path), fn($part) => $part !== '' && $part !== '.');
    $stack = [];
    foreach ($parts as $part) {
        if ($part === '..') {
            array_pop($stack);
        } else {
            $stack[] = $part;
        }
    }
    return '/' . implode('/', $stack);
}

$sftp = initializeSFTP($host, $username, $password);
$currentPath = normalizePath($defaultPath);

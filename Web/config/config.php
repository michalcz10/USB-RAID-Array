<?php
// Central configuration for DB, SFTP, and SSH connections

// Autoload dependencies (phpseclib)
require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

// Database credentials (placeholders – replace with real values in production)
$DB_HOST = 'localhost:3306';
$DB_USER = 'usbraid';
$DB_PASS = 'usbraid';
$DB_NAME = 'usbraid';

// SFTP credentials (placeholders)
$SFTP_HOST = '172.16.1.11';
$SFTP_USER = 'usbraid';
$SFTP_PASS = 'usbraid';
// Default remote path – uses session value if set
$defaultPath = $_SESSION['defPath'] ?? '/';

// SSH credentials (placeholders)
$SSH_HOST = '172.16.1.11';
$SSH_USER = 'usbraid';
$SSH_PASS = 'usbraid';

/** Helper: Get a MySQLi connection */
function getDatabaseConnection(): mysqli {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8');
    return $conn;
}

/** Helper: Initialise SFTP connection */
function initializeSFTP(): SFTP {
    global $SFTP_HOST, $SFTP_USER, $SFTP_PASS;
    $sftp = new SFTP($SFTP_HOST);
    if (!$sftp->login($SFTP_USER, $SFTP_PASS)) {
        die('Login Failed');
    }
    return $sftp;
}

/** Helper: Initialise SSH connection */
function getSSHConnection(): SSH2 {
    global $SSH_HOST, $SSH_USER, $SSH_PASS;
    $ssh = new SSH2($SSH_HOST);
    if (!$ssh->login($SSH_USER, $SSH_PASS)) {
        die('SSH login failed');
    }
    return $ssh;
}

/** Utility: Normalise a path */
function normalizePath(string $path): string {
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
?>

<?php
// Include this at the top to see potential errors
// Comment out in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';

$sftp = initializeSFTP($host, $username, $password);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'createDir') {
    $currentPath = isset($_POST['currentPath']) ? normalizePath($_POST['currentPath']) : $defaultPath;
    
    if (!isset($_POST['dirName'])) {
        http_response_code(400);
        echo "Directory name is required";
        exit;
    }
    
    $dirName = $_POST['dirName'];
    
    $dirName = preg_replace('/[^\w\-\.]/', '_', $dirName);
    
    if (strpos($currentPath, $defaultPath) !== 0) {
        $currentPath = $defaultPath;
    }
    
    $newDirPath = $currentPath . '/' . $dirName;
    
    if ($sftp->mkdir($newDirPath)) {
        echo "Directory created successfully!";
    } else {
        http_response_code(500);
        echo "Failed to create directory.";
    }
    exit;
}

http_response_code(400);
echo "Invalid request";
exit;
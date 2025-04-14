<?php
session_start();

if(!isset($_SESSION['uname'])){
    header("location: ../../index.php");
    session_destroy();
    exit;
}

if(!isset($_SESSION["upPer"]) || $_SESSION["upPer"] != true) {
    die("You don't have permission to rename files or directories.");
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';
$sftp = initializeSFTP($host, $username, $password);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPath = isset($_POST['path']) ? $_POST['path'] : '';
    $newName = isset($_POST['newName']) ? trim($_POST['newName']) : '';
    $isDirectory = isset($_POST['isDirectory']) && $_POST['isDirectory'] == '1';
    
    if (empty($oldPath) || empty($newName)) {
        die("Missing required information for renaming.");
    }

    $dirPath = dirname($oldPath);
    $oldName = basename($oldPath);

    $newPath = $dirPath . '/' . $newName;

    if ($sftp->file_exists($newPath) || $sftp->is_dir($newPath)) {
        die("Error: A file or directory with this name already exists.");
    }

    if ($sftp->rename($oldPath, $newPath)) {
        header("Location: index.php?path=" . urlencode($dirPath));
        exit;
    } else {
        die("Failed to rename the item. Please try again.");
    }
} else {
    header("Location: index.php");
    exit;
}
?>
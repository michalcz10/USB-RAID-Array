<?php
// Include this at the top to see potential errors
// Comment out in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';
use phpseclib3\Net\SFTP;

$sftp = initializeSFTP($host, $username, $password);

$currentPath = isset($_POST['currentPath']) ? normalizePath($_POST['currentPath']) : $defaultPath;

if (strpos($currentPath, $defaultPath) !== 0) {
    $currentPath = $defaultPath;
}

function createDirectoryRecursive($sftp, $path) {
    if ($sftp->is_dir($path)) {
        return true;
    }
    
    $parent = dirname($path);
    if ($parent != '/' && !$sftp->is_dir($parent)) {
        createDirectoryRecursive($sftp, $parent);
    }
    
    return $sftp->mkdir($path, 0755);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_dirs'])) {
        $directories = json_decode($_POST['create_dirs'], true);
        
        if (is_array($directories)) {
            foreach ($directories as $dir) {
                $remoteDirPath = $currentPath . '/' . $dir;
                createDirectoryRecursive($sftp, $remoteDirPath);
            }
        }
    }
    
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        echo "No files received or file size exceeds PHP limits.";
        http_response_code(400);
        exit;
    }
    
    if ($_FILES['files']['error'][0] !== 0) {
        $error = $_FILES['files']['error'][0];
        $errorMessage = "Upload error code: $error";

        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMessage = "File exceeds upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "File exceeds MAX_FILE_SIZE directive in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = "File was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = "A PHP extension stopped the file upload";
                break;
        }
        
        echo $errorMessage;
        http_response_code(400);
        exit;
    }

    $uploadStatus = array();
    $anySuccess = false;
    
    foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
        if (empty($tmpName)) continue;
        
        $fileName = $_FILES['files']['name'][$index];
        $relativePath = isset($_POST['paths']) && isset($_POST['paths'][$index]) ? $_POST['paths'][$index] : '';

        if (!empty($relativePath)) {
            $fileName = basename($relativePath);

            $dirPart = dirname($relativePath);
            if ($dirPart !== '.' && $dirPart !== '') {
                $remoteDirPath = $currentPath . '/' . $dirPart;

                if (!$sftp->is_dir($remoteDirPath)) {
                    createDirectoryRecursive($sftp, $remoteDirPath);
                }
                
                $remotePath = $remoteDirPath . '/' . $fileName;
            } else {
                $remotePath = $currentPath . '/' . $fileName;
            }
        } else {
            $remotePath = $currentPath . '/' . $fileName;
        }

        if ($sftp->put($remotePath, $tmpName, SFTP::SOURCE_LOCAL_FILE)) {
            $uploadStatus[] = "Uploaded: " . ($relativePath ? $relativePath : $fileName);
            $anySuccess = true;
        } else {
            $uploadStatus[] = "Failed to upload: " . ($relativePath ? $relativePath : $fileName);
        }
    }
    
    if ($anySuccess) {
        echo implode("\n", $uploadStatus);
        exit;
    } else {
        echo "Failed to upload any files. Please check SFTP connection and permissions.";
        http_response_code(500);
        exit;
    }
}

echo "No files received or invalid request.";
http_response_code(400);
exit;
<?php
set_time_limit(900); // 15 minutes max execution time

// Include this at the top to see potential errors
// Comment out in production
ini_set('display_errors', 0);
error_reporting(0);

ob_start();

try {
    require 'config.php';

    $logFile = __DIR__ . '/download_log.txt';
    file_put_contents($logFile, "Download started: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    
    $sftp = null;
    try {
        $sftp = initializeSFTP($host, $username, $password);
        file_put_contents($logFile, "SFTP connection established\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents($logFile, "SFTP connection failed: " . $e->getMessage() . "\n", FILE_APPEND);
        throw new Exception("Failed to connect to SFTP server: " . $e->getMessage());
    }
    
    function zipFolderRecursive($sftp, $remoteBasePath, $currentPath, $zip, $logFile, &$tempFiles) {
        file_put_contents($logFile, "Processing directory: $currentPath\n", FILE_APPEND);
        
        $files = $sftp->nlist($currentPath);
        
        if ($files === false) {
            file_put_contents($logFile, "Failed to list directory contents for: $currentPath\n", FILE_APPEND);
            throw new Exception("Failed to list directory contents for: $currentPath");
        }
        
        file_put_contents($logFile, "Found " . count($files) . " items in $currentPath\n", FILE_APPEND);
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $fullRemotePath = rtrim($currentPath, '/') . '/' . $file;
            
            $baseDirName = basename($remoteBasePath);
            $relPathFromBase = substr($fullRemotePath, strlen(dirname($remoteBasePath)) + 1);
            file_put_contents($logFile, "Processing: $fullRemotePath (relative: $relPathFromBase)\n", FILE_APPEND);
            
            $isDir = $sftp->is_dir($fullRemotePath);
            
            if ($isDir) {
                file_put_contents($logFile, "Found subdirectory: $fullRemotePath\n", FILE_APPEND);
                $zip->addEmptyDir($relPathFromBase);
                
                zipFolderRecursive($sftp, $remoteBasePath, $fullRemotePath, $zip, $logFile, $tempFiles);
            } else {
                $localTempFile = tempnam(sys_get_temp_dir(), 'sftp');
                $tempFiles[] = $localTempFile;
                file_put_contents($logFile, "Downloading to temp file: $localTempFile\n", FILE_APPEND);
                
                $downloadStart = time();
                $downloadSuccess = $sftp->get($fullRemotePath, $localTempFile);
                $downloadTime = time() - $downloadStart;
                
                if ($downloadSuccess) {
                    $fileSize = filesize($localTempFile);
                    file_put_contents($logFile, "Download successful ($downloadTime seconds), size: $fileSize bytes\n", FILE_APPEND);
                    
                    $zip->addFile($localTempFile, $relPathFromBase);
                } else {
                    file_put_contents($logFile, "Failed to download file after $downloadTime seconds\n", FILE_APPEND);
                }
            }
        }
    }
    
    function zipFolder($sftp, $folderPath, $zipFilePath, $logFile) {
        file_put_contents($logFile, "Starting to zip folder recursively: $folderPath\n", FILE_APPEND);
        
        $zip = new ZipArchive();
        
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            file_put_contents($logFile, "Failed to create zip archive\n", FILE_APPEND);
            throw new Exception("Unable to create the zip file.");
        }
        
        
        $tempFiles = [];
        
        try {
            zipFolderRecursive($sftp, $folderPath, $folderPath, $zip, $logFile, $tempFiles);
            
            file_put_contents($logFile, "Closing zip file\n", FILE_APPEND);
            $zipSuccess = $zip->close();
            
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
            
            if ($zipSuccess) {
                file_put_contents($logFile, "Zip created successfully, size: " . filesize($zipFilePath) . " bytes\n", FILE_APPEND);
                return true;
            } else {
                file_put_contents($logFile, "Failed to create zip\n", FILE_APPEND);
                return false;
            }
        } catch (Exception $e) {
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
            
            file_put_contents($logFile, "Error during zip creation: " . $e->getMessage() . "\n", FILE_APPEND);
            throw $e;
        }
    }
    
    if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file'])) ||
        ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['file']))) {
        
        $file = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST['file'] : $_GET['file'];
        file_put_contents($logFile, "Requested file: $file\n", FILE_APPEND);

        $fileExists = $sftp->file_exists($file);
        $isDir = $sftp->is_dir($file);
        
        file_put_contents($logFile, "File exists: " . ($fileExists ? "Yes" : "No") . "\n", FILE_APPEND);
        file_put_contents($logFile, "Is directory: " . ($isDir ? "Yes" : "No") . "\n", FILE_APPEND);
        
        if (!$fileExists && !$isDir) {
            throw new Exception("File not found: $file");
        }
        
        if ($isDir) {
            $zipFilePath = tempnam(sys_get_temp_dir(), 'folder_') . '.zip';
            file_put_contents($logFile, "Creating zip at: $zipFilePath\n", FILE_APPEND);
            
            if (zipFolder($sftp, $file, $zipFilePath, $logFile)) {
                if (file_exists($zipFilePath) && filesize($zipFilePath) > 0) {
                    file_put_contents($logFile, "Sending zip file to browser, size: " . filesize($zipFilePath) . " bytes\n", FILE_APPEND);
                    
                    while (ob_get_level()) {
                        ob_end_clean();
                    }

                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . basename($file) . '.zip"');
                    header('Content-Length: ' . filesize($zipFilePath));
                    header('Cache-Control: no-cache, must-revalidate');
                    header('Pragma: no-cache');

                    readfile($zipFilePath);
                    file_put_contents($logFile, "Download completed\n", FILE_APPEND);
                    @unlink($zipFilePath);
                    exit;
                } else {
                    throw new Exception("Failed to create zip file or zip file is empty");
                }
            } else {
                throw new Exception("Failed to create zip archive");
            }
        } else {
            $localFile = basename($file);
            file_put_contents($logFile, "Downloading single file: $localFile\n", FILE_APPEND);

            $tempFile = tempnam(sys_get_temp_dir(), 'file_');
            
            $downloadStart = time();
            $downloadSuccess = $sftp->get($file, $tempFile);
            $downloadTime = time() - $downloadStart;
            
            file_put_contents($logFile, "Download " . ($downloadSuccess ? "successful" : "failed") . " ($downloadTime seconds)\n", FILE_APPEND);
            
            if ($downloadSuccess) {
                $fileSize = filesize($tempFile);
                file_put_contents($logFile, "Downloaded file size: $fileSize bytes\n", FILE_APPEND);
                
                if ($fileSize > 0) {
                    while (ob_get_level()) {
                        ob_end_clean();
                    }

                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $localFile . '"');
                    header('Content-Length: ' . $fileSize);
                    header('Cache-Control: no-cache, must-revalidate');
                    header('Pragma: no-cache');

                    readfile($tempFile);
                    file_put_contents($logFile, "Download completed\n", FILE_APPEND);
                    @unlink($tempFile);
                    exit;
                } else {
                    @unlink($tempFile);
                    throw new Exception("Downloaded file is empty");
                }
            } else {
                @unlink($tempFile);
                throw new Exception("Failed to download file from SFTP server");
            }
        }
    } else {
        throw new Exception("Invalid request method or missing file parameter");
    }
} catch (Exception $e) {
    $errorMessage = "Error: " . $e->getMessage();
    file_put_contents($logFile, $errorMessage . "\n", FILE_APPEND);

    while (ob_get_level()) {
        ob_end_clean();
    }

    header("HTTP/1.1 500 Internal Server Error");
    echo $errorMessage;
}
?>
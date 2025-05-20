<?php
session_start();

if(!isset($_SESSION['uname'])){
    header("location: ../../index.php");
    session_destroy();
    exit;
}

// Check if user has permission to upload/modify
if(!isset($_SESSION["upPer"]) || $_SESSION["upPer"] != true) {
    die("You don't have permission to extract archives.");
}

// Include error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';
use phpseclib3\Net\SFTP;

$sftp = initializeSFTP($host, $username, $password);

// Process extraction request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive'])) {
    $archivePath = $_POST['archive'];
    $dirPath = dirname($archivePath);
    $archiveName = basename($archivePath);
    $extension = pathinfo($archiveName, PATHINFO_EXTENSION);
    
    // Create a temporary local directory for extraction
    $tempDir = sys_get_temp_dir() . '/sftp_extract_' . time();
    if (!mkdir($tempDir, 0777, true)) {
        die("Failed to create temporary directory");
    }
    
    // Download the archive to the temp directory
    $localArchivePath = $tempDir . '/' . $archiveName;
    if (!$sftp->get($archivePath, $localArchivePath)) {
        rmdir($tempDir);
        die("Failed to download the archive");
    }
    
    // Detect archive type and extract
    $extractionSuccess = false;
    
    try {
        // Determine the extraction method based on file extension
        switch (strtolower($extension)) {
            case 'zip':
                $extractionSuccess = extractZip($localArchivePath, $tempDir);
                break;
                
            case 'rar':
                $extractionSuccess = extractRar($localArchivePath, $tempDir);
                break;
                
            case 'tar':
            case 'gz':
            case 'bz2':
            case 'xz':
            case '7z':
                $extractionSuccess = extractArchive($localArchivePath, $tempDir);
                break;
                
            default:
                die("Unsupported archive format");
        }
        
        if ($extractionSuccess) {
            // Upload extracted files back to the server
            uploadExtractedFiles($sftp, $tempDir, $dirPath);
            
            // Clean up temporary directory
            deleteDirectory($tempDir);
            
            // Redirect back to the file listing
            header("Location: index.php?path=" . urlencode($dirPath));
            exit;
        } else {
            die("Failed to extract the archive");
        }
    } catch (Exception $e) {
        deleteDirectory($tempDir);
        die("Error during extraction: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}

// Extract ZIP archives
function extractZip($archivePath, $destination) {
    $zip = new ZipArchive();
    
    if ($zip->open($archivePath) === TRUE) {
        $zip->extractTo($destination);
        $zip->close();
        return true;
    }
    
    return false;
}

// Extract RAR archives (requires rar extension or unrar command)
function extractRar($archivePath, $destination) {
    // Try PHP Rar extension first
    if (extension_loaded('rar')) {
        $rar = RarArchive::open($archivePath);
        if ($rar) {
            $entries = $rar->getEntries();
            foreach ($entries as $entry) {
                $entry->extract($destination);
            }
            $rar->close();
            return true;
        }
    }
    
    // Fallback to command line unrar if available
    if (shell_exec("which unrar") || file_exists('C:\\Program Files\\WinRAR\\UnRAR.exe')) {
        $command = '';
        
        if (PHP_OS_FAMILY === 'Windows') {
            $command = '"C:\\Program Files\\WinRAR\\UnRAR.exe" x -o+ ' . escapeshellarg($archivePath) . ' ' . escapeshellarg($destination);
        } else {
            $command = 'unrar x -o+ ' . escapeshellarg($archivePath) . ' ' . escapeshellarg($destination);
        }
        
        exec($command, $output, $returnCode);
        return $returnCode === 0;
    }
    
    throw new Exception("RAR extraction is not available. Please install PHP RAR extension or UnRAR command line tool.");
}

// Extract other archives using system commands
function extractArchive($archivePath, $destination) {
    $extension = pathinfo($archivePath, PATHINFO_EXTENSION);
    $command = '';
    
    // Change to the destination directory
    $currentDir = getcwd();
    chdir($destination);
    
    if (PHP_OS_FAMILY === 'Windows') {
        // For Windows, you'll need to have 7-Zip installed
        $sevenZipPath = 'C:\\Program Files\\7-Zip\\7z.exe';
        if (file_exists($sevenZipPath)) {
            $command = '"' . $sevenZipPath . '" x ' . escapeshellarg($archivePath);
        } else {
            throw new Exception("7-Zip is not installed or not found at the expected location.");
        }
    } else {
        // For Linux/Unix systems
        switch (strtolower($extension)) {
            case 'tar':
                $command = 'tar -xf ' . escapeshellarg($archivePath);
                break;
            case 'gz':
                if (strpos($archivePath, '.tar.gz') !== false) {
                    $command = 'tar -xzf ' . escapeshellarg($archivePath);
                } else {
                    $command = 'gzip -d ' . escapeshellarg($archivePath);
                }
                break;
            case 'bz2':
                if (strpos($archivePath, '.tar.bz2') !== false) {
                    $command = 'tar -xjf ' . escapeshellarg($archivePath);
                } else {
                    $command = 'bzip2 -d ' . escapeshellarg($archivePath);
                }
                break;
            case 'xz':
                if (strpos($archivePath, '.tar.xz') !== false) {
                    $command = 'tar -xJf ' . escapeshellarg($archivePath);
                } else {
                    $command = 'xz -d ' . escapeshellarg($archivePath);
                }
                break;
            case '7z':
                $command = '7z x ' . escapeshellarg($archivePath);
                break;
            default:
                throw new Exception("Unsupported archive format");
        }
    }
    
    exec($command, $output, $returnCode);
    
    // Change back to the original directory
    chdir($currentDir);
    
    return $returnCode === 0;
}

// Upload extracted files back to the SFTP server
function uploadExtractedFiles($sftp, $localDir, $remoteDir) {
    $items = scandir($localDir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $localPath = $localDir . '/' . $item;
        $remotePath = $remoteDir . '/' . $item;
        
        if (is_dir($localPath)) {
            // Create the directory on the remote server
            if (!$sftp->is_dir($remotePath)) {
                $sftp->mkdir($remotePath);
            }
            
            // Upload the contents of the directory
            uploadExtractedFiles($sftp, $localPath, $remotePath);
        } else {
            // Upload the file - use the proper phpseclib3 method
            $sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);
        }
    }
}

// Recursively delete a directory
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    rmdir($dir);
}
?>
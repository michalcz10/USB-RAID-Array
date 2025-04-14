<?php
require 'config.php';

$sftp = initializeSFTP($host, $username, $password);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $itemToDelete = $_POST['delete'];
    $parentDir = dirname($itemToDelete);

    if ($parentDir === '/' || $parentDir === '.') {
        $parentDir = $defaultPath;
    }

    if ($sftp->is_dir($itemToDelete)) {
        function deleteFolder($sftp, $folderPath) {
            $items = $sftp->nlist($folderPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $itemPath = $folderPath . '/' . $item;
                if ($sftp->is_dir($itemPath)) {
                    deleteFolder($sftp, $itemPath);
                } else {
                    $sftp->delete($itemPath);
                }
            }
            return $sftp->rmdir($folderPath);
        }

        $success = deleteFolder($sftp, $itemToDelete);
    } else {
        $success = $sftp->delete($itemToDelete);
    }

    header("Location: index.php?path=" . urlencode($parentDir));
    exit;
}

header("Location: index.php");
exit;
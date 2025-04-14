<?php
    session_start();

    if(!isset($_SESSION['uname'])){
        header("location: ../../index.php");
        session_destroy();
        exit;
    }

    // Include this at the top to see potential errors
    // Comment out in production
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    require 'config.php';
    $sftp = initializeSFTP($host, $username, $password);

    $currentPath = isset($_GET['path']) ? normalizePath($_GET['path']) : $defaultPath;
    if (strpos($currentPath, $defaultPath) !== 0) {
        $currentPath = $defaultPath;
    }
    
    if (!$sftp->chdir($currentPath)) {
        die("Failed to navigate to the selected folder: $currentPath");
    }

    $items = $sftp->nlist();
	
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
    $audioExtensions = ['mp3', 'wav', 'm4a', 'flac', 'aac'];
    $editableExtensions = ['txt', 'html', 'css', 'js', 'php', 'xml', 'json', 'md', 'csv', 'log', 'ini', 'conf', 'sh', 'bat', 'py', 'rb', 'java', 'c', 'cpp', 'h', 'hpp'];
    $archiveExtensions = ['zip', 'rar', 'tar', 'gz', '7z', 'bz2', 'xz', 'tar.gz', 'tar.bz2', 'tar.xz'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>FTP</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="../../img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/index.css">
    <script src="../../js/bootstrap.bundle.js"></script>
</head>
<body class="text-center">
<div class="d-flex justify-content-end p-3">
    <button id="themeToggle" class="btn btn-sm theme-toggle">
        <i class="bi"></i>
        <span id="themeText"></span>
    </button>
</div>
<div class="custom-container">
<header class="row border-bottom m-5">
    <h1>USB RAID Array</h1>
    <div class="mb-3 p-3">
        <a href="../logout.php" class="btn btn-danger">Logout</a>
        <a href="../changepassword.php" class="btn btn-warning">Change Password</a>
        <?php if (isset($_SESSION["admin"]) && $_SESSION["admin"] == true) { ?>
            <a href="../adminpanel.php" class="btn btn-primary">Admin Panel</a>
            <a href="serverstat.php" class="btn btn-primary">Server Status</a>
        <?php } ?>
    </div>
</header>

<section class="row">

    <article class="col-8 border border-2 border-primary rounded p-2">
        <div class="col">
            <h4>Current Path: <?= htmlspecialchars($currentPath) ?></h4>
        </div>

        <div class="col">
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) { ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFileModal">Create File</button>
                    <button type="button" class="btn btn-success" onclick="document.getElementById('fileInput').click()">Upload Files</button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDirModal">Create Directory</button>
                    <button type="button" class="btn btn-info" onclick="document.getElementById('dirInput').click()">Upload Directory</button>
                    <input type="file" id="fileInput" multiple style="display: none;" onchange="handleFileSelect(event)">
                    <input type="file" id="dirInput" webkitdirectory directory multiple style="display: none;" onchange="handleFileSelect(event)">
                <?php } ?>
            </div>

        <!-- Modal Forms -->
        <!-- Create Directory Dialog -->
        <div class="modal fade" id="createDirModal" tabindex="-1" aria-labelledby="createDirModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createDirModalLabel">Create New Directory</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="dirName" class="form-label">Directory Name</label>
                            <input type="text" class="form-control" id="dirName" placeholder="Enter directory name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="createDirectory()">Create</button>
                    </div>
                </div>
            </div>
        </div>

            <!-- Create File Dialog -->
            <div class="modal fade" id="createFileModal" tabindex="-1" aria-labelledby="createFileModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createFileModalLabel">Create New File</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="fileName" class="form-label">File Name (with extension)</label>
                                <input type="text" class="form-control" id="fileName" placeholder="example.txt">
                                <div class="form-text">Supported extensions: .txt, .html, .css, .js, .php, etc.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="createFile()">Create</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rename Confirmation Dialog -->
            <div class="modal fade" id="renameModal" tabindex="-1" aria-labelledby="renameModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="renameModalLabel">Rename Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="rename.php">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="originalName" class="form-label">Current Name:</label>
                                <input type="text" class="form-control" id="originalName" disabled>
                                <input type="hidden" id="fullPath" name="path">
                                <input type="hidden" id="isDirectory" name="isDirectory">
                            </div>
                            <div class="mb-3">
                                <label for="newName" class="form-label">New Name:</label>
                                <input type="text" class="form-control" id="newName" name="newName" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Rename</button>
                        </div>
                    </form>
                </div>
            </div>
            </div>
            <!-- End of Modal Forms -->

            <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) { ?>
                <div class="dropzone" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                    Drop files or folders here to upload
                </div>
            <?php } ?>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $directories = [];
                    $files = [];

                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;

                        if ($sftp->is_dir($item)) {
                            $directories[] = $item;
                        } else {
                            $files[] = $item;
                        }
                    }

                    // Sort directories and files alphabetically
                    sort($directories, SORT_STRING | SORT_FLAG_CASE);
                    sort($files, SORT_STRING | SORT_FLAG_CASE);

                    if ($currentPath !== $defaultPath) : ?>
                        <tr>
                            <td colspan="3"><a class="text-danger" href="?path=<?= urlencode(dirname($currentPath)) ?>"><b>.. (Go Back)</b></a></td>
                        </tr>
                    <?php endif;

                    foreach ($directories as $directory) : ?>
                        <tr>
                            <td colspan="2">
                                <a href="?path=<?= urlencode($currentPath . '/' . $directory) ?>"><?= htmlspecialchars($directory) ?>/</a>
                            </td>
                            <td>
                                <?php if (isset($_SESSION["delPer"]) && $_SESSION["delPer"] == true) : ?>
                                    <form method="POST" action="delete.php" style="display:inline;">
                                        <input type="hidden" name="delete" value="<?= htmlspecialchars($currentPath . '/' . $directory) ?>">
                                        <button type="submit" class="btn btn-danger mt-1 mb-1" onclick="confirmDelete(event)">Delete</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (isset($_SESSION["downPer"]) && $_SESSION["downPer"] == true) : ?>
                                    <form method="POST" action="download.php" style="display:inline;">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars($currentPath . '/' . $directory) ?>">
                                        <button type="submit" class="btn btn-success mt-1 mb-1">Download</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) : ?>
                                    <button type="button" class="btn btn-warning mt-1 mb-1" onclick="openRenameModal('<?= htmlspecialchars(addslashes($directory)) ?>', '<?= htmlspecialchars(addslashes($currentPath . '/' . $directory)) ?>')">Rename</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach;

                    foreach ($files as $file) : ?>
                        <?php
                        
                        $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
                        $isPDF = strtolower($fileExtension) === 'pdf';
                        $isImage = in_array($fileExtension, $imageExtensions);
                        $isVideo = in_array($fileExtension, $videoExtensions);
                        $isAudio = in_array($fileExtension, $audioExtensions);
                        $isMedia = $isImage || $isVideo || $isAudio;
                        $isEditable = in_array($fileExtension, $editableExtensions);
                        $isArchive = in_array($fileExtension, $archiveExtensions);
                        if (!$isArchive && strpos($file, '.tar.') !== false) {
                            $isArchive = true;
                        }
                        ?>
                        <tr>
                            <td>
                                
                                <?php if ($isMedia): ?>
                                <a class="text-info-emphasis" href="view.php?file=<?= urlencode($currentPath . '/' . $file) ?>">
                                    <?= htmlspecialchars($file) ?>
                                </a>
                                <?php if ($isImage): ?>
                                    <span class="badge bg-success rounded-pill">Image</span>
                                <?php elseif ($isVideo): ?>
                                    <span class="badge bg-primary rounded-pill">Video</span>
                                <?php elseif ($isAudio): ?>
                                    <span class="badge bg-info rounded-pill">Audio</span>
                                <?php endif; ?>
                                <?php elseif ($isEditable): ?>
                                    <a class="text-warning-emphasis" href="open.php?file=<?= urlencode($currentPath . '/' . $file) ?>">
                                        <?= htmlspecialchars($file) ?>
                                    </a>
                                    <span class="badge bg-secondary rounded-pill"><?= htmlspecialchars($fileExtension) ?></span>
                                    <?php elseif ($isPDF): ?>
                                        <a class="text-info-emphasis" href="pdf.php?file=<?= urlencode($currentPath . '/' . $file) ?>&type=pdf">
                                            <?= htmlspecialchars($file) ?>
                                        </a>
                                        <span class="badge bg-danger rounded-pill">PDF</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($file) ?>
                                    <span class="badge bg-light text-dark rounded-pill"><?= htmlspecialchars($fileExtension) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text"><?= formatBytes($sftp->stat($currentPath . '/' . $file)['size']) ?></span>
                            </td>
                            <td>
                                <?php if (isset($_SESSION["delPer"]) && $_SESSION["delPer"] == true) : ?>
                                    <form method="POST" action="delete.php" style="display:inline;">
                                        <input type="hidden" name="delete" value="<?= htmlspecialchars($currentPath . '/' . $file) ?>">
                                        <button type="submit" class="btn btn-danger mt-1 mb-1" onclick="confirmDelete(event)">Delete</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (isset($_SESSION["downPer"]) && $_SESSION["downPer"] == true) : ?>
                                    <form method="POST" action="download.php" style="display:inline;">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars($currentPath . '/' . $file) ?>">
                                        <button type="submit" class="btn btn-success mt-1 mb-1">Download</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) : ?>
                                    <button type="button" class="btn btn-warning mt-1 mb-1" onclick="openRenameModal('<?= htmlspecialchars(addslashes($file)) ?>', '<?= htmlspecialchars(addslashes($currentPath . '/' . $file)) ?>')">Rename</button>
                                <?php endif; ?>
                                <?php if ($isArchive && isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) : ?>
                                    <form method="POST" action="extract.php" style="display:inline;">
                                        <input type="hidden" name="archive" value="<?= htmlspecialchars($currentPath . '/' . $file) ?>">
                                        <button type="submit" class="btn btn-info mt-1 mb-1">Extract</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <footer class="d-flex flex-column justify-content-center align-items-center p-3 border-top gap-3 m-3">
    <span class="text-muted">Developed by Michal Sedlák</span>
    <div class="d-flex gap-3">
        <a href="https://github.com/michalcz10/USB-RAID-pole" class="text-decoration-none" target="_blank" rel="noopener noreferrer">
            <img src="../../img/GitHub_Logo.png" alt="GitHub Logo" class="img-fluid hover-effect light-logo" style="height: 32px;">
            <img src="../../img/GitHub_Logo_White.png" alt="GitHub Logo" class="img-fluid hover-effect dark-logo" style="height: 32px;">
        </a>
        <a href="https://app.freelo.io/public/shared-link-view/?a=81efbcb4df761b3f29cdc80855b41e6d&b=4519c717f0729cc8e953af661e9dc981" class="text-decoration-none" target="_blank" rel="noopener noreferrer">
            <img src="../../img/freelo-logo-rgb.png" alt="Freelo Logo" class="img-fluid hover-effect light-logo" style="height: 24px;">
            <img src="../../img/freelo-logo-rgb-on-dark.png" alt="Freelo Logo" class="img-fluid hover-effect dark-logo" style="height: 24px;">
        </a>
    </div>
    </footer>
    </div>
    <script>
        // Pass PHP variables to JavaScript
        var currentPath = "<?php echo $currentPath; ?>";
    </script>
    <script src="js/index.js"></script>
</body>
</html>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
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

    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/workspace.php';
    $sftp = initializeSFTP();

    $defaultPath = normalizePath($defaultPath);
    $currentPath = isset($_GET['path']) ? normalizePath($_GET['path']) : $defaultPath;
    if ($currentPath !== $defaultPath && strpos($currentPath, rtrim($defaultPath, '/') . '/') !== 0) {
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
    <title>Files · USB RAID</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="../../img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= workspaceAsset('../../', 'css/modern.css') ?>">
    <link rel="stylesheet" href="<?= workspaceAsset('../../', 'css/workspace.css') ?>">
    <link rel="stylesheet" href="<?= workspaceAsset('../../', 'content/ftp/css/index.css') ?>">
    <script src="<?= workspaceAsset('../../', 'js/theme.js') ?>"></script>
    <script src="<?= workspaceAsset('../../', 'js/ui.js') ?>" defer></script>
</head>
<?php renderWorkspaceStart([
    'root' => '../../',
    'active' => 'files',
    'section' => 'Files',
    'eyebrow' => 'Your remote storage',
    'title' => 'All files',
    'description' => 'Everything in its place. Browse, manage and transfer your files.',
    'connected' => true,
]); ?>
        <nav class="pathbar" aria-label="Directory path"><a href="index.php" title="Home directory">⌂ <span>Home</span></a><?php
        $relativePath = trim(substr($currentPath, strlen(rtrim($defaultPath, '/'))), '/');
        $crumbPath = rtrim($defaultPath, '/');
        foreach (array_filter(explode('/', $relativePath), 'strlen') as $segment):
            $crumbPath .= '/' . $segment;
        ?><span class="path-separator" aria-hidden="true">/</span><a href="?path=<?= urlencode($crumbPath) ?>"><?= htmlspecialchars($segment) ?></a><?php endforeach; ?><span class="path-label" title="<?= htmlspecialchars($currentPath) ?>"><?= htmlspecialchars($currentPath) ?></span></nav>
        <section class="file-panel" aria-label="File browser">
        <div class="browser-toolbar"><label class="search-field"><span aria-hidden="true">⌕</span><input id="fileSearch" type="search" placeholder="Search this folder…" aria-label="Search this folder"><kbd>/</kbd></label><select id="typeFilter" class="form-select" aria-label="Filter by type"><option value="all">All types</option><option value="folder">Folders</option><option value="file">Files</option></select></div>
        <div class="file-content">
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) { ?>
                    <button type="button" class="btn" data-dialog="createFileModal">＋ New file</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">↑ Upload files</button>
                    <button type="button" class="btn" data-dialog="createDirModal">＋ New folder</button>
                    <button type="button" class="btn btn-info" onclick="document.getElementById('dirInput').click()">Upload folder</button>
                    <input type="file" id="fileInput" multiple style="display: none;" onchange="handleFileSelect(event)">
                    <input type="file" id="dirInput" webkitdirectory directory multiple style="display: none;" onchange="handleFileSelect(event)">
                <?php } ?>
            </div>

        <!-- Modal Forms -->
        <!-- Create Directory Dialog -->
        <dialog class="modal" id="createDirModal" tabindex="-1" aria-labelledby="createDirModalLabel">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createDirModalLabel">Create New Directory</h5>
                        <button type="button" class="btn-close" data-dialog-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="dirName" class="form-label">Directory Name</label>
                            <input type="text" class="form-control" id="dirName" autofocus placeholder="Enter directory name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="createDirectory()">Create</button>
                    </div>
                </div>
            </div>
        </dialog>

            <!-- Create File Dialog -->
            <dialog class="modal" id="createFileModal" tabindex="-1" aria-labelledby="createFileModalLabel">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createFileModalLabel">Create New File</h5>
                            <button type="button" class="btn-close" data-dialog-close aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="fileName" class="form-label">File Name (with extension)</label>
                                <input type="text" class="form-control" id="fileName" autofocus placeholder="example.txt">
                                <div class="form-text">Supported extensions: .txt, .html, .css, .js, .php, etc.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="createFile()">Create</button>
                        </div>
                    </div>
                </div>
            </dialog>

            <!-- Rename Confirmation Dialog -->
            <dialog class="modal" id="renameModal" tabindex="-1" aria-labelledby="renameModalLabel">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="renameModalLabel">Rename Item</h5>
                        <button type="button" class="btn-close" data-dialog-close aria-label="Close"></button>
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
                                <input type="text" class="form-control" id="newName" autofocus name="newName" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
                            <button type="submit" class="btn btn-primary">Rename</button>
                        </div>
                    </form>
                </div>
            </div>
            </dialog>
            <!-- End of Modal Forms -->

            <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) { ?>
                <div class="dropzone" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                    <span class="upload-symbol" aria-hidden="true">↑</span><span><strong>Drop files or folders here</strong><small>Or use the upload buttons to browse your device</small></span>
                </div>
            <?php } ?>

            <div class="file-table-scroll"><table class="file-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Size</th>
                        <th scope="col">Actions</th>
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
                            <td colspan="3"><a class="text-danger" href="?path=<?= urlencode(remoteDirname($currentPath)) ?>"><b>↑ Parent folder</b></a></td>
                        </tr>
                    <?php endif;

                    foreach ($directories as $directory) : ?>
                        <tr data-file-name="<?= htmlspecialchars($directory) ?>" data-kind="folder">
                            <td><span class="file-icon folder-icon" aria-hidden="true">▰</span>
                                <a href="?path=<?= urlencode(rtrim($currentPath, '/') . '/' . $directory) ?>"><?= htmlspecialchars($directory) ?></a></td><td class="size-cell">—</td>
                            <td>
                                <?php if (isset($_SESSION["delPer"]) && $_SESSION["delPer"] == true) : ?>
                                    <form method="POST" action="delete.php" style="display:inline;">
                                        <input type="hidden" name="delete" value="<?= htmlspecialchars(rtrim($currentPath, '/') . '/' . $directory) ?>">
                                        <button type="submit" class="btn btn-danger mt-1 mb-1" onclick="confirmDelete(event)">Delete</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (isset($_SESSION["downPer"]) && $_SESSION["downPer"] == true) : ?>
                                    <form method="POST" action="download.php" style="display:inline;">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars(rtrim($currentPath, '/') . '/' . $directory) ?>">
                                        <button type="submit" class="btn btn-success mt-1 mb-1">Download</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) : ?>
                                    <button type="button" class="btn btn-warning mt-1 mb-1" onclick="openRenameModal('<?= htmlspecialchars(addslashes($directory)) ?>', '<?= htmlspecialchars(addslashes(rtrim($currentPath, '/') . '/' . $directory)) ?>', true)">Rename</button>
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
                        <tr data-file-name="<?= htmlspecialchars($file) ?>" data-kind="file">
                            <td><span class="file-icon" aria-hidden="true">▤</span>
                                <?php if ($isMedia): ?>
                                <a class="text-info-emphasis" href="view.php?file=<?= urlencode(rtrim($currentPath, '/') . '/' . $file) ?>">
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
                                    <a class="text-warning-emphasis" href="open.php?file=<?= urlencode(rtrim($currentPath, '/') . '/' . $file) ?>">
                                        <?= htmlspecialchars($file) ?>
                                    </a>
                                    <span class="badge bg-secondary rounded-pill"><?= htmlspecialchars($fileExtension) ?></span>
                                    <?php elseif ($isPDF): ?>
                                        <a class="text-info-emphasis" href="pdf.php?file=<?= urlencode(rtrim($currentPath, '/') . '/' . $file) ?>&type=pdf">
                                            <?= htmlspecialchars($file) ?>
                                        </a>
                                        <span class="badge bg-danger rounded-pill">PDF</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($file) ?>
                                    <span class="badge bg-light text-dark rounded-pill"><?= htmlspecialchars($fileExtension) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text"><?= formatBytes($sftp->stat(rtrim($currentPath, '/') . '/' . $file)['size']) ?></span>
                            </td>
                            <td>
                                <?php if (isset($_SESSION["delPer"]) && $_SESSION["delPer"] == true) : ?>
                                    <form method="POST" action="delete.php" style="display:inline;">
                                        <input type="hidden" name="delete" value="<?= htmlspecialchars(rtrim($currentPath, '/') . '/' . $file) ?>">
                                        <button type="submit" class="btn btn-danger mt-1 mb-1" onclick="confirmDelete(event)">Delete</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (isset($_SESSION["downPer"]) && $_SESSION["downPer"] == true) : ?>
                                    <form method="POST" action="download.php" style="display:inline;">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars(rtrim($currentPath, '/') . '/' . $file) ?>">
                                        <button type="submit" class="btn btn-success mt-1 mb-1">Download</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) : ?>
                                    <button type="button" class="btn btn-warning mt-1 mb-1" onclick="openRenameModal('<?= htmlspecialchars(addslashes($file)) ?>', '<?= htmlspecialchars(addslashes(rtrim($currentPath, '/') . '/' . $file)) ?>')">Rename</button>
                                <?php endif; ?>
                                <?php if ($isArchive && isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) : ?>
                                    <form method="POST" action="extract.php" style="display:inline;">
                                        <input type="hidden" name="archive" value="<?= htmlspecialchars(rtrim($currentPath, '/') . '/' . $file) ?>">
                                        <button type="submit" class="btn btn-info mt-1 mb-1">Extract</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
            <div id="emptySearch" class="empty-state" hidden><span aria-hidden="true">⌕</span><h3>No files to show</h3><p>Try another search or upload files to this folder.</p></div>
            <div class="browser-status"><span id="fileCount" role="status"></span><span>Folders first · A–Z</span></div>
        </div>
        </section>
<?php renderWorkspaceEnd(); ?>

    <script>
        // Pass PHP variables to JavaScript
        const currentPath = <?= json_encode($currentPath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="<?= workspaceAsset('../../', 'content/ftp/js/index.js') ?>"></script>
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

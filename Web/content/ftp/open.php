<?php
// Include this at the top to see potential errors
// Comment out in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if(!isset($_SESSION['uname'])){
    header("location: ../../index.php");
    session_destroy();
    exit;
}

require 'config.php';
$sftp = initializeSFTP($host, $username, $password);

$filePath = isset($_GET['file']) ? $_GET['file'] : '';

$content = '';
$fileName = basename($filePath);
$extension = pathinfo($fileName, PATHINFO_EXTENSION);
$editable = false;

$editableExtensions = ['txt', 'html', 'css', 'js', 'php', 'xml', 'json', 'md', 'csv', 'log', 'ini', 'conf', 'sh', 'bat', 'py', 'rb', 'java', 'c', 'cpp', 'h', 'hpp'];

if (!empty($filePath) && $sftp->file_exists($filePath) && !$sftp->is_dir($filePath)) {
    if (in_array(strtolower($extension), $editableExtensions)) {
        $editable = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content']) && isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true) {
            $newContent = $_POST['content'];

            if ($sftp->put($filePath, $newContent)) {
                $saveSuccess = true;
            } else {
                $saveError = "Failed to save changes. Check permissions.";
            }
        }
        
        $content = $sftp->get($filePath);
        if ($content === false) {
            $error = "Failed to read file contents.";
        }
    } else {
        $error = "This file type is not supported for editing.";
    }
} else {
    $error = "File not found or is a directory.";
}

$showLineNumbers = in_array(strtolower($extension), ['php', 'js', 'html', 'css', 'py', 'java', 'c', 'cpp', 'h', 'hpp', 'rb', 'sh', 'xml', 'json']);

$parentDir = dirname($filePath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit File - <?= htmlspecialchars($fileName) ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="../../img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/open.css">
    <script src="../../js/bootstrap.bundle.js"></script>
</head>
<body>
<div class="d-flex justify-content-end p-3">
    <button id="themeToggle" class="btn btn-sm theme-toggle">
        <i class="bi"></i>
        <span id="themeText"></span>
    </button>
</div>
    <div class="container mt-4">
        <div class="header-container">
            <h1>Edit File: <?= htmlspecialchars($fileName) ?></h1>
            <a href="index.php?path=<?= urlencode($parentDir) ?>" class="btn btn-secondary">Back to File List</a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php elseif ($editable): ?>
            <?php if (isset($saveSuccess)): ?>
                <div class="alert alert-success">File saved successfully!</div>
            <?php endif; ?>
            
            <?php if (isset($saveError)): ?>
                <div class="alert alert-danger"><?= $saveError ?></div>
            <?php endif; ?>
            
            <form method="POST" id="editorForm">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="editor" class="form-label">File Content</label>
                        <?php if (!isset($_SESSION["upPer"]) || $_SESSION["upPer"] != true): ?>
                            <span class="readonly-notice">Read-only mode (you don't have upload permissions)</span>
                        <?php endif; ?>
                    </div>
                    <div class="editor-container">
                        <?php if ($showLineNumbers): ?>
                            <div id="lineNumbers" class="line-numbers"></div>
                        <?php endif; ?>
                        <textarea id="editor" name="content" class="form-control" <?= (!isset($_SESSION["upPer"]) || $_SESSION["upPer"] != true) ? 'readonly' : '' ?>><?= htmlspecialchars($content) ?></textarea>
                    </div>
                </div>
                
                <?php if (isset($_SESSION["upPer"]) && $_SESSION["upPer"] == true): ?>
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
    <script src="../../js/theme.js"></script>
    <script>
        function updateLineNumbers() {
            const editor = document.getElementById('editor');
            const lineNumbers = document.getElementById('lineNumbers');
            
            if (!lineNumbers) return;
            
            const lines = editor.value.split('\n');
            const lineCount = lines.length;
            
            let html = '';
            for (let i = 1; i <= lineCount; i++) {
                html += i + '<br>';
            }
            
            lineNumbers.innerHTML = html;

            syncLineNumbersHeight();
            lineNumbers.scrollTop = editor.scrollTop;
        }
        
        function syncLineNumbersHeight() {
            const editor = document.getElementById('editor');
            const lineNumbers = document.getElementById('lineNumbers');
            
            if (!lineNumbers || !editor) return;

            lineNumbers.style.height = editor.clientHeight + 'px';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const editor = document.getElementById('editor');
            const lineNumbers = document.getElementById('lineNumbers');
            
            if (editor && lineNumbers) {
                setTimeout(() => {
                    updateLineNumbers();
                    syncLineNumbersHeight();
                }, 0);
                
                editor.addEventListener('input', updateLineNumbers);

                editor.addEventListener('keydown', function(e) {
                    if (e.key === 'Tab') {
                        e.preventDefault();
                        
                        const start = this.selectionStart;
                        const end = this.selectionEnd;

                        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);

                        this.selectionStart = this.selectionEnd = start + 4;

                        updateLineNumbers();
                    }
                });

                editor.addEventListener('scroll', function() {
                    if (lineNumbers) {
                        lineNumbers.scrollTop = this.scrollTop;
                    }
                });

                editor.addEventListener('mouseup', syncLineNumbersHeight);

                const observer = new MutationObserver(function(mutations) {
                    syncLineNumbersHeight();
                });
                
                observer.observe(editor, { 
                    attributes: true, 
                    attributeFilter: ['style'] 
                });

                if (typeof ResizeObserver === 'function') {
                    const resizeObserver = new ResizeObserver(() => {
                        syncLineNumbersHeight();
                    });
                    resizeObserver.observe(editor);
                } else {

                    window.addEventListener('resize', syncLineNumbersHeight);
                }
            }
        });
    </script>
</body>
</html>
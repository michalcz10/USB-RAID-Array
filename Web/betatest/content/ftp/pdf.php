<?php
session_start();

if(!isset($_SESSION['uname'])){
    header("location: ../../index.php");
    session_destroy();
    exit;
}

require 'config.php';
$sftp = initializeSFTP($host, $username, $password);

if (!isset($_GET['file'])) {
    die("No file specified");
}

$filePath = $_GET['file'];
$fileName = basename($filePath);
$fileSize = $sftp->stat($filePath)['size'];

if (isset($_GET['stream'])) {
    session_write_close();

    while (ob_get_level()) {
        ob_end_clean();
    }

    if (ini_get('zlib.output_compression')) {
        ini_set('zlib.output_compression', 'Off');
    }

    $start = 0;
    $end = $fileSize - 1;
    $length = $fileSize;

    if (isset($_SERVER['HTTP_RANGE'])) {
        $rangeHeader = $_SERVER['HTTP_RANGE'];
        $matches = [];
        if (preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            $start = intval($matches[1]);
            
            if (!empty($matches[2])) {
                $end = intval($matches[2]);
            }
            
            $length = $end - $start + 1;

            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        }
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
    header("Accept-Ranges: bytes");
    header("Content-Length: $length");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    set_time_limit(0);

    $minChunkSize = 64 * 1024;    // 64KB minimum
    $maxChunkSize = 2 * 1024 * 1024; // 2MB maximum
    $chunkSize = 256 * 1024;      // Start with 256KB

    $currentPosition = $start;
    $bytesRemaining = $length;
    $lastChunkTime = microtime(true);

    try {
        while ($bytesRemaining > 0) {
            if (connection_aborted()) {
                break;
            }

            $readSize = min($chunkSize, $bytesRemaining);

            $chunkData = $sftp->get($filePath, false, $currentPosition, $readSize);
            
            if ($chunkData !== false) {
                $bytesSent = strlen($chunkData);
                echo $chunkData;
                $bytesRemaining -= $bytesSent;
                $currentPosition += $bytesSent;
                
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                $currentTime = microtime(true);
                $timeDiff = $currentTime - $lastChunkTime;
                $lastChunkTime = $currentTime;

                if ($timeDiff > 0) {
                    $speed = $bytesSent / $timeDiff;
                    $chunkSize = min(
                        max($minChunkSize, $chunkSize * (($speed > 512 * 1024) ? 1.5 : 0.8)),
                        $maxChunkSize
                    );
                }
            } else {
                break;
            }
        }
    } catch (Exception $e) {
        error_log("Streaming error: " . $e->getMessage());
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Viewer</title>
    <link rel="icon" href="../../img/favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="../../css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/pdf.css">
    <script src="../../js/bootstrap.bundle.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.js"></script>
</head>
<body>
<div class="d-flex justify-content-end p-3">
    <button id="themeToggle" class="btn btn-sm theme-toggle">
        <i class="bi"></i>
        <span id="themeText"></span>
    </button>
</div>
<div class="custom-container">
    <header class="text-center border-bottom m-5">
        <h1 class="mb-4">PDF Viewer</h1>
        <div class="mb-3">
            <a href="index.php?path=<?= urlencode(dirname($filePath)) ?>" class="btn btn-primary">Back to Files</a>
        </div>
    </header>

    <div id="pdfContainer" class="container-fluid position-relative">
        <div id="loadingMessage" class="d-flex align-items-center justify-content-center w-100 h-100">
            <div class="bg-dark bg-opacity-75 text-white p-3 rounded">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Loading PDF...
            </div>
        </div>
    </div>

    <div class="controls">
        <div class="btn-group" role="group" aria-label="PDF Navigation">
            <button id="prev" class="btn btn-light border">
                <i class="bi bi-chevron-left"></i> Previous
            </button>
            <button class="btn btn-light border disabled">
                Page <span id="pageNum"></span> of <span id="pageCount"></span>
            </button>
            <button id="next" class="btn btn-light border">
                Next <i class="bi bi-chevron-right"></i>
            </button>
            <button id="fullscreen" class="btn btn-light border">
                <i class="bi bi-fullscreen"></i>
            </button>
        </div>
    </div>
    <div class="actionButton">
        <?php if (isset($_SESSION["downPer"]) && $_SESSION["downPer"] == true) : ?>
                <a href="download.php?file=<?= urlencode($filePath) ?>" class="btn btn-success m-3">Download</a>
        <?php endif; ?>
    </div>

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
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    
            const url = 'pdf.php?file=<?= urlencode($filePath) ?>&stream=1';
            const pdfFileName = '<?= htmlspecialchars($fileName) ?>';
            let currentPage = 1;
            let pdfDoc = null;

            const PdfPageStorage = {
                getStorageKey() {
                    return `pdf_page_${pdfFileName}`;
                },
                
                savePage(pageNum) {
                    localStorage.setItem(this.getStorageKey(), pageNum.toString());
                },
                
                loadPage() {
                    return parseInt(localStorage.getItem(this.getStorageKey())) || 1;
                }
            };
    
            async function loadPDF() {
                try {
                    pdfDoc = await pdfjsLib.getDocument(url).promise;
                    document.getElementById('pageCount').textContent = pdfDoc.numPages;

                    currentPage = Math.min(PdfPageStorage.loadPage(), pdfDoc.numPages);
                    
                    renderPage(currentPage);
                    document.getElementById('loadingMessage').classList.add('d-none');
                } catch (error) {
                    console.error('Error loading PDF:', error);
                    const loadingMessage = document.getElementById('loadingMessage');
                    loadingMessage.querySelector('div').classList.add('bg-danger');
                    loadingMessage.querySelector('div').innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error loading PDF';
                }
            }
    
            async function renderPage(pageNumber) {
                const page = await pdfDoc.getPage(pageNumber);
                const pageContainer = document.createElement('div');
                pageContainer.className = 'page-container';
            
                const windowWidth = window.innerWidth;
                const windowHeight = window.innerHeight;
                const viewport = page.getViewport({ scale: 1.0 });
                
                const pixelRatio = window.devicePixelRatio || 1;
                const widthScale = (windowWidth / viewport.width);
                const heightScale = (windowHeight / viewport.height);
                
                let scale;
                if (isFullscreen) {
                    scale = Math.min(widthScale, heightScale) * 0.95;
                } else if (windowWidth < 768) {
                    scale = widthScale * 0.95;
                } else {
                    scale = Math.min(widthScale, heightScale, 2);
                }
                
                const scaledViewport = page.getViewport({ scale: scale * pixelRatio });
                
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                
                canvas.height = scaledViewport.height;
                canvas.width = scaledViewport.width;
                
                canvas.style.width = `${scaledViewport.width / pixelRatio}px`;
                canvas.style.height = `${scaledViewport.height / pixelRatio}px`;
            
                pageContainer.appendChild(canvas);
                document.getElementById('pdfContainer').innerHTML = '';
                document.getElementById('pdfContainer').appendChild(pageContainer);
                document.getElementById('pageNum').textContent = pageNumber;
            
                await page.render({
                    canvasContext: context,
                    viewport: scaledViewport // Use scaledViewport instead of viewport
                }).promise;
            
                PdfPageStorage.savePage(pageNumber);
            }
            document.getElementById('prev').addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderPage(currentPage);
                }
            });
    
            document.getElementById('next').addEventListener('click', () => {
                if (currentPage < pdfDoc.numPages) {
                    currentPage++;
                    renderPage(currentPage);
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && isFullscreen) {
                    document.getElementById('fullscreen').click();
                } else if (e.key === 'ArrowLeft' && currentPage > 1) {
                    currentPage--;
                    renderPage(currentPage);
                } else if (e.key === 'ArrowRight' && currentPage < pdfDoc.numPages) {
                    currentPage++;
                    renderPage(currentPage);
                }
            });
    
            window.addEventListener('resize', () => {
                if (currentPage) {
                    renderPage(currentPage);
                }
            });

            let isFullscreen = false;

            document.getElementById('fullscreen').addEventListener('click', () => {
                const container = document.querySelector('.custom-container');
                const fullscreenBtn = document.getElementById('fullscreen');
                const fullscreenIcon = fullscreenBtn.querySelector('i');
                
                if (!isFullscreen) {
                    container.classList.add('fullscreen-mode');
                    document.body.style.overflow = 'hidden';
                    fullscreenIcon.classList.remove('bi-fullscreen');
                    fullscreenIcon.classList.add('bi-fullscreen-exit');
                } else {
                    container.classList.remove('fullscreen-mode');
                    document.body.style.overflow = '';
                    fullscreenIcon.classList.remove('bi-fullscreen-exit');
                    fullscreenIcon.classList.add('bi-fullscreen');
                }
                
                isFullscreen = !isFullscreen;
                renderPage(currentPage);
            });
                
            loadPDF();
        </script>
    <script src="../../js/theme.js"></script>
</body>
</html>
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
ini_set('memory_limit', '512M');

require 'config.php';
$sftp = initializeSFTP($host, $username, $password);

if (!isset($_GET['file'])) {
    die("No file specified");
}

if (isset($_POST['createShare'])) {
    $duration = intval($_POST['duration']);
    $downloadAllowed = isset($_POST['downloadAllowed']) ? 1 : 0;
    $filePath = $_POST['filePath'];

    $token = bin2hex(random_bytes(16));

    date_default_timezone_set('Europe/Prague');

    $expiration = date('Y-m-d H:i:s', strtotime("+$duration hours"));

    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("INSERT INTO share (token, file_path, expiration, download_allowed) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $token, $filePath, $expiration, $downloadAllowed);
    
    if ($stmt->execute()) {
        $shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . 
                    dirname($_SERVER['PHP_SELF']) . "/shared.php?token=$token";

        $_SESSION['shareCreated'] = true;
        $_SESSION['shareUrl'] = $shareUrl;
        $_SESSION['shareExpiration'] = $expiration;

        header("Location: view.php?file=" . urlencode($filePath) . "&shared=1");
        exit;
    } else {
        $_SESSION['shareError'] = "Failed to create share: " . $conn->error;
        header("Location: view.php?file=" . urlencode($filePath) . "&shared=0");
        exit;
    }
    
    $stmt->close();
    $conn->close();
}

$shareCreated = false;
$shareUrl = '';
$shareExpiration = '';
$shareError = '';

if (isset($_GET['shared'])) {
    if ($_GET['shared'] == '1' && isset($_SESSION['shareCreated']) && $_SESSION['shareCreated']) {
        $shareCreated = true;
        $shareUrl = $_SESSION['shareUrl'];
        $shareExpiration = $_SESSION['shareExpiration'];
    } elseif ($_GET['shared'] == '0' && isset($_SESSION['shareError'])) {
        $shareError = $_SESSION['shareError'];
    }
}

function getDatabaseConnection() {
    $servername = "localhost:3306";
    $username = "UNAME";
    $password = "PSWD";
    $db = "usbraidlogin";

    $conn = new mysqli($servername, $username, $password, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$filePath = $_GET['file'];
$fileName = basename($filePath);
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!$sftp->stat($filePath)) {
    die("File not found: $filePath");
}

$imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
$videoTypes = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
$audioTypes = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'];

$isImage = in_array($fileExtension, $imageTypes);
$isVideo = in_array($fileExtension, $videoTypes);
$isAudio = in_array($fileExtension, $audioTypes);

if (!$isImage && !$isVideo && !$isAudio) {
    die("Unsupported file type");
}

$fileSize = $sftp->stat($filePath)['size'];

$mimeMap = [
    'mp4' => 'video/mp4',
    'webm' => 'video/mp4',
    'ogg' => 'video/mp4',
    'mov' => 'video/mp4',
    'avi' => 'video/mp4',
    'mkv' => 'video/mp4',

    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',

    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'm4a' => 'audio/mp4',
    'flac' => 'audio/flac',
    'aac' => 'audio/aac',
];

$mimeType = isset($mimeMap[$fileExtension]) ? $mimeMap[$fileExtension] : 'application/octet-stream';

if (isset($_GET['stream'])) {
    // Close session to allow other scripts to run
    session_write_close();

    // Prevent output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Disable output compression
    if (ini_get('zlib.output_compression')) {
        ini_set('zlib.output_compression', 'Off');
    }

    // Initialize range variables
    $start = 0;
    $end = $fileSize - 1;
    $length = $fileSize;

    // Handle range requests
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

    // Set headers for streaming
    header("Content-Type: $mimeType");
    header("Accept-Ranges: bytes");
    header("Content-Length: $length");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Debug headers
    if (isset($_GET['debug'])) {
        header("X-Stream-Info: Chunked SFTP Streaming");
        header("X-File-Path: " . basename($filePath));
        header("X-File-Size: $fileSize");
    }

    // Set timeout to 0 to prevent script termination
    set_time_limit(0);

    // Chunk settings
    $minChunkSize = 64 * 1024;    // 64KB minimum
    $maxChunkSize = 2 * 1024 * 1024; // 2MB maximum
    $chunkSize = 256 * 1024;      // Start with 256KB

    $currentPosition = $start;
    $bytesRemaining = $length;
    $lastChunkTime = microtime(true);

    try {
        while ($bytesRemaining > 0) {
            // Check client connection and server status
            if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
                if (isset($_GET['debug'])) {
                    error_log("Client disconnected at position $currentPosition");
                }
                break;
            }

            // Calculate adaptive chunk size
            $readSize = min($chunkSize, $bytesRemaining);
            
            // Get chunk from SFTP
            $chunkData = $sftp->get($filePath, false, $currentPosition, $readSize);
            
            if ($chunkData !== false) {
                $bytesSent = strlen($chunkData);
                
                // Output chunk
                echo $chunkData;
                $bytesRemaining -= $bytesSent;
                $currentPosition += $bytesSent;
                
                // Flush buffers
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                // Adaptive chunk sizing based on transfer speed
                $currentTime = microtime(true);
                $timeDiff = $currentTime - $lastChunkTime;
                $lastChunkTime = $currentTime;

                if ($timeDiff > 0) {
                    $speed = $bytesSent / $timeDiff; // bytes/second
                    $chunkSize = min(
                        max($minChunkSize, $chunkSize * (($speed > 512 * 1024) ? 1.5 : 0.8)),
                        $maxChunkSize
                    );
                }

                if (isset($_GET['debug'])) {
                    header("X-Chunk-Size: $bytesSent");
                    header("X-Position: $currentPosition");
                    header("X-Remaining: $bytesRemaining");
                }
            } else {
                error_log("SFTP read error at position $currentPosition");
                break;
            }

            // Throttle to prevent CPU overload
            usleep(100000); // 100ms
        }
    } catch (Exception $e) {
        error_log("Streaming error: " . $e->getMessage());
        if (isset($_GET['debug'])) {
            header("X-Stream-Error: " . $e->getMessage());
        }
    }

    if (isset($_GET['debug'])) {
        error_log("Streaming completed. Sent $currentPosition bytes of $fileSize");
    }
    exit;
}
?>


<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light' ?>">
<head>
    <title>Media Viewer - <?= htmlspecialchars($fileName) ?></title>
    <link rel="icon" href="../../img/favicon.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="../../css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/view.css">
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
        <h1>Media Viewer</h1>
        <div class="mb-3 p-3">
            <a href="index.php?path=<?= urlencode(dirname($filePath)) ?>" class="btn btn-primary">Back to Files</a>
        </div>
    </header>

    <section class="row">
        <article class="col-12">
            <div class="media-container position-relative">
                <?php if ($isImage): ?>
                    <img src="view.php?file=<?= urlencode($filePath) ?>&stream=1" alt="<?= htmlspecialchars($fileName) ?>" class="img-fluid">
                <?php elseif ($isVideo): ?>
                <video id="videoPlayer" controls autoplay playsinline>
                    <source src="view.php?file=<?= urlencode($filePath) ?>&stream=1" type="<?= $mimeType ?>">
                    Your browser does not support this video format. Try downloading the file instead.
                </video>
                <?php if($fileExtension !== 'mp4'): ?>
                <div class="alert alert-warning mt-2">
                    Note: For best results, use MP4 format (H.264 codec). Other formats may not play correctly in all browsers.
                </div>
                <?php endif; ?>
                <?php elseif ($isAudio): ?>
                    <audio controls autoplay style="width: 80%;">
                        <source src="view.php?file=<?= urlencode($filePath) ?>&stream=1" type="<?= $mimeType ?>">
                        Your browser does not support the audio element.
                    </audio>
                <?php endif; ?>

                <?php if (isset($_SESSION["downPer"]) && $_SESSION["downPer"] == true) : ?>
                <a href="download.php?file=<?= urlencode($filePath) ?>" class="btn btn-success m-3">Download</a>
                <?php endif; ?>
                
                <!-- Add Share Button -->
                <button type="button" class="btn btn-info m-3" data-bs-toggle="modal" data-bs-target="#shareModal">
                    <i class="bi bi-share"></i> Share
                </button>
            </div>
            
            <!-- Share success message -->
            <?php if ($shareCreated): ?>
            <div class="alert alert-success mt-3">
                <p>Share link created successfully!</p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="shareUrl" value="<?= htmlspecialchars($shareUrl) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyShareUrl()">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
                <p class="small text-muted">This link will expire on <?= date('F j, Y, g:i a', strtotime($shareExpiration)) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($shareError): ?>
            <div class="alert alert-danger mt-3">
                <?= htmlspecialchars($shareError) ?>
            </div>
            <?php endif; ?>

            <div class="file-info mt-4">
                <h4>File Information</h4>
                <table class="table table-bordered w-auto mx-auto text-wrap">
                    <tr>
                        <th>File Name</th>
                        <td class="text-break"><?= htmlspecialchars($fileName) ?></td>
                    </tr>
                    <tr>
                        <th>File Type</th>
                        <td><?= htmlspecialchars(strtoupper($fileExtension)) ?></td>
                    </tr>
                    <tr>
                        <th>MIME Type</th>
                        <td class="text-break"><?= htmlspecialchars($mimeType) ?></td>
                    </tr>
                    <tr>
                        <th>File Size</th>
                        <td><?= formatBytes($fileSize) ?></td>
                    </tr>
                </table>
            </div>
        </article>
    </section>

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

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareModalLabel">Share "<?= htmlspecialchars($fileName) ?>"</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="">
                    <input type="hidden" name="filePath" value="<?= htmlspecialchars($filePath) ?>">
                    
                    <div class="mb-3">
                        <label for="duration" class="form-label">Link expires after</label>
                        <select class="form-select" id="duration" name="duration">
                            <option value="1">1 hour</option>
                            <option value="6">6 hours</option>
                            <option value="24" selected>1 day</option>
                            <option value="168">1 week</option>
                            <option value="720">30 days</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="downloadAllowed" name="downloadAllowed">
                        <label class="form-check-label" for="downloadAllowed">Allow downloading</label>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="createShare" class="btn btn-primary">Create Share Link</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function copyShareUrl() {
    const shareUrl = document.getElementById('shareUrl');
    shareUrl.select();
    document.execCommand('copy');
    
    // Show feedback
    const button = shareUrl.nextElementSibling;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check"></i> Copied!';
    
    setTimeout(() => {
        button.innerHTML = originalText;
    }, 2000);
}
</script>

<script src="../../js/theme.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const mediaElement = document.querySelector('video, audio');
    
    if (mediaElement) {
        // Restore volume from localStorage
        const savedVolume = localStorage.getItem('mediaVolume');
        if (savedVolume !== null) {
            mediaElement.volume = parseFloat(savedVolume);
        }

        // Save volume to localStorage when it changes
        mediaElement.addEventListener('volumechange', () => {
            localStorage.setItem('mediaVolume', mediaElement.volume);
        });
    }
});
</script>
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
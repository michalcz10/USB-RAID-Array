<?php
// Include this at the top to see potential errors
// Comment out in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    session_destroy();
    header("location: ../../index.php");
    exit();
}

require '../../vendor/autoload.php';

use phpseclib3\Net\SSH2;

$ssh_host = 'localhost';
$ssh_username = 'uname';
$ssh_password = 'pswd';

$ssh = new SSH2($ssh_host);
if (!$ssh->login($ssh_username, $ssh_password)) {
    die('SSH login failed');
}

function getDiskData($ssh, $device) {
    return $ssh->exec("df -h $device");
}

function getSystemData($ssh) {
    // Get memory info using awk to clean up the output
    $meminfo = $ssh->exec("free -m | awk 'NR==2{printf \"%s,%s,%s,\", $2,$3,$7} NR==3{printf \"%s,%s,%s\", $2,$3,$4}'");
    
    // Use vmstat to get CPU idle percentage, more reliable and commonly available
    $cpuIdle = $ssh->exec("vmstat 1 2 | tail -1 | awk '{print $15}'");
    
    return [
        'storage' => [
            'system' => parseDiskData($ssh->exec('df -h /dev/ubuntu-vg/ubuntu-lv')),
            'data' => parseDiskData($ssh->exec('df -h /dev/sdb1'))
        ],
        'raid' => parseRaidStatus($ssh->exec('cat /proc/mdstat')),
        'resources' => parseSystemStats($cpuIdle, $meminfo),
        'lastUpdate' => date('Y-m-d H:i:s')
    ];
}

$systemData = getSystemData($ssh);

$monitors = [
    [
        'id' => 'SystemDisk',
        'title' => 'System Disk (sda)',
        'icon' => 'hdd',
        'color' => 'primary',
        'type' => 'storage',
        'source' => 'system'
    ],
    [
        'id' => 'DataDisk',
        'title' => 'Data Disk (sdb)',
        'icon' => 'hdd',
        'color' => 'primary',
        'type' => 'storage',
        'source' => 'data'
    ],
    [
        'id' => 'RAID',
        'title' => 'RAID Status',
        'icon' => 'shield-check',
        'color' => 'warning',
        'type' => 'raid'
    ],
    [
        'id' => 'CPU',
        'title' => 'CPU Usage',
        'icon' => 'cpu',
        'color' => 'info',
        'type' => 'resources',
        'source' => 'cpu'
    ],
    [
        'id' => 'Memory',
        'title' => 'Memory Usage',
        'icon' => 'memory',
        'color' => 'success',
        'type' => 'resources',
        'source' => 'memory'
    ]
];

function parseDiskData($output) {
    $lines = explode("\n", trim($output));
    if (count($lines) < 2) return null;
    
    $values = preg_split('/\s+/', trim($lines[1]));
    return [
        'size' => $values[1] ?? 'N/A',
        'used' => $values[2] ?? 'N/A',
        'available' => $values[3] ?? 'N/A',
        'usage' => $values[4] ?? '0%'
    ];
}

function parseRaidStatus($output) {
    if (empty($output)) {
        return [
            'active' => false,
            'status' => 'unknown',
            'type' => 'N/A'
        ];
    }

    $status = [
        'active' => false,
        'status' => 'unknown',
        'type' => 'N/A'
    ];

    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (preg_match('/active\s+(\w+)/', $line, $matches)) {
            $status['active'] = true;
            $status['type'] = $matches[1] ?? 'N/A';
        }
        if (strpos($line, '[UU]') !== false) {
            $status['status'] = 'healthy';
        } elseif (strpos($line, '_') !== false) {
            $status['status'] = 'degraded';
        }
    }

    return $status;
}

function parseSystemStats($cpuOutput, $memOutput) {
    // Parse CPU stats - convert idle percentage to usage percentage
    $idlePercent = floatval(trim(str_replace(',', '.', $cpuOutput)));
    $cpuUsage = 100 - $idlePercent; // Convert idle to usage percentage
    if (is_nan($cpuUsage) || $cpuUsage < 0 || $cpuUsage > 100) {
        $cpuUsage = 0; // Default to 0% if parsing fails
    }
    $cpu = ['usage' => number_format($cpuUsage, 1)];

    // Parse memory stats
    $parts = explode(',', $memOutput);
    if (count($parts) >= 6) {
        $memTotal = intval($parts[0]);
        $memUsed = intval($parts[1]);
        $memAvail = intval($parts[2]);
        $swapTotal = intval($parts[3]);
        $swapUsed = intval($parts[4]);
        $swapFree = intval($parts[5]);

        $memory = [
            'total' => number_format($memTotal / 1024, 2) . 'G',
            'used' => number_format($memUsed / 1024, 2) . 'G',
            'available' => number_format($memAvail / 1024, 2) . 'G',
            'usage' => ($memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0) . '%',
            'swap_total' => number_format($swapTotal / 1024, 2) . 'G',
            'swap_used' => number_format($swapUsed / 1024, 2) . 'G',
            'swap_free' => number_format($swapFree / 1024, 2) . 'G',
            'swap_usage' => ($swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 1) : 0) . '%'
        ];
    } else {
        $memory = [
            'total' => '0G',
            'used' => '0G',
            'available' => '0G',
            'usage' => '0%',
            'swap_total' => '0G',
            'swap_used' => '0G',
            'swap_free' => '0G',
            'swap_usage' => '0%'
        ];
    }

    return ['cpu' => $cpu, 'memory' => $memory];
}

function convertToBytes($size) {
    if (preg_match('/^([\d.]+)([KMGT]?)i?B?$/', trim($size), $matches)) {
        $value = floatval($matches[1]);
        $unit = strtoupper($matches[2]);
        
        switch ($unit) {
            case 'P': $value *= 1024;
            case 'T': $value *= 1024;
            case 'G': $value *= 1024;
            case 'M': $value *= 1024;
            case 'K': $value *= 1024;
        }
        
        return $value;
    }
    return 0;
}

function formatBytes($bytes, $forceUnit = '') {
    $units = ['B', 'K', 'M', 'G', 'T', 'P'];
    $bytes = max($bytes, 0);
    
    if ($forceUnit) {
        $unitIndex = array_search($forceUnit, $units);
        if ($unitIndex !== false) {
            $bytes /= pow(1024, $unitIndex);
            return round($bytes, 2) . $forceUnit;
        }
    }
    
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Server Status</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="../../img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/serverstat.css">
    <script src="../../js/bootstrap.bundle.js"></script>
</head>

<body class="container-fluid text-center">
<div class="d-flex justify-content-end p-3">
    <button id="themeToggle" class="btn btn-sm theme-toggle">
        <i class="bi"></i>
        <span id="themeText"></span>
    </button>
</div>

<header class="row border-bottom m-5">
    <h1>Server Status</h1>
    <div class="mb-3 p-3">
        <a href="../logout.php" class="btn btn-danger">Logout</a>
        <a href="../changepassword.php" class="btn btn-warning">Change Password</a>
        <a href="../adminpanel.php" class="btn btn-primary">Admin Panel</a>
        <a href="index.php" class="btn btn-primary">SFTP</a>
    </div>
</header>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm bg-body-tertiary">
                <div class="card-header monitor-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h4 mb-0">
                            <i class="bi bi-server text-primary me-2"></i>
                            Server Disk Status
                        </h1>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-clock me-1"></i>
                            <?= htmlspecialchars($systemData['lastUpdate']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="row g-4">
                        <?php foreach ($monitors as $monitor): ?>
                            <div class="col-md-6">
                                <div class="monitor-card card h-100 border-<?= $monitor['color'] ?>">
                                    <div class="card-header bg-<?= $monitor['color'] ?> bg-opacity-10">
                                        <h3 class="h5 mb-0">
                                            <i class="bi bi-<?= $monitor['icon'] ?> text-<?= $monitor['color'] ?> me-2"></i>
                                            <?= $monitor['title'] ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($monitor['type'] === 'storage'): 
                                            $diskData = $systemData['storage'][$monitor['source']];
                                            $usagePercent = intval(rtrim($diskData['usage'], '%'));
                                        ?>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Storage Usage</span>
                                                    <span><?= $diskData['usage'] ?></span>
                                                </div>
                                                <div class="progress" style="height: 10px">
                                                    <div class="progress-bar bg-<?= $usagePercent > 90 ? 'danger' : ($usagePercent > 75 ? 'warning' : 'success') ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $usagePercent ?>%" 
                                                         aria-valuenow="<?= $usagePercent ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row text-center g-2">
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Total</div>
                                                        <div class="fw-bold"><?= $diskData['size'] ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Used</div>
                                                        <div class="fw-bold"><?= $diskData['used'] ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Free</div>
                                                        <div class="fw-bold"><?= $diskData['available'] ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif ($monitor['type'] === 'raid'): ?>
                                            <div class="text-center">
                                                <div class="display-4 mb-2">
                                                    <i class="bi bi-<?= $systemData['raid']['active'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                                </div>
                                                <h4 class="h6"><?= $systemData['raid']['type'] ?> Array</h4>
                                                <span class="badge bg-<?= $systemData['raid']['status'] === 'healthy' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($systemData['raid']['status']) ?>
                                                </span>
                                            </div>
                                        <?php elseif ($monitor['type'] === 'resources' && $monitor['source'] === 'cpu'): ?>
                                            <div class="cpu-gauge">
                                                <canvas id="cpuGauge" width="150" height="150" data-value="<?= $systemData['resources']['cpu']['usage'] ?>"></canvas>
                                            </div>
                                        <?php elseif ($monitor['type'] === 'resources' && $monitor['source'] === 'memory'): 
                                            $memData = $systemData['resources']['memory'];
                                            $usagePercent = floatval(rtrim($memData['usage'], '%'));
                                            $swapPercent = floatval(rtrim($memData['swap_usage'], '%'));
                                        ?>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Memory Usage</span>
                                                    <span><?= $memData['usage'] ?></span>
                                                </div>
                                                <div class="progress" style="height: 10px">
                                                    <div class="progress-bar bg-<?= $usagePercent > 90 ? 'danger' : ($usagePercent > 75 ? 'warning' : 'success') ?>" 
                                                        role="progressbar" 
                                                        style="width: <?= $usagePercent ?>%" 
                                                        aria-valuenow="<?= $usagePercent ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row text-center g-2 mb-3">
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Total</div>
                                                        <div class="fw-bold"><?= $memData['total'] ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Used</div>
                                                        <div class="fw-bold"><?= $memData['used'] ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Available</div>
                                                        <div class="fw-bold"><?= $memData['available'] ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Swap Usage</span>
                                                    <span><?= $memData['swap_usage'] ?></span>
                                                </div>
                                                <div class="progress" style="height: 10px">
                                                    <div class="progress-bar bg-<?= $swapPercent > 90 ? 'danger' : ($swapPercent > 75 ? 'warning' : 'success') ?>" 
                                                        role="progressbar" 
                                                        style="width: <?= $swapPercent ?>%" 
                                                        aria-valuenow="<?= $swapPercent ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row text-center g-2">
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Total</div>
                                                        <div class="fw-bold"><?= $memData['swap_total'] ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Used</div>
                                                        <div class="fw-bold"><?= $memData['swap_used'] ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 border rounded">
                                                        <div class="small text-muted">Free</div>
                                                        <div class="fw-bold"><?= $memData['swap_free'] ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="d-flex flex-column justify-content-center align-items-center p-3 border-top gap-3">
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
<script src="../../js/theme.js"></script>
<script>
let refreshInterval;
let isRefreshing = false;

function updateData(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    
    // Update monitor cards
    document.querySelectorAll('.monitor-card').forEach((card, index) => {
        const newCard = doc.querySelectorAll('.monitor-card')[index];
        if (newCard && card.querySelector('.card-body')) {
            card.querySelector('.card-body').innerHTML = newCard.querySelector('.card-body').innerHTML;
            
            // Redraw CPU gauge if this is the CPU card
            if (card.querySelector('.cpu-gauge')) {
                const cpuValue = parseFloat(newCard.querySelector('canvas').getAttribute('data-value') || '0');
                drawCpuGauge(cpuValue);
            }
        }
    });
    
    // Update last update time
    const newLastUpdate = doc.querySelector('.badge.bg-light.text-dark')?.innerHTML;
    const currentLastUpdate = document.querySelector('.badge.bg-light.text-dark');
    if (newLastUpdate && currentLastUpdate && newLastUpdate !== currentLastUpdate.innerHTML) {
        currentLastUpdate.innerHTML = newLastUpdate;
    }

    // Update CPU gauge if needed
    const cpuValue = document.querySelector('.cpu-gauge .h4')?.textContent;
    if (cpuValue) {
        drawCpuGauge(parseFloat(cpuValue));
    }
}

function startRefresh() {
    if (!refreshInterval) {
        refreshInterval = setInterval(() => {
            if (!isRefreshing) {
                isRefreshing = true;
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        updateData(html);
                        isRefreshing = false;
                    })
                    .catch(() => {
                        isRefreshing = false;
                    });
            }
        }, 5000);
    }
}

function stopRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

// Start refresh when page is visible
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        stopRefresh();
    } else {
        startRefresh();
    }
});

// Initial setup
startRefresh();
drawCpuGauge(parseFloat(document.querySelector('.cpu-gauge .h4')?.textContent || '0'));

function drawCpuGauge(value) {
    const canvas = document.getElementById('cpuGauge');
    if (!canvas) return;
    
    // Ensure value is a valid number between 0 and 100
    value = parseFloat(value) || 0;
    value = Math.max(0, Math.min(100, value));
    
    const ctx = canvas.getContext('2d');
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = Math.min(centerX, centerY) - 10;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw background circle
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
    ctx.strokeStyle = getComputedStyle(document.body).getPropertyValue('--bs-border-color');
    ctx.lineWidth = 10;
    ctx.stroke();

    // Draw value arc
    const startAngle = -Math.PI / 2;
    const endAngle = startAngle + (Math.PI * 2 * value / 100);
    
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, startAngle, endAngle);
    ctx.strokeStyle = value > 90 ? '#dc3545' : value > 75 ? '#ffc107' : '#198754';
    ctx.lineWidth = 10;
    ctx.stroke();

    // Draw percentage text inside the circle
    ctx.font = 'bold 20px Arial';
    ctx.fillStyle = getComputedStyle(document.body).getPropertyValue('--bs-body-color');
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(`${value.toFixed(1)}%`, centerX, centerY - 10);

    // Draw 'CPU USAGE' text below the percentage
    ctx.font = '12px Arial';
    ctx.fillText('CPU USAGE', centerX, centerY + 15);
}

// Initial CPU gauge draw
window.addEventListener('load', function() {
    const cpuValue = parseFloat(document.querySelector('.cpu-gauge canvas')?.getAttribute('data-value') || '0');
    drawCpuGauge(cpuValue);
});
</script>
</body>
</html>
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

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/workspace.php';

// The configuration helper authenticates using SSH_HOST, SSH_USER and SSH_PASS.
$ssh = getSSHConnection();

require_once __DIR__ . '/lib/serverstats.php';

$systemData = getSystemData($ssh, $defaultPath);

$monitors = [
    [
        'id' => 'SystemDisk',
        'title' => 'System filesystem',
        'icon' => 'hdd',
        'color' => 'primary',
        'type' => 'storage',
        'source' => 'system'
    ],
    [
        'id' => 'DataDisk',
        'title' => 'Storage filesystem',
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Server Status</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="../../img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= workspaceAsset('../../', 'css/modern.css') ?>">
    <link rel="stylesheet" href="<?= workspaceAsset('../../', 'css/workspace.css') ?>">
    <link rel="stylesheet" href="<?= workspaceAsset('../../', 'content/ftp/css/serverstat.css') ?>">
    <script src="<?= workspaceAsset('../../', 'js/theme.js') ?>"></script>
    <script src="<?= workspaceAsset('../../', 'js/ui.js') ?>" defer></script>
</head>

<?php renderWorkspaceStart([
    'root' => '../../',
    'active' => 'server',
    'section' => 'Server status',
    'eyebrow' => 'Server overview',
    'title' => 'Server status',
    'description' => 'Keep an eye on your storage, RAID health and system resources.',
    'connected' => true,
    'class' => 'server-page',
]); ?>


<div class="content-toolbar"><p>Live statistics · refreshes every 5 seconds</p><span class="badge bg-light text-dark"><i class="ui-icon icon-clock" aria-hidden="true"></i> <?= htmlspecialchars($systemData['lastUpdate']) ?></span></div>
<div class="stats-grid">
    <?php foreach ($monitors as $monitor): ?>
    <section class="stats-cell" aria-label="<?= htmlspecialchars($monitor['title']) ?>">
        <div class="monitor-card card h-100">
            <div class="card-header"><h3 class="mb-0"><i class="ui-icon icon-<?= $monitor['icon'] ?> text-primary me-2" aria-hidden="true"></i><?= htmlspecialchars($monitor['title']) ?></h3></div>
            <div class="card-body">
                <?php if ($monitor['type'] === 'storage'):
                    $diskData = $systemData['storage'][$monitor['source']];
                ?>
                    <?php if (!$diskData['available_data']): ?>
                        <p class="text-muted" role="status">Storage statistics are unavailable for this path.</p>
                    <?php else:
                        $usagePercent = min(100, (int) rtrim($diskData['usage'], '%'));
                    ?>
                        <p class="small text-muted"><?= htmlspecialchars($diskData['filesystem']) ?> · <?= htmlspecialchars($monitor['source'] === 'system' ? '/' : $defaultPath) ?></p>
                        <div class="d-flex justify-content-between"><span>Storage usage</span><strong><?= htmlspecialchars($diskData['usage']) ?></strong></div>
                        <div class="progress mb-4"><div class="progress-bar bg-<?= $usagePercent > 90 ? 'danger' : ($usagePercent > 75 ? 'warning' : 'success') ?>" role="progressbar" style="width:<?= $usagePercent ?>%" aria-label="Storage usage" aria-valuenow="<?= $usagePercent ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                        <div class="row text-center">
                            <?php foreach (['size' => 'Total', 'used' => 'Used', 'available' => 'Free'] as $key => $label): ?>
                            <div class="col-4"><div class="p-2 border rounded"><div class="small text-muted"><?= $label ?></div><div class="fw-bold"><?= htmlspecialchars($diskData[$key]) ?></div></div></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php elseif ($monitor['type'] === 'raid'): ?>
                    <div class="raid-summary">
                        <i class="ui-icon icon-<?= $systemData['raid']['status'] === 'healthy' ? 'check-circle-fill text-success' : 'x-circle-fill text-warning' ?>" aria-hidden="true"></i>
                        <h4><?= $systemData['raid']['arrays'] ? htmlspecialchars($systemData['raid']['type']) . ' Array' : 'Linux MD RAID' ?></h4>
                        <span class="badge"><?= htmlspecialchars(ucfirst($systemData['raid']['status'])) ?></span>
                        <?php foreach ($systemData['raid']['arrays'] as $array): ?>
                            <p class="small text-muted mt-2"><?= htmlspecialchars($array['name'] . ' · ' . $array['type'] . ' · ' . $array['status']) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($monitor['source'] === 'cpu'): ?>
                    <?php if (!$systemData['resources']['cpu']['available_data']): ?>
                        <p class="text-muted" role="status">CPU statistics are unavailable.</p>
                    <?php else: ?>
                        <div class="cpu-gauge"><canvas id="cpuGauge" width="170" height="170" data-value="<?= $systemData['resources']['cpu']['usage'] ?>" aria-label="CPU usage <?= $systemData['resources']['cpu']['usage'] ?> percent"></canvas></div>
                    <?php endif; ?>
                <?php else:
                    $memData = $systemData['resources']['memory'];
                ?>
                    <?php if (!$memData['available_data']): ?>
                        <p class="text-muted" role="status">Memory statistics are unavailable.</p>
                    <?php else: ?>
                        <?php foreach (['usage' => 'Memory usage', 'swap_usage' => 'Swap usage'] as $usageKey => $label):
                            $usagePercent = (float) rtrim($memData[$usageKey], '%');
                            $fields = $usageKey === 'usage' ? ['total' => 'Total', 'used' => 'Used', 'available' => 'Available'] : ['swap_total' => 'Total', 'swap_used' => 'Used', 'swap_free' => 'Free'];
                        ?>
                        <div class="resource-section">
                            <div class="d-flex justify-content-between"><span><?= $label ?></span><strong><?= htmlspecialchars($memData[$usageKey]) ?></strong></div>
                            <div class="progress mb-3"><div class="progress-bar bg-<?= $usagePercent > 90 ? 'danger' : ($usagePercent > 75 ? 'warning' : 'success') ?>" role="progressbar" style="width:<?= $usagePercent ?>%" aria-label="<?= $label ?>" aria-valuenow="<?= $usagePercent ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                            <div class="row text-center">
                                <?php foreach ($fields as $key => $fieldLabel): ?>
                                <div class="col-4"><div class="p-2 border rounded"><div class="small text-muted"><?= $fieldLabel ?></div><div class="fw-bold"><?= htmlspecialchars($memData[$key]) ?></div></div></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<?php renderWorkspaceEnd(); ?>

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

}

function startRefresh() {
    if (!refreshInterval) {
        refreshInterval = setInterval(() => {
            if (!isRefreshing) {
                isRefreshing = true;
                fetch(window.location.href)
                    .then(response => {
                        if (!response.ok || response.redirected) throw new Error('Status refresh failed');
                        return response.text();
                    })
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
if (!document.hidden) startRefresh();

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
    ctx.strokeStyle = getComputedStyle(document.body).getPropertyValue('--line');
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
    ctx.fillStyle = getComputedStyle(document.body).getPropertyValue('--text');
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
new MutationObserver(() => {
    const cpuValue = document.getElementById('cpuGauge')?.dataset.value;
    if (cpuValue !== undefined) drawCpuGauge(cpuValue);
}).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
</script>
</body>
</html>

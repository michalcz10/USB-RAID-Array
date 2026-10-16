<?php
// Pure parsers are kept separate so unavailable or malformed SSH output can be tested.
function getDiskData($ssh, $path) {
    // The command runs on Linux even when PHP runs on Windows. escapeshellarg()
    // follows the local OS, so quote explicitly for the remote POSIX shell.
    $quotedPath = "'" . str_replace("'", "'\\''", $path) . "'";
    return parseDiskData($ssh->exec('LC_ALL=C df -Pk -- ' . $quotedPath . ' 2>/dev/null'));
}

function getSystemData($ssh, $storagePath) {
    $meminfo = $ssh->exec("LC_ALL=C free -m | awk '/^Mem:/{printf \"%s,%s,%s,\", \$2,\$3,\$7} /^Swap:/{printf \"%s,%s,%s\", \$2,\$3,\$4}'");
    $cpuIdle = $ssh->exec("LC_ALL=C vmstat 1 2 | tail -1 | awk '{print \$15}'");

    return [
        'storage' => [
            'system' => getDiskData($ssh, '/'),
            'data' => getDiskData($ssh, $storagePath),
        ],
        'raid' => parseRaidStatus($ssh->exec('cat /proc/mdstat 2>/dev/null')),
        'resources' => parseSystemStats($cpuIdle, $meminfo),
        'lastUpdate' => date('Y-m-d H:i:s'),
    ];
}

function parseDiskData($output) {
    $unavailable = ['available_data' => false, 'size' => 'N/A', 'used' => 'N/A', 'available' => 'N/A', 'usage' => 'N/A', 'filesystem' => ''];
    if (!is_string($output)) return $unavailable;

    foreach (preg_split('/\r?\n/', trim($output)) as $line) {
        // POSIX df -Pk gives one filesystem per line and sizes in KiB.
        if (!preg_match('/^(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+(.+)$/', trim($line), $parts)) continue;
        return [
            'available_data' => true,
            'filesystem' => $parts[1],
            'size' => formatBytes((float) $parts[2] * 1024),
            'used' => formatBytes((float) $parts[3] * 1024),
            'available' => formatBytes((float) $parts[4] * 1024),
            'usage' => $parts[5] . '%',
        ];
    }
    return $unavailable;
}

function parseRaidStatus($output) {
    $status = ['active' => false, 'status' => 'unavailable', 'type' => 'N/A', 'arrays' => []];
    if (!is_string($output) || trim($output) === '') return $status;

    preg_match_all('/^(md\S+)\s*:\s*(active|inactive)\s+([^\r\n]+)((?:\r?\n[ \t]+[^\r\n]*)*)/m', $output, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $details = $match[3] . $match[4];
        preg_match('/\b(raid\d+|linear|multipath)\b/', $match[3], $level);
        $state = $match[2] === 'active' ? 'unknown' : 'inactive';
        if ($state !== 'inactive') {
            if (preg_match('/\[([U_]+)\]/', $details, $members)) {
                $state = strpos($members[1], '_') !== false ? 'degraded' : 'healthy';
            } elseif (in_array($level[1] ?? '', ['raid0', 'linear'], true)) {
                $state = 'active'; // Non-redundant arrays do not expose a [UU] health bitmap.
            }
            if (preg_match('/\b(recovery|resync|reshape|check|repair)\s*=/', $details)) {
                if ($state !== 'degraded') $state = 'syncing';
            }
            if (strpos($details, '(F)') !== false) $state = 'degraded';
        }
        $status['arrays'][] = ['name' => $match[1], 'type' => $level[1] ?? 'N/A', 'status' => $state];
    }
    if (!$status['arrays']) {
        $status['status'] = strpos($output, 'Personalities :') !== false ? 'not configured' : 'unavailable';
        return $status;
    }
    $states = array_column($status['arrays'], 'status');
    $status['active'] = !in_array('inactive', $states, true);
    $status['type'] = implode(', ', array_unique(array_column($status['arrays'], 'type')));
    foreach (['inactive', 'degraded', 'unknown', 'syncing', 'active', 'healthy'] as $state) {
        if (in_array($state, $states, true)) { $status['status'] = $state; break; }
    }
    return $status;
}

function parseSystemStats($cpuOutput, $memOutput) {
    $idle = is_string($cpuOutput) ? str_replace(',', '.', trim($cpuOutput)) : '';
    $cpuAvailable = is_numeric($idle) && (float) $idle >= 0 && (float) $idle <= 100;
    $cpu = ['available_data' => $cpuAvailable, 'usage' => $cpuAvailable ? number_format(100 - (float) $idle, 1, '.', '') : null];
    $memory = array_fill_keys(['total', 'used', 'available', 'usage', 'swap_total', 'swap_used', 'swap_free', 'swap_usage'], 'N/A');
    $memory['available_data'] = false;
    $parts = is_string($memOutput) ? explode(',', trim($memOutput)) : [];
    if (count($parts) === 6 && count(array_filter($parts, 'is_numeric')) === 6 && min(array_map('floatval', $parts)) >= 0 && (float) $parts[0] > 0) {
        [$total, $used, $available, $swapTotal, $swapUsed, $swapFree] = array_map('floatval', $parts);
        $memory = [
            'available_data' => true,
            'total' => formatBytes($total * 1024 * 1024),
            'used' => formatBytes($used * 1024 * 1024),
            'available' => formatBytes($available * 1024 * 1024),
            'usage' => min(100, round($used / $total * 100, 1)) . '%',
            'swap_total' => formatBytes($swapTotal * 1024 * 1024),
            'swap_used' => formatBytes($swapUsed * 1024 * 1024),
            'swap_free' => formatBytes($swapFree * 1024 * 1024),
            'swap_usage' => ($swapTotal > 0 ? min(100, round($swapUsed / $swapTotal * 100, 1)) : 0) . '%',
        ];
    }
    return ['cpu' => $cpu, 'memory' => $memory];
}

function formatBytes($bytes) {
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
    $bytes = max((float) $bytes, 0);
    $power = min($bytes > 0 ? (int) floor(log($bytes, 1024)) : 0, count($units) - 1);
    return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
}

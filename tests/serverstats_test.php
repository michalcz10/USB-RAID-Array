<?php
require_once __DIR__ . '/../Web/content/ftp/lib/serverstats.php';
require_once __DIR__ . '/../Web/includes/workspace.php';

// Treat warnings and deprecations as failures, including when rendering missing data.
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$disk = parseDiskData("Filesystem 1024-blocks Used Available Capacity Mounted on\n/dev/mapper/vg-root 1048576 262144 786432 25% /\n");
check($disk['available_data'] && $disk['size'] === '1 GiB' && $disk['usage'] === '25%', 'Parse POSIX df sizes and usage');
foreach ([false, null, '', 'df: /dev/sdb1: No such file or directory', "Filesystem 1024-blocks Used Available Capacity Mounted on\n"] as $output) {
    check(!parseDiskData($output)['available_data'], 'Missing disk output must be unavailable');
}
$healthy = "Personalities : [raid1] [raid5]\nmd0 : active raid5 sda[0] sdb[1] sdc[2]\n      1000 blocks [3/3] [UUU]\n\nunused devices: <none>\n";
check(parseRaidStatus($healthy)['status'] === 'healthy', 'Three-disk RAID5 should be healthy');
check(parseRaidStatus(str_replace('[UUU]', '[U_U]', $healthy))['status'] === 'degraded', 'Missing RAID member should be degraded');
check(parseRaidStatus($healthy . "md1 : active raid1 sdd[0] sde[1]\n      1000 blocks [2/1] [U_]\n")['status'] === 'degraded', 'One failed array must make overall status degraded');
check(parseRaidStatus(str_replace('[UUU]', "[UUU]\n      resync = 42.0%", $healthy))['status'] === 'syncing', 'RAID resync is visible');
check(parseRaidStatus("md0 : active raid0 sda[0] sdb[1]\n      1000 blocks super 1.2 512k chunks\n")['status'] === 'active', 'RAID0 does not require a redundancy bitmap');
check(parseRaidStatus("md0 : inactive sda[0](S)\n      1000 blocks\n")['status'] === 'inactive', 'Inactive array remains inactive');
check(parseRaidStatus("Personalities : [raid1]\nunused devices: <none>\n")['status'] === 'not configured', 'No configured Linux MD array');
check(parseRaidStatus(false)['status'] === 'unavailable', 'Unreadable mdstat is unavailable');
$resources = parseSystemStats('97,5', '1024,256,768,0,0,0');
check($resources['cpu']['usage'] === '2.5' && $resources['memory']['usage'] === '25%' && $resources['memory']['swap_usage'] === '0%', 'Resource values and zero swap');
check(!parseSystemStats(false, false)['cpu']['available_data'], 'Missing CPU output must not show 100%');
check(!parseSystemStats('not a number', '')['memory']['available_data'], 'Missing memory output must be unavailable');

// Check remote command construction and path quoting with a harmless stub.
$ssh = new class {
    public $commands = [];
    function exec($command) { $this->commands[] = $command; return false; }
};
$defaultPath = "/storage/owner's files";
$systemData = getSystemData($ssh, $defaultPath);
check(in_array("LC_ALL=C df -Pk -- '/' 2>/dev/null", $ssh->commands, true), 'System filesystem uses root instead of a device name');
check(in_array("LC_ALL=C df -Pk -- '/storage/owner'\\''s files' 2>/dev/null", $ssh->commands, true), 'Storage path is quoted for remote Linux even on Windows');

// Exercise the actual page template with missing output and with valid output.
$source = file_get_contents(__DIR__ . '/../Web/content/ftp/serverstat.php');
$template = substr($source, strpos($source, '$monitors ='));
foreach ([false, true] as $available) {
    if ($available) {
        $systemData['storage'] = ['system' => $disk, 'data' => $disk];
        $systemData['resources'] = $resources;
        $systemData['raid'] = parseRaidStatus($healthy);
    }
    ob_start();
    eval($template);
    $html = ob_get_clean();
    check(strpos($html, 'Server status') !== false, 'Status page renders');
    check($available ? strpos($html, '1 GiB') !== false : strpos($html, 'Storage statistics are unavailable') !== false, 'Template handles available and missing data');
}
echo "Server statistics: parser, RAID, command quoting and page rendering checks passed.\n";

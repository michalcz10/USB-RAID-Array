<?php
require_once __DIR__ . '/../Web/includes/workspace.php';
require_once __DIR__ . '/../Web/content/ftp/lib/serverstats.php';
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
});
function expectWorkspace($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

function renderWorkspaceFixture($name, $admin = true) {
    $_SESSION = ['uname' => 'michal', 'admin' => $admin, 'upPer' => true, 'downPer' => true, 'delPer' => true];
    $defaultPath = '/mnt/raid'; $currentPath = '/mnt/raid/Workspace';
    $filePath = $currentPath . '/Example.pdf'; $fileName = basename($filePath); $parentDir = $currentPath;
    $fileExtension = 'png'; $fileSize = 245760; $mimeType = 'image/png';
    $isImage = true; $isVideo = false; $isAudio = false;
    $editable = true; $showLineNumbers = true; $content = "{\n  \"workspace\": \"USB RAID\",\n  \"status\": \"connected\"\n}\n";
    $message = ''; $messageType = ''; $message_type = ''; $shareCreated = false; $shareError = '';
    $share = ['expiration' => '2030-01-01']; $downloadAllowed = false; $token = 'test-only';
    $items = ['Documents', 'Photos', 'README.md', 'Example.pdf'];
    $imageExtensions = ['png']; $videoExtensions = []; $audioExtensions = []; $editableExtensions = ['md']; $archiveExtensions = [];
    $sftp = new class {
        function is_dir($name) { return strpos($name, '.') === false; }
        function stat($path) { return ['size' => 245760]; }
    };
    $result = new class {
        private $rows = [
            ['uname' => 'michal', 'admin' => true, 'defPath' => '/mnt/raid', 'delPer' => true, 'downPer' => true, 'upPer' => true],
            ['uname' => 'guest', 'admin' => false, 'defPath' => '/mnt/raid/shared', 'delPer' => false, 'downPer' => true, 'upPer' => false],
        ];
        function fetch_assoc() { return array_shift($this->rows); }
    };
    $systemData = [
        'storage' => array_fill_keys(['system', 'data'], parseDiskData("Filesystem 1024-blocks Used Available Capacity Mounted on\n/dev/md0 1048576 262144 786432 25% /mnt/raid\n")),
        'raid' => parseRaidStatus("md0 : active raid5 sda[0] sdb[1] sdc[2]\n      1000 blocks [3/3] [UUU]\n"),
        'resources' => parseSystemStats('93', '8192,2048,6144,1024,0,1024'), 'lastUpdate' => '2026-10-16 10:00:00',
    ];
    $top = in_array($name, ['adminpanel', 'changepassword'], true);
    $source = file_get_contents(__DIR__ . '/../Web/content/' . ($top ? '' : 'ftp/') . $name . '.php');
    if ($name === 'serverstat') {
        $start = strpos($source, '$monitors =');
        eval(substr($source, $start, strpos($source, '?>', $start) - $start));
    }
    $start = strpos($source, '<!DOCTYPE html>');
    $template = substr($source, $start, strpos($source, '</html>', $start) + 7 - $start);
    ob_start(); eval('?>' . $template); return ob_get_clean();
}

$preview = in_array('--preview', $argv, true);
$directory = __DIR__ . '/../Web/.layout-preview';
if ($preview && !is_dir($directory)) mkdir($directory);
$pages = ['index', 'serverstat', 'adminpanel', 'changepassword', 'open', 'view', 'pdf', 'shared'];
foreach ($pages as $name) {
    $html = renderWorkspaceFixture($name);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expectWorkspace($xpath->query('//body')->length === 1, "$name has one body");
    expectWorkspace($xpath->query('//main')->length === 1, "$name has one main region");
    expectWorkspace($xpath->query('//aside[contains(@class,"sidebar")]')->length === 1, "$name has shared sidebar");
    expectWorkspace($xpath->query('//*[@id="themeToggle"]')->length === 1, "$name has one theme switch");
    expectWorkspace($xpath->query('//a[@aria-current="page"]')->length === 1, "$name has one active navigation item");
    expectWorkspace($xpath->query('//main//footer')->length === 1, "$name footer is inside the main region");
    if ($name === 'shared') expectWorkspace(strpos($html, 'User management') === false && strpos($html, 'Account settings') === false, 'Public shared preview does not expose account navigation');
    if ($preview) {
        $base = $name === 'adminpanel' || $name === 'changepassword' ? '../content/' : '../content/ftp/';
        $html = str_replace('<head>', '<head><base href="' . $base . '">', $html);
        // Browser previews never request a real remote file or perform mutations.
        $image = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="380"><rect width="800" height="380" fill="#1c3053"/><circle cx="400" cy="150" r="70" fill="#2563eb"/><text x="400" y="290" text-anchor="middle" fill="white" font-size="28" font-family="sans-serif">USB RAID / Workspace preview</text></svg>');
        $html = preg_replace('/src="(?:view|shared)\.php\?[^"\n]+"/', 'src="' . $image . '"', $html);
        $html = str_replace("const url = 'pdf.php?file=%2Fmnt%2Fraid%2FWorkspace%2FExample.pdf&stream=1';", "const url = '/Web/.layout-preview/sample.pdf';", $html);
        file_put_contents($directory . '/' . $name . '.html', $html);
    }
}
$member = renderWorkspaceFixture('index', false);
expectWorkspace(strpos($member, 'User management') === false && strpos($member, '> Server status</a>') === false, 'Member navigation omits admin pages');
expectWorkspace(strpos($member, 'Account settings') !== false, 'Members can access account settings');
if ($preview) {
    // One-page PDF fixture for viewer navigation and fullscreen checks.
    $stream = "BT /F1 24 Tf 60 700 Td (USB RAID workspace preview) Tj ET";
    $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream"];
    $pdf = "%PDF-1.4\n"; $offsets = [0];
    foreach ($objects as $i => $object) { $offsets[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n$object\nendobj\n"; }
    $xref = strlen($pdf); $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    file_put_contents($directory . '/sample.pdf', $pdf);
}
echo "Workspace: all eight page templates, navigation roles and shared layout checks passed.\n";

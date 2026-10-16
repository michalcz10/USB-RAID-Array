<?php
require_once __DIR__ . '/../Web/includes/remotepaths.php';

$parents = [
    '/' => '/',
    '/etc' => '/',
    '/etc/' => '/',
    '/etc/file.txt' => '/etc',
    '//etc//file.txt' => '//etc',
    '/folder with spaces/item.txt' => '/folder with spaces',
    'folder/file.txt' => 'folder',
    'file.txt' => '.',
    '/a\\b/file.txt' => '/a\\b', // Backslash is a valid POSIX filename character.
];
foreach ($parents as $path => $expected) {
    if (remoteDirname($path) !== $expected) throw new RuntimeException('Incorrect parent for ' . $path);
}
foreach (['/' => '/', '//etc/../' => '/', '/etc/../../' => '/', '/etc/./ssh/' => '/etc/ssh'] as $path => $expected) {
    if (normalizePath($path) !== $expected) throw new RuntimeException('Incorrect normalized path for ' . $path);
}
// Round trips must remain correct on both Windows/XAMPP and Linux.
foreach (['/etc', '/mnt/raid', '/folder with spaces'] as $directory) {
    $parent = normalizePath(remoteDirname($directory));
    if ($parent !== ($directory === '/mnt/raid' ? '/mnt' : '/')) throw new RuntimeException('Invalid navigation round trip');
}
// ZIP paths keep the top-level directory name when it is directly under root.
foreach (['/etc' => '/etc/config.txt', '/mnt/raid' => '/mnt/raid/config.txt'] as $base => $file) {
    $relative = substr($file, strlen(rtrim(remoteDirname($base), '/')) + 1);
    if ($relative !== ($base === '/etc' ? 'etc/config.txt' : 'raid/config.txt')) throw new RuntimeException('Incorrect ZIP member path');
}
echo "Remote paths: POSIX parents, normalization, root navigation and ZIP paths passed.\n";

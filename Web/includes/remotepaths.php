<?php
/** SFTP paths use POSIX separators, regardless of the OS running PHP. */
function normalizePath(string $path): string {
    $parts = array_filter(explode('/', $path), fn($part) => $part !== '' && $part !== '.');
    $stack = [];
    foreach ($parts as $part) {
        if ($part === '..') {
            array_pop($stack);
        } else {
            $stack[] = $part;
        }
    }
    return '/' . implode('/', $stack);
}

/** Like dirname(), but only '/' is a separator and the root stays '/'. */
function remoteDirname(string $path): string {
    $path = rtrim($path, '/');
    if ($path === '') return '/';
    $separator = strrpos($path, '/');
    if ($separator === false) return '.';
    $parent = rtrim(substr($path, 0, $separator), '/');
    return $parent === '' ? '/' : $parent;
}

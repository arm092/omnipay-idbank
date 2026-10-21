<?php

$failed = false;
foreach (['src', 'tests'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
        if ($file->getExtension() === 'php') {
            passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $status);
            $failed = $failed || $status !== 0;
        }
    }
}
exit($failed ? 1 : 0);

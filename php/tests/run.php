<?php
/**
 * 跨平台协议测试入口。
 *
 * 运行：php tests/run.php
 */
$files = glob(__DIR__ . '/*ProtocolTest.php');
sort($files);

$failed = [];
foreach ($files as $file) {
    echo "\n== " . basename($file) . " ==\n";
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        $failed[] = basename($file);
    }
}

if ($failed) {
    fwrite(STDERR, "\nFailed: " . implode(', ', $failed) . "\n");
    exit(1);
}

echo "\nAll protocol tests passed (" . count($files) . " files).\n";

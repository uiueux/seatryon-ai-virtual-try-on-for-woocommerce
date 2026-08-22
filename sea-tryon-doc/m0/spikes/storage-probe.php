<?php
/**
 * M0-only cross-process/SAPI storage probe.
 *
 * This file contains no WordPress bootstrap or customer data and is excluded from
 * production packages. HTTP use is restricted to loopback clients.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    $remote = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if (!in_array($remote, array('127.0.0.1', '::1'), true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$operation = PHP_SAPI === 'cli'
    ? (isset($argv[1]) ? (string) $argv[1] : 'read')
    : (isset($_GET['op']) ? (string) $_GET['op'] : 'read');

$root = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'sea-tryon-m0-probe';
$file = $root . DIRECTORY_SEPARATOR . 'handoff.json';

if ($operation === 'write') {
    if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create probe directory.');
    }

    $payload = array(
        'schema' => 1,
        'nonce'  => bin2hex(random_bytes(16)),
        'writer' => PHP_SAPI,
    );
    $payload['sha256'] = hash('sha256', $payload['schema'] . '|' . $payload['nonce'] . '|' . $payload['writer']);

    if (file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        throw new RuntimeException('Unable to write probe file.');
    }

    echo json_encode(array('operation' => 'write', 'path' => $file, 'payload' => $payload), JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_file($file)) {
    http_response_code(404);
    exit(json_encode(array('operation' => $operation, 'valid' => false, 'reason' => 'missing')));
}

$payload = json_decode((string) file_get_contents($file), true);
$expected = is_array($payload) && isset($payload['schema'], $payload['nonce'], $payload['writer'])
    ? hash('sha256', $payload['schema'] . '|' . $payload['nonce'] . '|' . $payload['writer'])
    : '';
$valid = is_array($payload) && isset($payload['sha256']) && hash_equals((string) $payload['sha256'], $expected);

if ($operation === 'cleanup') {
    $removed = $valid && unlink($file);
    if ($removed) {
        @rmdir($root);
    }
    echo json_encode(array('operation' => 'cleanup', 'valid' => $valid, 'removed' => $removed));
    exit($removed ? 0 : 1);
}

echo json_encode(array('operation' => 'read', 'valid' => $valid, 'reader' => PHP_SAPI, 'payload' => $payload), JSON_UNESCAPED_SLASHES);
exit($valid ? 0 : 1);


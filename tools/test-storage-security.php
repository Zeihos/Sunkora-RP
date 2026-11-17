<?php
declare(strict_types=1);

// Exercise the real storage helper with a private temporary directory.
// This test never reads or modifies live project data.
$root = dirname(__DIR__);
$protected = [
    'Сайт/app/.htaccess','Сайт/config/.htaccess','Сайт/data/.htaccess',
    'Форум/app/.htaccess','Форум/config/.htaccess','Форум/storage/.htaccess',
    'Вики/app/.htaccess','Вики/config/.htaccess','Вики/storage/.htaccess',
];
foreach ($protected as $relative) {
    $contents = @file_get_contents($root.'/'.$relative);
    if (!is_string($contents) || !preg_match('/^Require\\s+all\\s+denied\\s*$/mi', $contents)) {
        fwrite(STDERR, "FAIL: missing Apache deny rule: $relative\n");
        exit(1);
    }
}
$uploads = @file_get_contents($root.'/Вики/assets/uploads/.htaccess');
if (!is_string($uploads) || !str_contains($uploads, '<FilesMatch') || !str_contains($uploads, 'Require all denied')) {
    fwrite(STDERR, "FAIL: missing PHP upload restriction\n");
    exit(1);
}
$dir = sys_get_temp_dir().'/sunkora-wiki-test-'.bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) throw new RuntimeException('Failed to create test storage');
try {
    putenv('SUNKORA_WIKI_STORAGE_DIR='.$dir);
    require $root.'/Вики/app/core.php';
    if (WIKI_STORE_DIR !== realpath($dir) || dirname(WIKI_DATA) !== realpath($dir)) {
        throw new RuntimeException('Wiki storage override was not applied');
    }
    wiki_change(function (&$database): void { $database = ['test' => 'private']; });
    if (wiki_read() !== ['test' => 'private']) {
        throw new RuntimeException('Wiki storage read/write failed');
    }
    $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
    if (!wiki_login_limit('failure') || !is_file($dir.'/login-limits.json')) {
        throw new RuntimeException('Wiki login limiter does not use private storage');
    }
    echo "OK: Apache deny rules, private Wiki storage read/write and login limiter\n";
} finally {
    putenv('SUNKORA_WIKI_STORAGE_DIR');
    foreach (glob($dir.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
    foreach (glob($dir.'/.*') ?: [] as $file) if (is_file($file)) unlink($file);
    rmdir($dir);
}

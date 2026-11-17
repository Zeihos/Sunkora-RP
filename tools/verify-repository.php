<?php
declare(strict_types=1);

/**
 * Check web app entrypoints and references to bundled static assets.
 * No network requests, database connections or production data access.
 */
$root = dirname(__DIR__);
$apps = [
    'Сайт' => ['index.php', 'app/core.php', 'app/actions.php', 'assets/css/style.css', 'assets/js/app.js', 'assets/img/sunkora-avatar.jpg', 'assets/img/sunkora-cover.jpg', 'config/.htaccess', 'data/.htaccess'],
    'Форум' => ['index.php', 'app/core.php', 'app/actions.php', 'assets/css/forum.css', 'assets/js/forum.js', 'assets/img/sunkora-avatar.jpg', 'assets/img/sunkora-cover.jpg', 'config/.htaccess', 'storage/.htaccess'],
    'Вики' => ['index.php', 'app/core.php', 'app/actions.php', 'assets/css/wiki.css', 'assets/js/wiki.js', 'assets/img/sunkora-avatar.jpg', 'assets/img/sunkora-cover.jpg', 'config/.htaccess', 'storage/.htaccess', 'assets/uploads/.htaccess'],
];
$missing = [];
$checked = 0;
foreach ($apps as $name => $required) {
    foreach ($required as $relative) {
        $path = $root . '/' . $name . '/' . $relative;
        $checked++;
        if (!is_file($path)) $missing[] = "$name/$relative";
    }
    $dir = $root . '/' . $name;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        if (!in_array(strtolower($file->getExtension()), ['php', 'js', 'css'], true)) continue;
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) { $missing[] = 'Unreadable: ' . $file->getPathname(); continue; }
        preg_match_all('~(?<![A-Za-z0-9_])assets/(?:css|js|img)/[A-Za-z0-9._/-]+\\.(?:css|js|png|jpe?g|webp|svg|woff2)(?![A-Za-z0-9])~i', $source, $matches);
        foreach (array_unique($matches[0]) as $asset) {
            $checked++;
            if (str_contains($asset, '..') || !is_file($dir . '/' . $asset)) {
                $missing[] = "$name: $asset referenced by " . basename($file->getPathname());
            }
        }
    }
}
if ($missing) {
    fwrite(STDERR, "Repository validation failed:\n- " . implode("\n- ", array_unique($missing)) . "\n");
    exit(1);
}
echo "OK: {$checked} entrypoint and asset path checks across 3 PHP apps\n";

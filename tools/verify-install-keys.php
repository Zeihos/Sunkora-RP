<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$targets = [
    'SUNKORA_SITE_INSTALL_KEY' => $root . '/Сайт/config/install-key.php',
    'SUNKORA_FORUM_INSTALL_KEY' => $root . '/Форум/config/install-key.php',
    'SUNKORA_WIKI_INSTALL_KEY' => $root . '/Вики/config/install-key.php',
];
foreach ($targets as $variable => $path) {
    putenv($variable);
    try {
        require $path;
        fwrite(STDERR, "FAIL: missing $variable was accepted\n");
        exit(1);
    } catch (DomainException $expected) {
        // Fail closed when an installation secret is absent.
    }
    $sample = str_repeat('T', 40);
    putenv("$variable=$sample");
    $value = require $path;
    if (!is_string($value) || !hash_equals($sample, $value)) {
        fwrite(STDERR, "FAIL: $variable was not read from environment\n");
        exit(1);
    }
    putenv($variable);
    echo "OK: $variable\n";
}

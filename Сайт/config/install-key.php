<?php
declare(strict_types=1);
// Configure on the PHP host, never in the Git repository.
$key = getenv('SUNKORA_SITE_INSTALL_KEY');
if (!is_string($key) || strlen($key) < 32 || strlen($key) > 90) {
    throw new DomainException('Ключ установки сайта не настроен. Задайте SUNKORA_SITE_INSTALL_KEY (32–90 символов).');
}
return $key;

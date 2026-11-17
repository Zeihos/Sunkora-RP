<?php
declare(strict_types=1);
/* Базовые значения сайта. Остальные настройки изменяются через админ-панель. */
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) { http_response_code(404); exit; }
return [
    'name' => 'SUNKORA ROLEPLAY',
    'short_name' => 'SUNKORA',
    'vk' => 'https://vk.ru/sunkora_rp',
    'telegram' => 'https://t.me/sunkora_rp',
];

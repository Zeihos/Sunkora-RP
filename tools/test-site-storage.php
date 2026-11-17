<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/sunkora-site-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700))throw new RuntimeException('Не удалось создать временный каталог');
try {
 putenv('SUNKORA_SITE_STORAGE_DIR='.$dir);
 require $root.'/Сайт/app/core.php';
 if(dbfile()!==realpath($dir).'/store.php')throw new RuntimeException('Некорректный путь базы сайта');
 store_change(function (&$s): void {$s['installed']=true;$s['settings']['tagline']='Тест';});
 $state=store_read();
 if(!$state['installed']||$state['settings']['tagline']!=='Тест')throw new RuntimeException('Сайт не сохранил данные');
 if(!str_starts_with(file_get_contents(dbfile()),SUNKORA_STORE_HEADER))throw new RuntimeException('Неверный формат файла базы сайта');
 putenv('SUNKORA_SITE_STORAGE_DIR='.$root.'/Сайт/data');
 try {
  dbfile();
  throw new LogicException('База сайта приняла публичный путь');
 } catch (RuntimeException $expected) {}
 putenv('SUNKORA_SITE_STORAGE_DIR='.$root.'/unavailable-test-path');
 try {
  dbfile();
  throw new LogicException('База сайта приняла отсутствующий путь');
 } catch (RuntimeException $expected) {}
 echo "OK: сайт — внешняя база, запись, чтение и проверки пути\n";
} finally {
 putenv('SUNKORA_SITE_STORAGE_DIR');
 foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);
 rmdir($dir);
}

<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/sunkora-forum-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700))throw new RuntimeException('Не удалось создать временный каталог');
try {
 putenv('SUNKORA_FORUM_STORAGE_DIR='.$dir);
 require $root.'/Форум/app/core.php';
 if(storage_path()!==realpath($dir).'/state.php')throw new RuntimeException('Некорректный путь базы форума');
 change_state(function (&$s): void {$s['installed']=true;$s['settings']['tagline']='Тест';});
 $state=read_state();
 if(!$state['installed']||$state['settings']['tagline']!=='Тест')throw new RuntimeException('Форум не сохранил данные');
 if(!str_starts_with(file_get_contents(storage_path()),STORE_PREFIX))throw new RuntimeException('Неверный формат файла базы форума');
 putenv('SUNKORA_FORUM_STORAGE_DIR='.$root.'/Форум/storage');
 try {
  storage_path();
  throw new LogicException('База форума приняла публичный путь');
 } catch (RuntimeException $expected) {}
 $paths=['/forum/index.php','/forum/'];
 $cases=[
  ['https://forum.example.org/forum/index.php?p=login','forum.example.org','/forum/index.php?p=login'],
  ['http://forum.example.org/forum/?p=topic','forum.example.org','/forum/?p=topic'],
  ['https://evil.example/forum/?p=login','forum.example.org',null],
  ['https://forum.example.org.evil.example/forum/?p=login','forum.example.org',null],
  ['https://forum.example.org@evil.example/forum/?p=login','forum.example.org',null],
  ['javascript:alert(1)','forum.example.org',null],
  ['https://forum.example.org/other.php','forum.example.org',null],
 ];
 foreach($cases as [$ref,$host,$expected]){
  if(local_return_url($ref,$host,$paths)!==$expected)throw new RuntimeException('Ошибка безопасного редиректа: '.$ref);
 }
 echo "OK: форум — внешняя база, запись, чтение и безопасный возврат\n";
} finally {
 putenv('SUNKORA_FORUM_STORAGE_DIR');
 foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);
 rmdir($dir);
}

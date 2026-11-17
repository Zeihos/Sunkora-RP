<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/sunkora-site-reliability-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700))throw new RuntimeException('Не удалось создать временный каталог');
try {
 putenv('SUNKORA_SITE_STORAGE_DIR='.$dir);
 require $root.'/Сайт/app/core.php';

 $cases=[
  [null,false],['',false],['0',false],['60000',false],['060000',false],
  ['60001',true],['999999999999999999999999999',true],
  ['-1',true],['0.5',true],['garbage',true],["60000\n",true],[[42],true]
 ];
 foreach($cases as [$value,$expected]){
  if(request_body_too_large($value,60000)!==$expected){
   throw new RuntimeException('Ошибка обработки Content-Length: '.var_export($value,true));
  }
 }

 // Production data format and all existing account fields remain unchanged.
 $id='account-test';
 store_change(function (&$db) use ($id) {
  $db['installed']=true;
  $db['users'][]=['id'=>$id,'username'=>'Demo_User','email'=>'demo@example.test','password'=>'test-hash','role'=>'player','created'=>'2026-11-18','blocked'=>false];
 });
 $original=@file_get_contents(dbfile());
 if(!is_string($original)||!str_starts_with($original,SUNKORA_STORE_HEADER))throw new RuntimeException('Неверный формат базы');
 if(!is_file($dir.'/.store.lock'))throw new RuntimeException('Отдельный файл блокировки не создан');

 try {
  store_change(function (&$db) { $db['users']=[]; throw new DomainException('Отмена изменений'); });
  throw new LogicException('Исключение обработчика не было передано');
 } catch(DomainException $expected) {}
 if(file_get_contents(dbfile())!==$original)throw new RuntimeException('Данные изменились после ошибки');

 store_change(function (&$db) { $db['settings']['tagline']='Новая запись'; });
 $result=store_read();
 if(count($result['users'])!==1||$result['users'][0]['id']!==$id||$result['settings']['tagline']!=='Новая запись')throw new RuntimeException('Данные не сохранились');
 if(glob($dir.'/.sunkora-*'))throw new RuntimeException('Остался временный файл после атомарной записи');

 echo 'OK: Content-Length tests '.count($cases).', atomic store updates and rollback on failure'."\n";
} finally {
 putenv('SUNKORA_SITE_STORAGE_DIR');
 foreach(glob($dir.'/*')?:[] as $path)if(is_file($path))unlink($path);
 foreach(glob($dir.'/.*')?:[] as $path)if(is_file($path))unlink($path);
 rmdir($dir);
}

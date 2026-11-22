<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/sunkora-wiki-auth-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700)) throw new RuntimeException('Не удалось создать каталог тестов');
$n=0;
$check=static function(bool $condition,string $label)use(&$n):void{
 $n++;if(!$condition)throw new RuntimeException('FAIL: '.$label);
};
$denied=static function(callable $fn,string $label)use(&$n):void{
 $n++;try{$fn();}catch(DomainException $expected){return;}
 throw new RuntimeException('FAIL: no denial: '.$label);
};
try {
 putenv('SUNKORA_WIKI_STORAGE_DIR='.$dir);
 require $root.'/Вики/app/core.php';
 require $root.'/Вики/app/seeds.php';

 $oldHash=password_hash('OldPassword-12345',PASSWORD_DEFAULT);
 wiki_change(function (&$data)use($oldHash):void {
  $data=wiki_seed_data();
  $data['admin']=['name'=>'Админ','email'=>'admin@example.test','password_hash'=>$oldHash];
 });

 $_SESSION=['wiki_admin'=>true,'wiki_auth_version'=>hash('sha256',$oldHash)];
 wiki_admin_change(function (&$data):void{$data['settings']['site_title']='Проверенный админ';});
 $check(wiki_read()['settings']['site_title']==='Проверенный админ','valid session update');

 $newHash=password_hash('NewPassword-67890',PASSWORD_DEFAULT);
 // Another request changes the password after this session was opened.
 wiki_change(function (&$data)use($newHash):void{$data['admin']['password_hash']=$newHash;});
 $denied(fn()=>wiki_admin_change(function (&$data):void{$data['settings']['site_title']='Утечка';}),'stale editing session');
 $check(wiki_read()['settings']['site_title']==='Проверенный админ','rejected write did not change content');
 $denied(fn()=>wiki_assert_admin_state(wiki_read()),'stale backup session');

 $_SESSION['wiki_admin']=false;
 $denied(fn()=>wiki_admin_change(function (&$data):void{$data['settings']['site_title']='Утечка';}),'unauthenticated edit');
 $_SESSION['wiki_admin']=true;
 $_SESSION['wiki_auth_version']=hash('sha256',$newHash);
 $check(wiki_admin_change(function (&$data):bool {$data['settings']['site_title']='Обновлённый админ';return true;}),'new session can edit');
 $check(wiki_read()['settings']['site_title']==='Обновлённый админ','updated data saved');

 $denied(function():void{
  wiki_admin_change(function (&$data):void{
   if(!password_verify('OldPassword-12345',(string)$data['admin']['password_hash']))
    throw new DomainException('Неверный пароль');
   $data['admin']['password_hash']=password_hash('Overwrite',PASSWORD_DEFAULT);
  });
 },'stale password form');
 $check(password_verify('NewPassword-67890',wiki_read()['admin']['password_hash']),'new password retained');

 // Source-level checks confirm every admin mutation uses the guarded path.
 $source=file_get_contents($root.'/Вики/app/actions.php');
 $p=strrpos($source,'        wiki_require_admin();');
 $check(is_int($p),'wiki admin action section');
 $adminSection=is_int($p)?substr($source,$p):'';
 $check(substr_count($adminSection,'wiki_admin_change(function')===8,'all eight admin mutations guarded');
 $check(!str_contains($adminSection,'wiki_change(function'),'no unprotected admin mutations');
 $check(str_contains($source,'$currentDb=wiki_read();'),'login refreshes credentials');
 $check(str_contains($source,'wiki_assert_admin_state($snapshot);'),'backup rechecks session');
 $check(str_contains($adminSection,'$filename=wiki_admin_change(function'),'delete media reads latest articles');
 echo "OK: $n Wiki security assertions\n";
} finally {
 putenv('SUNKORA_WIKI_STORAGE_DIR');
 unset($_SESSION);
 foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);
 foreach(glob($dir.'/.*')?:[] as $file)if(is_file($file))unlink($file);
 rmdir($dir);
}

<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/sunkora-account-test-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700))throw new RuntimeException('Не удалось создать каталог проверки');
$assertions=0;
$check=static function(bool $ok,string $message)use(&$assertions):void {
 $assertions++;
 if(!$ok)throw new RuntimeException('FAIL: '.$message);
};
try {
 putenv('SUNKORA_SITE_STORAGE_DIR='.$dir);
 require $root.'/Сайт/app/core.php';

 $check(site_identifier_key('СУНКОРА')==='сункора','Russian uppercase');
 $check(site_identifier_key('СункорА')==='сункора','Russian mixed case');
 $check(site_identifier_key('ЁЖИК')==='ёжик','Russian Yo');
 $check(site_identifier_key('ІЇЄҐЎ')==='іїєґў','Cyrillic regional capitals');
 $check(site_identifier_key('ALICE_99')==='alice_99','Latin uppercase');

 $hash=password_hash('long-password-123',PASSWORD_DEFAULT);
 $users=[
  ['id'=>'a','username'=>'СункорА','email'=>'Team@Example.test','password'=>$hash,'role'=>'player','blocked'=>false],
  ['id'=>'b','username'=>'Alpha_1','email'=>'other@example.test','password'=>$hash,'role'=>'player','blocked'=>false],
 ];
 $check(site_user_exists($users,'сУНКОРа','new@example.test'),'duplicate Cyrillic nickname');
 $check(site_user_exists($users,'Unique_1','TEAM@example.TEST'),'duplicate email');
 $check(site_user_exists($users,'ALPHA_1','not-used@example.test'),'duplicate Latin nickname');
 $check(!site_user_exists($users,'Unique_1','not-used@example.test'),'unique nickname and email');
 $check(site_find_user($users,'СУНКОРА')['id']==='a','lookup Cyrillic nickname');
 $check(site_find_user($users,'team@EXAMPLE.test')['id']==='a','lookup email');
 $check(site_find_user($users,'alpha_1')['id']==='b','lookup Latin nickname');
 $check(site_find_user($users,'unknown')===null,'missing account');
 $check(password_verify('long-password-123',site_find_user($users,'сункорА')['password']),'Cyrillic lookup works with stored password');

 $state=defaults();
 try {
  site_require_open_registration($state);
  throw new LogicException('Uninstalled site accepted registration');
 } catch(DomainException $ok) { $assertions++; }
 $state['installed']=true;
 site_require_open_registration($state);
 $assertions++;
 $state['settings']['registration_enabled']=false;
 try {
  site_require_open_registration($state);
  throw new LogicException('Disabled registration accepted');
 } catch(DomainException $ok) { $assertions++; }

 // Simulates a settings change between initial form validation and the
 // locked write: the final state must win, not the outdated initial check.
 store_change(function (&$state)use($users):void {
  $state['installed']=true;
  $state['users']=$users;
  $state['settings']['registration_enabled']=false;
 });
 try {
  store_change(function (&$state) {
   site_require_open_registration($state);
   $state['users'][]=['id'=>'bad','username'=>'dup','email'=>'dup@example.test'];
  });
  throw new LogicException('Disabled registration bypassed the write lock');
 } catch(DomainException $ok) { $assertions++; }
 $check(count(store_read()['users'])===2,'failed registration changed database');

 store_change(function (&$state):void {
  $state['settings']['registration_enabled']=true;
  site_require_open_registration($state);
  if(site_user_exists($state['users'],'сУнКоРа','another@example.test')) {
   $state['settings']['tagline']='duplicate prevented';
  }
 });
 $check(store_read()['settings']['tagline']==='duplicate prevented','duplicate checked under lock');
 echo "OK: $assertions account and registration assertions\n";
} finally {
 putenv('SUNKORA_SITE_STORAGE_DIR');
 foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);
 foreach(glob($dir.'/.*')?:[] as $file)if(is_file($file))unlink($file);
 rmdir($dir);
}

<?php
declare(strict_types=1);
/**
 * Verify that every mutation re-checks the actor against the locked state,
 * and check privilege changes using a disposable account database.
 */
$root=dirname(__DIR__);
$folder=sys_get_temp_dir().'/sunkora-forum-auth-'.bin2hex(random_bytes(8));
if(!mkdir($folder,0700))throw new RuntimeException('Cannot create temporary folder');
$count=0;
$check=static function(bool $ok,string $label)use(&$count):void{
 $count++;if(!$ok)throw new RuntimeException('FAIL: '.$label);
};
$reject=static function(callable $callback,string $label)use(&$count):void{
 $count++;
 try{$callback();}catch(DomainException $expected){return;}
 throw new RuntimeException('FAIL (unexpected permission): '.$label);
};
try{
 putenv('SUNKORA_FORUM_STORAGE_DIR='.$folder);
 require $root.'/Форум/app/core.php';
 $users=[
  'root'=>['id'=>'root','role'=>'admin','banned'=>false,'password'=>password_hash('Admin-password-123',PASSWORD_DEFAULT)],
  'moderator'=>['id'=>'moderator','role'=>'moderator','banned'=>false],
  'member'=>['id'=>'member','role'=>'user','banned'=>false,'password'=>password_hash('Old-user-password',PASSWORD_DEFAULT)]
 ];
 change_state(function(&$state)use($users):void{
  $state['installed']=true;$state['users']=$users;
 });
 $check(forum_actor_for_action(read_state(),'root','admin')['id']==='root','current admin');
 $check(forum_actor_for_action(read_state(),'moderator','staff')['id']==='moderator','current moderator');
 $reject(fn()=>forum_actor_for_action(read_state(),'member','staff'),'ordinary member cannot moderate');
 $reject(fn()=>forum_actor_for_action(read_state(),'member','admin'),'ordinary member cannot administer');

 // Simulate a form opened while a member was a moderator; under the write
 // lock the stored role has already been downgraded.
 $stale=read_state()['users']['moderator'];
 change_state(function(&$s):void{$s['users']['moderator']['role']='user';});
 $reject(function()use($stale):void{
  change_state(function(&$s)use($stale):void{
   $actor=forum_actor_for_action($s,$stale['id'],'staff');
   $s['settings']['announcement']='Unauthorized '.$actor['role'];
  });
 },'demoted moderator');
 $check(read_state()['settings']['announcement']===seed_state()['settings']['announcement'],'failed moderation unchanged');

 // Simulate an admin demotion between opening the form and applying changes.
 $adminForm=read_state()['users']['root'];
 change_state(function(&$s):void{$s['users']['root']['role']='user';});
 $reject(function()use($adminForm):void{
  change_state(function(&$s)use($adminForm):void{
   forum_actor_for_action($s,$adminForm['id'],'admin');
   $s['settings']['name']='Unauthorized rename';
  });
 },'demoted admin');
 $check(read_state()['settings']['name']==='SUNKORA ROLEPLAY','failed admin operation unchanged');

 change_state(function(&$s):void{$s['users']['member']['banned']=true;});
 $reject(fn()=>forum_actor_for_action(read_state(),'member'),'banned member');
 change_state(function(&$s):void{$s['users']['member']['banned']=false;});
 $check(forum_actor_for_action(read_state(),'member')['id']==='member','unbanned member');
 change_state(function(&$s):void{$s['settings']['maintenance']=true;});
 $reject(fn()=>forum_actor_for_action(read_state(),'member'),'member during maintenance');
 $reject(fn()=>forum_actor_for_action(read_state(),'moderator','staff'),'moderator during maintenance');
 change_state(function(&$s):void{$s['users']['root']['role']='admin';});
 $check(forum_actor_for_action(read_state(),'root','admin')['id']==='root','admin during maintenance');

 // A stale password must not overwrite a newer password within a write lock.
 change_state(function(&$s):void{
  $s['settings']['maintenance']=false;
  $s['users']['member']['password']=password_hash('New-user-password',PASSWORD_DEFAULT);
 });
 $oldPassword='Old-user-password';
 $reject(function()use($oldPassword):void{
  change_state(function(&$s)use($oldPassword):void{
   $actor=forum_actor_for_action($s,'member');
   if(!password_verify($oldPassword,$actor['password']))throw new DomainException('Password changed');
   $s['users']['member']['password']=password_hash('Unauthorized-password',PASSWORD_DEFAULT);
  });
 },'stale password');
 $check(password_verify('New-user-password',read_state()['users']['member']['password']),'new password remains unchanged');
 $check(!password_verify('Unauthorized-password',read_state()['users']['member']['password']),'stale update did not apply');

 // Audit that handler scopes stay wired to the actual dispatch paths.
 $source=file_get_contents($root.'/Форум/app/actions.php');
 if(!is_string($source))throw new RuntimeException('Missing forum actions');
 $scopes=[
  'post_edit'=>"forum_actor_for_action(",
  'post_delete'=>"forum_actor_for_action(",
  'topic_watch'=>"forum_actor_for_action(",
  'report'=>"forum_actor_for_action(",
  'report_resolve'=>"forum_actor_for_action(",
  'notify_read'=>"forum_actor_for_action(",
  'profile_save'=>"forum_actor_for_action(",
  'password_change'=>"forum_actor_for_action(",
  'admin_settings'=>"forum_actor_for_action(",
  'admin_category'=>"forum_actor_for_action(",
  'admin_board'=>"forum_actor_for_action(",
  'admin_remove'=>"forum_actor_for_action(",
  'admin_user'=>"forum_actor_for_action("
 ];
 foreach($scopes as $name=>$needle){
  $from=strpos($source,"case '".$name."':");
  $to=$from===false?false:strpos($source,"\n case '",$from+1);
  $part=$from===false?'':substr($source,$from,$to===false?null:$to-$from);
  $check(str_contains($part,$needle),'handler permission guard: '.$name);
 }
 echo "OK: $count forum permission checks\n";
}finally{
 putenv('SUNKORA_FORUM_STORAGE_DIR');
 foreach(glob($folder.'/*')?:[] as $path)if(is_file($path))unlink($path);
 foreach(glob($folder.'/.*')?:[] as $path)if(is_file($path))unlink($path);
 rmdir($folder);
}

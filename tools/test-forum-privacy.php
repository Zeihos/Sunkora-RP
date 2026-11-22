<?php
declare(strict_types=1);
// Access-control tests use only a disposable storage directory.
$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/sunkora-forum-privacy-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700))throw new RuntimeException('Не удалось создать тестовый каталог');
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{
 $n++;if(!$ok)throw new RuntimeException('FAIL: '.$label);
};
$denied=static function(callable $callback,string $label)use(&$n):void{
 $n++;try{$callback();}catch(DomainException $ex){return;}
 throw new RuntimeException('Unexpectedly allowed: '.$label);
};
try{
 putenv('SUNKORA_FORUM_STORAGE_DIR='.$dir);
 require $root.'/Форум/app/core.php';
 $s=seed_state();$s['installed']=true;
 $s['users']=[
  'alice'=>['id'=>'alice','name'=>'Алиса','role'=>'user','banned'=>false],
  'bob'=>['id'=>'bob','name'=>'Боб','role'=>'user','banned'=>false],
  'mod'=>['id'=>'mod','name'=>'Модератор','role'=>'moderator','banned'=>false],
  'admin'=>['id'=>'admin','name'=>'Админ','role'=>'admin','banned'=>false]
 ];
 $s['topics']['complaint-1']=['id'=>'complaint-1','board'=>'complaints','author'=>'alice','updated'=>now(),'pinned'=>false];
 $private=$s['boards']['complaints'];$public=$s['boards']['help'];
 $staffBoard=$private;$staffBoard['mode']='staff';
 $check(can_read_topic($s['topics']['complaint-1'],$private,$s['users']['alice']),'author can read own complaint');
 $check(can_read_topic($s['topics']['complaint-1'],$private,$s['users']['mod']),'moderator can read complaint');
 $check(!can_read_topic($s['topics']['complaint-1'],$private,$s['users']['bob']),'other user cannot read complaint');
 $check(!can_read_topic($s['topics']['complaint-1'],$private,null),'anonymous cannot read complaint');
 $check(count(all_visible_topics($s,$s['users']['bob']))===0,'private complaint not in public list');
 $denied(fn()=>forum_assert_move_allowed($private,$public),'private to public move');
 $denied(fn()=>forum_assert_move_allowed($staffBoard,$public),'staff to public move');
 $denied(fn()=>forum_assert_move_allowed($private,$staffBoard),'private to staff move');
 forum_assert_move_allowed($private,$private);$n++;
 forum_assert_move_allowed($public,$s['boards']['server']);$n++;
 $denied(fn()=>forum_assert_board_mode_allowed($s,'complaints','public'),'changing populated private board to public');
 $denied(fn()=>forum_assert_board_mode_allowed($s,'complaints','staff'),'changing populated private board to staff');
 forum_assert_board_mode_allowed($s,'complaints','private');$n++;
 forum_assert_board_mode_allowed($s,'suggestions','private');$n++;

 $check(forum_assert_board_permission($s,'alice','complaints')['id']==='alice','author can create complaint');
 $denied(fn()=>forum_assert_board_permission($s,'bob','news'),'regular user cannot create official news');
 $denied(fn()=>forum_assert_board_permission($s,'deleted','help'),'deleted account cannot post');
 $s['users']['alice']['banned']=true;
 $denied(fn()=>forum_assert_board_permission($s,'alice','complaints'),'newly banned user cannot post');
 $s['users']['alice']['banned']=false;
 $s['boards']['complaints']['mode']='staff';
 $denied(fn()=>forum_assert_board_permission($s,'alice','complaints'),'changed access to staff-only');
 $s['boards']['complaints']['mode']='private';
 $s['settings']['registration']=false;
 $denied(fn()=>forum_require_registration_open($s),'new registration disabled');
 $s['settings']['registration']=true;
 forum_require_registration_open($s);$n++;
 $s['settings']['maintenance']=true;
 $denied(fn()=>forum_require_registration_open($s),'register during maintenance');
 $denied(fn()=>forum_assert_board_permission($s,'alice','complaints'),'create during maintenance');

 change_state(function(&$state)use($s):void{$state=$s;});
 $denied(function(){change_state(function(&$state):void{forum_require_registration_open($state);$state['users']['unexpected']=['id'=>'unexpected'];});},'disabled registration under storage lock');
 $check(!isset(read_state()['users']['unexpected']),'failed action left database unchanged');
 echo "OK: $n forum privacy and permission assertions\n";
}finally{
 putenv('SUNKORA_FORUM_STORAGE_DIR');
 foreach(glob($dir.'/*')?:[] as $path)if(is_file($path))unlink($path);
 foreach(glob($dir.'/.*')?:[] as $path)if(is_file($path))unlink($path);
 rmdir($dir);
}

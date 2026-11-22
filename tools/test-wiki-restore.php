<?php
declare(strict_types=1);

// All data is confined to a disposable directory; no hosting data are touched.
$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/sunkora-wiki-restore-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700)) throw new RuntimeException('Не удалось создать тестовый каталог');
$count=0;
$assert=static function(bool $condition,string $label)use(&$count):void {
    $count++;if(!$condition)throw new RuntimeException('FAIL: '.$label);
};
$reject=static function(callable $fn,string $label)use(&$count):void {
    $count++;
    try{$fn();}catch(DomainException $expected){return;}
    throw new RuntimeException('FAIL: accepted '.$label);
};
try {
    putenv('SUNKORA_WIKI_STORAGE_DIR='.$dir);
    require $root.'/Вики/app/core.php';
    require $root.'/Вики/app/seeds.php';

    $backup=wiki_seed_data();
    $oldHash=password_hash('OldPassword-For-Backup-123',PASSWORD_DEFAULT);
    $backup['admin']=['name'=>'Бывший владелец','email'=>'past@example.test','password_hash'=>$oldHash];
    $backup['settings']['site_title']='Заголовок из архива';
    $backup['articles'][0]['content']='Текст статьи из архивной версии.';
    $json=json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $assert(is_string($json),'encode fixture');
    $parsed=wiki_validate_restore_json($json);
    $assert($parsed['settings']['site_title']==='Заголовок из архива','backup JSON accepted');
    $assert(count($parsed['categories'])===8,'seed categories validated');
    $assert(count($parsed['articles'])>=10,'seed articles validated');

    $broken=$backup;$broken['version']=999;
    $reject(fn()=>wiki_validate_restore_json(json_encode($broken)),'unknown backup version');
    $reject(fn()=>wiki_validate_restore_json('{corrupt json}'),'corrupt JSON syntax');
    $broken=$backup;unset($broken['articles']);
    $reject(fn()=>wiki_validate_restore_json(json_encode($broken)),'missing articles');
    $broken=$backup;$broken['articles'][0]['category_id']=987654;
    $reject(fn()=>wiki_validate_restore_json(json_encode($broken)),'orphaned article category');
    $broken=$backup;$broken['articles'][1]['id']=$broken['articles'][0]['id'];
    $reject(fn()=>wiki_validate_restore_json(json_encode($broken)),'duplicate article ID');
    $broken=$backup;$broken['categories'][1]['slug']=$broken['categories'][0]['slug'];
    $reject(fn()=>wiki_validate_restore_json(json_encode($broken)),'duplicate category slug');
    $broken=$backup;$broken['settings']['site_url']='javascript:alert(1)';
    $reject(fn()=>wiki_validate_restore_json(json_encode($broken)),'unsafe link');
    $broken=$backup;$broken['media'][]=[
        'id'=>1,'filename'=>'../config/install-key.php','title'=>'Bad','created_at'=>wiki_now()
    ];
    $reject(fn()=>wiki_validate_restore_json(json_encode($broken)),'unsafe media filename');

    $password='CurrentPassword-For-Testing-456';
    $currentHash=password_hash($password,PASSWORD_DEFAULT);
    $current=wiki_seed_data();
    $current['admin']=['name'=>'Действующий владелец','email'=>'current@example.test','password_hash'=>$currentHash];
    $current['settings']['site_title']='Текущие данные';
    $current['articles'][0]['content']='Текущий текст статьи';
    wiki_change(function(&$db)use($current):void{$db=$current;});
    $_SESSION=['wiki_admin'=>true,'wiki_auth_version'=>hash('sha256',$currentHash)];
    $before=file_get_contents(WIKI_DATA);
    $assert(is_string($before),'current database exists');
    $reject(fn()=>wiki_restore_from_json($json,'WrongPassword'),'incorrect current password');
    $assert(file_get_contents(WIKI_DATA)===$before,'invalid password left database intact');
    $assert(count(glob($dir.'/.wiki-before-restore-*.json')?:[])===0,'no snapshot before valid password');

    $snapshotName=wiki_restore_from_json($json,$password);
    $assert((bool)preg_match('/^\.wiki-before-restore-[0-9]{8}-[0-9]{6}-[a-f0-9]{12}\.json$/',$snapshotName),'safe snapshot name');
    $snapshotPath=$dir.'/'.$snapshotName;
    $assert(is_file($snapshotPath),'rollback backup created');
    $snapshot=json_decode(file_get_contents($snapshotPath),true,64,JSON_THROW_ON_ERROR);
    $assert($snapshot['settings']['site_title']==='Текущие данные','rollback snapshot contains old data');
    $assert(password_verify($password,$snapshot['admin']['password_hash']),'rollback snapshot contains current admin');
    $saved=wiki_read();
    $assert($saved['settings']['site_title']==='Заголовок из архива','restored title');
    $assert($saved['articles'][0]['content']==='Текст статьи из архивной версии.','restored article');
    $assert($saved['admin']===$current['admin'],'admin account and hash preserved');
    $assert($saved['installed_at']===$current['installed_at'],'installation date preserved');

    $invalid=$backup;$invalid['articles'][0]['category_id']=11111;
    $stored=file_get_contents(WIKI_DATA);
    $reject(fn()=>wiki_restore_from_json(json_encode($invalid),$password),'invalid restore data rejected');
    $assert(file_get_contents(WIKI_DATA)===$stored,'invalid restore left Wiki unchanged');

    // A password change in a parallel request must revoke a stale restore form.
    $rotatedHash=password_hash('RotatedPassword-For-Testing-789',PASSWORD_DEFAULT);
    wiki_change(function(&$db)use($rotatedHash):void{$db['admin']['password_hash']=$rotatedHash;});
    $reject(fn()=>wiki_restore_from_json($json,$password),'stale admin session cannot restore');
    $_SESSION['wiki_auth_version']=hash('sha256',$rotatedHash);
    $reject(fn()=>wiki_restore_from_json($json,$password),'current session with old password cannot restore');
    $assert(wiki_read()['settings']['site_title']==='Заголовок из архива','denied restore left articles unchanged');

    // Restore from the safety snapshot with the new current admin credentials.
    $recovered=wiki_restore_from_json(file_get_contents($snapshotPath),'RotatedPassword-For-Testing-789');
    $assert(is_file($dir.'/'.$recovered),'second safety snapshot exists');
    $assert(wiki_read()['settings']['site_title']==='Текущие данные','rollback restored previous content');
    $assert(password_verify('RotatedPassword-For-Testing-789',wiki_read()['admin']['password_hash']),'rollback did not overwrite current admin hash');

    $ui=file_get_contents($root.'/Вики/index.php');
    $actions=file_get_contents($root.'/Вики/app/actions.php');
    $assert(str_contains($ui,'name="restore_confirmation"'),'UI asks for confirmation');
    $assert(str_contains($ui,'name="current_password"'),'UI asks for current password');
    $assert(str_contains($actions,"if ($action === 'restore_backup')"),'web restore action connected');
    $assert(str_contains($actions,'is_uploaded_file($tmp)'),'backup upload verification');
    echo "OK: $count Wiki restore, rollback and validation assertions\n";
} finally {
    putenv('SUNKORA_WIKI_STORAGE_DIR');
    unset($_SESSION);
    foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);
    foreach(glob($dir.'/.*')?:[] as $file)if(is_file($file))unlink($file);
    rmdir($dir);
}

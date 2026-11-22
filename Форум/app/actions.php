<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';
function dispatch_action(array $initial): never {
 require_csrf();
 $action=(string)($_POST['action']??'');
 $user=current_user($initial);
 if(!$initial['installed'] && $action!=='setup') throw new DomainException('Сначала завершите установку форума.');
 if($initial['installed'] && $action==='setup') throw new DomainException('Форум уже установлен.');
 if($initial['installed'] && !empty($initial['settings']['maintenance']) && !administrator($user) && !in_array($action,['logout','login'],true)) throw new DomainException('Форум на техническом обслуживании.');
 switch($action){
 case 'setup':
  $key=textfield('install_key',150);$name=textfield('name',24);$password=(string)($_POST['password']??'');
  if(!valid_name($name)||strlen($password)<10||strlen($password)>128)throw new DomainException('Имя: 3–24 символа. Пароль: 10–128 символов.');
  $configured=require BASE_DIR.'/config/install-key.php';
  if(!hash_equals($configured,$key))throw new DomainException('Неверный ключ установки.');
  $newId=change_state(function(&$s)use($name,$password){if($s['installed'])throw new DomainException('Форум уже настроен.');
   $id=uid('u-');$s['users'][$id]=['id'=>$id,'name'=>$name,'norm'=>lower($name),'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'admin','joined'=>now(),'bio'=>'Владелец проекта','banned'=>false];$s['installed']=true;
   foreach([
     ['info','Добро пожаловать на форум SUNKORA ROLEPLAY','Приветствуем тебя на форуме SUNKORA ROLEPLAY! Здесь публикуются новости и информация о проекте, работают разделы для общения, вопросов, предложений и обращений.\n\nЧтобы участвовать в обсуждениях, создай аккаунт. Следи за новостями проекта и проверяй информацию только в официальных сообществах: https://vk.ru/sunkora_rp и https://t.me/sunkora_rp.\n\nНастройки игровых серверов и ссылки на клиент появятся после объявления администрации.'],
     ['rules','Основные правила форума','1. Уважай других участников. Оскорбления, угрозы и травля запрещены.\n2. Не публикуй спам, рекламу и вредоносные ссылки.\n3. Не выкладывай личные данные, пароли и приватную переписку без согласия.\n4. Создавай темы в подходящих разделах и используй понятные заголовки.\n5. Жалобы и апелляции публикуй в приватном разделе обращений.\n6. Решения администрации можно обжаловать корректно, без оскорблений.\n\nЭто базовые правила общения на форуме, а не полный свод игровых правил. Администрация может уточнить их перед публичным открытием.'],
   ] as [$board,$title,$body]) { $tid=uid('t-');$pid=uid('m-');$date=now();$s['topics'][$tid]=['id'=>$tid,'board'=>$board,'title'=>$title,'author'=>$id,'created'=>$date,'updated'=>$date,'last_author'=>$id,'pinned'=>true,'locked'=>true,'views'=>0];$s['posts'][$pid]=['id'=>$pid,'topic'=>$tid,'author'=>$id,'body'=>str_replace('\\n',"\n",$body),'created'=>$date,'edited'=>null,'deleted'=>false]; }
   audit($s,$s['users'][$id],'Установлен форум');return $id;
  }); session_regenerate_id(true);$_SESSION['user']=$newId;notice('success','Форум установлен. Вы вошли как владелец.');jump('admin');
 case 'register':
  forum_require_registration_open($initial);
  $name=textfield('name',24);$password=(string)($_POST['password']??'');$pass2=(string)($_POST['confirm']??'');
  if(!valid_name($name))throw new DomainException('Имя: от 3 до 24 символов; буквы, цифры, _ и -.');
  if(strlen($password)<10 || strlen($password)>128)throw new DomainException('Пароль должен содержать от 10 до 128 символов.');
  if($password!==$pass2)throw new DomainException('Пароли не совпадают.');
  $newId=change_state(function(&$s)use($name,$password){forum_require_registration_open($s);throttled($s,'register',5,3600);foreach($s['users'] as $existing){if($existing['norm']===lower($name))throw new DomainException('Этот ник уже занят.');}
   $id=uid('u-');$s['users'][$id]=['id'=>$id,'name'=>$name,'norm'=>lower($name),'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'user','joined'=>now(),'bio'=>'','banned'=>false];return $id;
  });session_regenerate_id(true);$_SESSION['user']=$newId;notice('success','Аккаунт создан! Добро пожаловать на форум.');jump('home');
 case 'login':
  $name=textfield('name',24);$password=(string)($_POST['password']??'');
  $id=change_state(function(&$s)use($name,$password){throttled($s,'login',12,900);foreach($s['users'] as $u){if($u['norm']===lower($name)&&password_verify($password,$u['password'])){
    if($u['banned']??false)return 'banned';return $u['id'];
   }}return null;});
  if($id==='banned')throw new DomainException('Аккаунт заблокирован.');if($id===null)throw new DomainException('Неверный ник или пароль.');session_regenerate_id(true);$_SESSION['user']=$id;notice('success','С возвращением!');jump('home');
 case 'logout':
  unset($_SESSION['user']);session_regenerate_id(true);notice('success','Вы вышли из аккаунта.');jump('home');
 case 'topic_create':
  require_user($user);$boardId=textfield('board',64);$title=textfield('title',140);$body=textfield('body',12000);
  $board=$initial['boards'][$boardId]??null;if(!$board||!can_write_board($board,$user))throw new DomainException('Нет доступа к публикации в этом разделе.');
  if(txtlen($title)<5||txtlen($body)<15)throw new DomainException('Заголовок — от 5 символов, сообщение — от 15 символов.');
  $id=change_state(function(&$s)use($user,$boardId,$title,$body){
   $user=forum_assert_board_permission($s,$user['id'],$boardId);
   throttled($s,'topics-'.$user['id'],8,3600);
   $id=uid('t-');$pid=uid('m-');$date=now();$s['topics'][$id]=['id'=>$id,'board'=>$boardId,'title'=>$title,'author'=>$user['id'],'created'=>$date,'updated'=>$date,'last_author'=>$user['id'],'pinned'=>false,'locked'=>false,'views'=>0];
   $s['posts'][$pid]=['id'=>$pid,'topic'=>$id,'author'=>$user['id'],'body'=>$body,'created'=>$date,'edited'=>null,'deleted'=>false];return $id;
  });notice('success','Тема опубликована.');jump('topic',['t'=>$id]);
 case 'reply_create':
  require_user($user);$topicId=textfield('topic',64);$body=textfield('body',12000);
  if(txtlen($body)<2)throw new DomainException('Сообщение слишком короткое.');
  $tid=change_state(function(&$s)use($user,$topicId,$body){
   $user=forum_actor_for_action($s,$user['id']);
   $t=$s['topics'][$topicId]??null;$b=$t?($s['boards'][$t['board']]??null):null;
   if(!$t||!$b||!can_read_topic($t,$b,$user))throw new DomainException('Тема недоступна.');
   if($t['locked']&&!staff($user))throw new DomainException('Тема закрыта для ответов.');
   throttled($s,'reply-'.$user['id'],24,300);
   $id=uid('m-');$date=now();$s['posts'][$id]=['id'=>$id,'topic'=>$topicId,'author'=>$user['id'],'body'=>$body,'created'=>$date,'edited'=>null,'deleted'=>false];
   $s['topics'][$topicId]['updated']=$date;$s['topics'][$topicId]['last_author']=$user['id'];
   if($t['author']!==$user['id'])notify($s,$t['author'],$user['name'].' ответил(а) в вашей теме «'.$t['title'].'»',route('topic',['t'=>$topicId]));
   foreach($s['users'] as $follower){if($follower['id']!==$user['id']&&$follower['id']!==$t['author']&&in_array($topicId,$follower['watches']??[],true)&&can_read_topic($t,$b,$follower))notify($s,$follower['id'],'Новый ответ в теме «'.$t['title'].'»',route('topic',['t'=>$topicId]));}
   return $topicId;
  });notice('success','Ответ опубликован.');jump('topic',['t'=>$tid,'page'=>999]);
 case 'post_edit':
  require_user($user);$pid=textfield('post',64);$body=textfield('body',12000);if(txtlen($body)<2)throw new DomainException('Сообщение слишком короткое.');
  $tid=change_state(function(&$s)use($user,$pid,$body){$user=forum_actor_for_action($s,$user['id']);$post=$s['posts'][$pid]??null;if(!$post||($post['deleted']??false)||!can_edit_post($post,$user))throw new DomainException('Нет прав на редактирование.');
   $topic=$s['topics'][$post['topic']]??null;$board=$topic?($s['boards'][$topic['board']]??null):null;
   if(!$board||!can_read_topic($topic,$board,$user))throw new DomainException('Тема недоступна.');
   $s['posts'][$pid]['body']=$body;$s['posts'][$pid]['edited']=now();audit($s,$user,'Изменил сообщение '.$pid);return $post['topic'];});notice('success','Сообщение изменено.');jump('topic',['t'=>$tid]);
 case 'post_delete':
  require_user($user);$pid=textfield('post',64);
  $tid=change_state(function(&$s)use($user,$pid){$user=forum_actor_for_action($s,$user['id']);$post=$s['posts'][$pid]??null;if(!$post||!can_edit_post($post,$user))throw new DomainException('Нет прав на удаление.');
   $topic=$s['topics'][$post['topic']]??null;$board=$topic?($s['boards'][$topic['board']]??null):null;
   if(!$topic||!$board||!can_read_topic($topic,$board,$user))throw new DomainException('Тема недоступна.');
   $all=thread_posts($s,$post['topic']);if($all && $all[0]['id']===$pid)throw new DomainException('Первое сообщение удаляется вместе с темой через управление темой.');
   $s['posts'][$pid]['deleted']=true;audit($s,$user,'Удалил сообщение '.$pid);return $post['topic'];});notice('success','Сообщение удалено.');jump('topic',['t'=>$tid]);
 case 'topic_moderate':
  require_user($user);$tid=textfield('topic',64);$mode=textfield('mode',32);
  $redir=change_state(function(&$s)use($user,$tid,$mode){$user=forum_actor_for_action($s,$user['id']);$t=$s['topics'][$tid]??null;if(!$t)throw new DomainException('Тема не найдена.');$b=$s['boards'][$t['board']]??null;
   if(!$b||!can_read_topic($t,$b,$user))throw new DomainException('Нет доступа.');
   if($mode==='delete'){
    if(!staff($user)&&($t['author']!==$user['id']||count(thread_posts($s,$tid))>1))throw new DomainException('Удалять темы с ответами могут только модераторы.');
    foreach($s['posts'] as $pid=>$p)if($p['topic']===$tid)unset($s['posts'][$pid]);unset($s['topics'][$tid]);audit($s,$user,'Удалена тема '.$tid);return ['board',$t['board']];
   }
   require_staff($user);
   if($mode==='pin')$s['topics'][$tid]['pinned']=!$t['pinned'];
   elseif($mode==='lock')$s['topics'][$tid]['locked']=!$t['locked'];
   elseif($mode==='move'){$dest=textfield('destination',64);if(!isset($s['boards'][$dest]))throw new DomainException('Выберите раздел.');forum_assert_move_allowed($b,$s['boards'][$dest]);$s['topics'][$tid]['board']=$dest;}
   else throw new DomainException('Неизвестная команда.');
   audit($s,$user,'Модерация темы '.$tid.' ('.$mode.')');return ['topic',$tid];
  });notice('success','Изменения сохранены.');jump($redir[0],[$redir[0]==='board'?'f':'t'=>$redir[1]]);
 case 'topic_watch':
  require_user($user);$tid=textfield('topic',64);
  change_state(function(&$s)use($user,$tid){$user=forum_actor_for_action($s,$user['id']);$t=$s['topics'][$tid]??null;$b=$t?($s['boards'][$t['board']]??null):null;if(!$t||!$b||!can_read_topic($t,$b,$user))throw new DomainException('Тема недоступна.');
   $current=$s['users'][$user['id']]['watches']??[];$s['users'][$user['id']]['watches']=in_array($tid,$current,true)?array_values(array_diff($current,[$tid])):array_values(array_unique([...$current,$tid]));
  });notice('success','Подписка обновлена.');jump('topic',['t'=>$tid]);
 case 'report':
  require_user($user);$postId=textfield('post',64);$reason=textfield('reason',1200);if(txtlen($reason)<10)throw new DomainException('Опишите причину (от 10 символов).');
  change_state(function(&$s)use($user,$postId,$reason){$user=forum_actor_for_action($s,$user['id']);throttled($s,'report-'.$user['id'],6,3600);$p=$s['posts'][$postId]??null;$t=$p?($s['topics'][$p['topic']]??null):null;$b=$t?($s['boards'][$t['board']]??null):null;
   if(!$t||!$b||!can_read_topic($t,$b,$user))throw new DomainException('Сообщение недоступно.');$id=uid('r-');$s['reports'][$id]=['id'=>$id,'post'=>$postId,'by'=>$user['id'],'reason'=>$reason,'created'=>now(),'status'=>'open'];
  });notice('success','Жалоба отправлена команде форума.');jump('home');
 case 'report_resolve':
  require_staff($user);$id=textfield('id',64);
  change_state(function(&$s)use($user,$id){$user=forum_actor_for_action($s,$user['id'],'staff');if(!isset($s['reports'][$id]))throw new DomainException('Жалоба не найдена.');$s['reports'][$id]['status']='closed';audit($s,$user,'Закрыта жалоба '.$id);});notice('success','Жалоба обработана.');jump('moderation');
 case 'notify_read':
  require_user($user);change_state(function(&$s)use($user){$user=forum_actor_for_action($s,$user['id']);foreach($s['notifications'] as &$n){if($n['to']===$user['id'])$n['read']=true;}unset($n);});jump('notifications');
 case 'profile_save':
  require_user($user);$bio=textfield('bio',350);
  change_state(function(&$s)use($user,$bio){$user=forum_actor_for_action($s,$user['id']);$s['users'][$user['id']]['bio']=$bio;});notice('success','Профиль обновлён.');jump('settings');
 case 'password_change':
  require_user($user);$old=(string)($_POST['old_password']??'');$new=(string)($_POST['new_password']??'');
  if(!password_verify($old,$user['password']))throw new DomainException('Неверный текущий пароль.');if(strlen($new)<10||strlen($new)>128)throw new DomainException('Новый пароль: 10–128 символов.');
  change_state(function(&$s)use($user,$new,$old){$user=forum_actor_for_action($s,$user['id']);if(!password_verify($old,$user['password']))throw new DomainException('Текущий пароль изменился. Повторите операцию.');$s['users'][$user['id']]['password']=password_hash($new,PASSWORD_DEFAULT);});session_regenerate_id(true);notice('success','Пароль изменён.');jump('settings');
 case 'backup_download':
  require_admin($user);$backup=read_state();$user=forum_actor_for_action($backup,$user['id'],'admin');$backup['attempts']=[];$backup['audit']=array_slice($backup['audit'],-100);
  header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="sunkora-forum-backup-'.gmdate('Y-m-d-His').'.json"');
  header('X-Content-Type-Options: nosniff');echo json_encode($backup,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;
 case 'admin_settings':
  require_admin($user);$settings=[];
  foreach(['name'=>55,'tagline'=>100,'welcome'=>500,'site_url'=>400,'vk'=>400,'tg'=>400,'announcement'=>400] as $k=>$limit){$settings[$k]=textfield($k,$limit);}
  foreach(['site_url','vk','tg'] as $k)if(!safeurl($settings[$k]))throw new DomainException('Некорректная ссылка: '.$k);
  $settings['registration']=isset($_POST['registration']);$settings['maintenance']=isset($_POST['maintenance']);
  if(!$settings['name'])throw new DomainException('Укажите название проекта.');
  change_state(function(&$s)use($settings,$user){$user=forum_actor_for_action($s,$user['id'],'admin');$s['settings']=array_replace($s['settings'],$settings);audit($s,$user,'Настройки форума');});notice('success','Настройки сохранены.');jump('admin',['tab'=>'settings']);
 case 'admin_category':
  require_admin($user);$id=textfield('id',64);$title=textfield('title',85);$desc=textfield('description',200);$position=(int)($_POST['position']??1);
  if(!$title)throw new DomainException('Название обязательно.');
  change_state(function(&$s)use($id,$title,$desc,$position,$user){$user=forum_actor_for_action($s,$user['id'],'admin');$key=$id?:uid('c-');if($id!==''&&!isset($s['categories'][$id]))throw new DomainException('Категория не найдена.');
   $s['categories'][$key]=['id'=>$key,'title'=>$title,'description'=>$desc,'position'=>max(1,min(500,$position))];audit($s,$user,'Изменил категорию '.$key);});notice('success','Категория сохранена.');jump('admin',['tab'=>'boards']);
 case 'admin_board':
  require_admin($user);$id=textfield('id',64);$title=textfield('title',85);$desc=textfield('description',200);$cat=textfield('category',64);$position=(int)($_POST['position']??1);$mode=textfield('mode',16);$icon=textfield('icon',40);
  if(!$title||!in_array($mode,['public','private','staff'],true)||!isset($initial['categories'][$cat]))throw new DomainException('Проверьте название, категорию и режим доступа.');
  change_state(function(&$s)use($id,$title,$desc,$cat,$position,$mode,$icon,$user){
   $user=forum_actor_for_action($s,$user['id'],'admin');
   $key=$id?:uid('b-');if($id&&!isset($s['boards'][$id]))throw new DomainException('Раздел не найден.');
   if(!isset($s['categories'][$cat]))throw new DomainException('Категория не найдена.');
   if($id!=='')forum_assert_board_mode_allowed($s,$id,$mode);
   $s['boards'][$key]=['id'=>$key,'title'=>$title,'description'=>$desc,'category'=>$cat,'position'=>max(1,min(500,$position)),'mode'=>$mode,'icon'=>preg_match('/^[a-z-]{2,40}$/',$icon)?$icon:'message-square','staff_post'=>isset($_POST['staff_post'])];audit($s,$user,'Изменил раздел '.$key);});notice('success','Раздел сохранён.');jump('admin',['tab'=>'boards']);
 case 'admin_remove':
  require_admin($user);$kind=textfield('kind',20);$id=textfield('id',64);
  change_state(function(&$s)use($kind,$id,$user){$user=forum_actor_for_action($s,$user['id'],'admin');if($kind='board'){if(!isset($s['boards'][$id]))throw new DomainException('Раздел не найден.');foreach($s['topics'] as $t)if($t['board']===$id)throw new DomainException('Сначала перенесите темы из раздела.');unset($s['boards'][$id]);}
   elseif($kind==='category'){if(!isset($s['categories'][$id]))throw new DomainException('Категория не найдена.');foreach($s['boards'] as $b)if($b['category']===$id)throw new DomainException('Сначала перенесите разделы из категории.');unset($s['categories'][$id]);}
   else throw new DomainException('Неизвестный объект.');audit($s,$user,'Удалил '.$kind.' '.$id);});notice('success','Удалено.');jump('admin',['tab'=>'boards']);
 case 'admin_user':
  require_admin($user);$id=textfield('id',64);$role=textfield('role',20);$ban=isset($_POST['banned']);
  if(!in_array($role,['user','moderator','admin'],true))throw new DomainException('Неизвестная роль.');
  change_state(function(&$s)use($id,$role,$ban,$user){$user=forum_actor_for_action($s,$user['id'],'admin');if(!isset($s['users'][$id]))throw new DomainException('Пользователь не найден.');if($id===$user['id'])throw new DomainException('Нельзя изменять собственную роль и статус.');
   if($s['users'][$id]['role']==='admin'&&$role!=='admin'){
    $admins=count(array_filter($s['users'],fn($u)=>$u['role']==='admin'));if($admins<2)throw new DomainException('Должен остаться хотя бы один администратор.');
   }
   $s['users'][$id]['role']=$role;$s['users'][$id]['banned']=$ban;audit($s,$user,'Роль и ограничения '.$id);});notice('success','Пользователь обновлён.');jump('admin',['tab'=>'users']);
 default:throw new DomainException('Неизвестное действие.');
 }
}

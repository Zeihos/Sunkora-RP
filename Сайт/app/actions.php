<?php
declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
if (!hash_equals(csrf(), (string)($_POST['csrf']??''))) { http_response_code(419); exit('Сессия устарела. Обновите страницу и повторите попытку.'); }
if (strlen((string)($_SERVER['CONTENT_LENGTH']??0)) > 60000) { http_response_code(413); exit('Слишком большой запрос'); }
$action=input('action',48);
try {
 switch($action) {
 case 'setup':
  if (store_read()['installed']) redirect('login');
  if (!age_limiter(ipkey('setup'),12,3600)) throw new DomainException('Слишком много попыток. Повтори позже.');
  $key=(string)(require dirname(__DIR__).'/config/install-key.php');
  if (!hash_equals($key,input('install_key',90))) throw new DomainException('Неверный ключ установки.');
  $nick=input('username',50); $email=strtolower(input('email',180)); $pass=(string)($_POST['password']??'');
  if (!valid_nick($nick)) throw new DomainException('Ник: от 3 до 24 букв, цифр, дефисов или подчёркиваний.');
  if (!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>180) throw new DomainException('Некорректная почта.');
  if (strlen($pass)<10 || strlen($pass)>256) throw new DomainException('Пароль должен быть от 10 до 256 символов.');
  $id=uuid(); $hash=password_hash($pass,PASSWORD_DEFAULT);
  store_change(function (&$s) use ($id,$nick,$email,$hash) {
   if ($s['installed']) throw new DomainException('Администратор уже создан.');
   $s['users'][]=['id'=>$id,'username'=>$nick,'email'=>$email,'password'=>$hash,'role'=>'admin','created'=>date('c'),'blocked'=>false];
   $s['installed']=true;
  });
  session_regenerate_id(true); $_SESSION['uid']=$id; $_SESSION['csrf']=bin2hex(random_bytes(32));
  flash('success','Сайт успешно настроен. Вы вошли как администратор.'); redirect('admin');
 case 'register':
  if (!store_read()['installed']) throw new DomainException('Сначала установите сайт.');
  if (!state_settings(store_read())['registration_enabled']) throw new DomainException('Регистрация временно отключена.');
  if (!age_limiter(ipkey('register'),8,3600)) throw new DomainException('Лимит регистраций. Попробуйте позже.');
  $nick=input('username',55); $email=strtolower(input('email',180)); $pass=(string)($_POST['password']??'');
  if (!valid_nick($nick)) throw new DomainException('Ник должен содержать 3–24 буквы, цифры, дефис или подчёркивание.');
  if (!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>180) throw new DomainException('Укажите действительную почту.');
  if (strlen($pass)<10||strlen($pass)>256) throw new DomainException('Пароль должен быть не короче 10 символов.');
  if (!hash_equals($pass,(string)($_POST['password_confirm']??''))) throw new DomainException('Пароли не совпадают.');
  if (!isset($_POST['agreement'])) throw new DomainException('Примите условия использования и политику конфиденциальности.');
  $hash=password_hash($pass,PASSWORD_DEFAULT); $id=uuid();
  store_change(function (&$s) use ($nick,$email,$hash,$id) {
   foreach($s['users'] as $u) if (strtolower((string)$u['username'])===strtolower($nick)||strtolower((string)$u['email'])===$email) throw new DomainException('Ник или электронная почта уже используются.');
   $s['users'][]=['id'=>$id,'username'=>$nick,'email'=>$email,'password'=>$hash,'role'=>'player','created'=>date('c'),'blocked'=>false];
  });
  session_regenerate_id(true); $_SESSION['uid']=$id; $_SESSION['csrf']=bin2hex(random_bytes(32));
  flash('success','Аккаунт создан. Добро пожаловать в SUNKORA!'); redirect('account');
 case 'login':
  if (!age_limiter(ipkey('login'),15,900)) throw new DomainException('Слишком много попыток входа. Попробуйте через 15 минут.');
  $identifier=strtolower(input('identifier',180)); $pass=(string)($_POST['password']??''); $user=null;
  foreach(store_read()['users'] as $u) if (strtolower((string)$u['username'])===$identifier||strtolower((string)$u['email'])===$identifier) { $user=$u; break; }
  if (!$user || !password_verify($pass,(string)$user['password'])) throw new DomainException('Неверный логин или пароль.');
  if (!empty($user['blocked'])) throw new DomainException('Доступ к аккаунту ограничен.');
  session_regenerate_id(true); $_SESSION['uid']=$user['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
  flash('success','Вы вошли в аккаунт.'); redirect(($user['role']??'')==='admin'?'admin':'account');
 case 'logout':
  unset($_SESSION['uid']); session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
  flash('success','Вы вышли из аккаунта.'); redirect('home');
 case 'password_change':
  require_login();
  if (!age_limiter(ipkey('password'),10,3600)) throw new DomainException('Слишком много запросов на изменение пароля.');
  $current=(string)($_POST['current_password']??''); $new=(string)($_POST['new_password']??'');
  $person=account();
  if (!password_verify($current,(string)$person['password'])) throw new DomainException('Текущий пароль указан неверно.');
  if (strlen($new)<10 || strlen($new)>256) throw new DomainException('Новый пароль должен содержать 10–256 символов.');
  if (!hash_equals($new,(string)($_POST['new_password_confirm']??''))) throw new DomainException('Новые пароли не совпадают.');
  if (hash_equals($current,$new)) throw new DomainException('Новый пароль должен отличаться от текущего.');
  $uid=$person['id']; $hash=password_hash($new,PASSWORD_DEFAULT);
  store_change(function (&$s) use ($uid,$hash) { foreach($s['users'] as &$u) if ($u['id']===$uid) { $u['password']=$hash; return; } throw new DomainException('Аккаунт не найден.'); });
  session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
  flash('success','Пароль успешно изменён.'); redirect('account');
 case 'account_delete':
  require_login(); $person=account();
  if ($person['role']==='admin') throw new DomainException('Удаление аккаунта владельца через сайт отключено.');
  if (!isset($_POST['confirm_delete'])) throw new DomainException('Подтвердите удаление аккаунта.');
  if (!password_verify((string)($_POST['password']??''),$person['password'])) throw new DomainException('Неверный пароль.');
  $uid=$person['id'];
  store_change(function (&$s) use ($uid) {
   $s['users']=array_values(array_filter($s['users'],static fn($u)=>$u['id']!==$uid));
   $s['tickets']=array_values(array_filter($s['tickets'],static fn($t)=>$t['user_id']!==$uid));
  });
  unset($_SESSION['uid']); session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
  flash('success','Ваш аккаунт и обращения удалены.'); redirect('home');
 case 'ticket_create':
  require_login(); if (!age_limiter(ipkey('ticket'),10,3600)) throw new DomainException('Лимит обращений превышен.');
  $title=input('title',110); $message=input('message',2000);
  if (textlen($title)<5||textlen($title)>100||textlen($message)<12||textlen($message)>2000) throw new DomainException('Тема от 5 до 100 символов, сообщение от 12 до 2000 символов.');
  $id=uuid(); $uid=account()['id'];
  store_change(function (&$s) use ($id,$uid,$title,$message) {
   $s['tickets'][]=['id'=>$id,'user_id'=>$uid,'title'=>$title,'status'=>'open','created'=>date('c'),'messages'=>[['by'=>$uid,'text'=>$message,'at'=>date('c')]]];
  });
  flash('success','Обращение отправлено. Ответ появится в личном кабинете.'); redirect('account','#ticket-'.$id);
 case 'ticket_reply':
  require_login(); $id=input('id',50); $message=input('message',2000);
  if (textlen($message)<2||textlen($message)>2000) throw new DomainException('Ответ должен содержать 2–2000 символов.');
  $user=account();
  store_change(function (&$s) use ($id,$user,$message) {
   foreach($s['tickets'] as &$t) if ($t['id']===$id) {
    if ($t['user_id']!==$user['id'] && $user['role']!=='admin') throw new DomainException('Нет доступа к обращению.');
    if ($t['status']==='closed' && $user['role']!=='admin') throw new DomainException('Обращение закрыто.');
    $t['messages'][]=['by'=>$user['id'],'text'=>$message,'at'=>date('c')];
    if ($user['role']==='admin') $t['status']='answered'; else $t['status']='open';
    return;
   }
   throw new DomainException('Обращение не найдено.');
  });
  flash('success','Сообщение отправлено.'); redirect($user['role']==='admin'?'admin':'account',$user['role']==='admin'?'&tab=tickets#ticket-'.$id:'#ticket-'.$id);
 case 'ticket_close':
  require_login(); $id=input('id',50); $user=account();
  store_change(function (&$s) use ($id,$user) {
   foreach($s['tickets'] as &$t) if ($t['id']===$id) {
    if ($t['user_id']!==$user['id']&&$user['role']!=='admin') throw new DomainException('Недостаточно прав.');
    $t['status']=$t['status']==='closed'?'open':'closed'; return;
   }
   throw new DomainException('Обращение не найдено.');
  });
  flash('success','Статус обращения обновлён.'); redirect($user['role']==='admin'?'admin':'account',$user['role']==='admin'?'&tab=tickets':'');
 case 'admin_backup':
  require_admin();
  $snapshot=store_read();
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="SUNKORA-backup-'.date('Y-m-d').'.json"');
  header('Cache-Control: private, no-store, max-age=0');
  echo json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  exit;
 case 'admin_settings':
  require_admin();
  $fields=['tagline'=>130,'description'=>450,'vk'=>255,'telegram'=>255,'discord'=>255,'contact_email'=>200,'shop_url'=>255,'announcement'=>350];
  $values=[]; foreach($fields as $f=>$max) $values[$f]=input($f,$max);
  foreach(['vk','telegram','discord','shop_url'] as $key) if ($values[$key]!==''&&!externalUrl($values[$key])) throw new DomainException('Ссылка «'.$key.'» должна начинаться с https:// или http://');
  if ($values['contact_email']!==''&&!filter_var($values['contact_email'],FILTER_VALIDATE_EMAIL)) throw new DomainException('Неверный адрес электронной почты.');
  $values['registration_enabled']=isset($_POST['registration_enabled']);
  store_change(function (&$s) use ($values) { $s['settings']=array_replace($s['settings'],$values); });
  flash('success','Настройки сохранены.'); redirect('admin','&tab=settings');
 case 'admin_save':
  require_admin();
  $kind=input('kind',30); $id=input('id',60);
  $schema=[
   'news'=>['date'=>12,'category'=>55,'title'=>130,'text'=>2000],
   'servers'=>['name'=>75,'host'=>253,'port'=>5],
   'downloads'=>['name'=>75,'platform'=>18,'url'=>255,'caption'=>130],
   'faq'=>['question'=>200,'answer'=>1600],
   'products'=>['name'=>110,'category'=>60,'description'=>900,'price_label'=>50],
  ];
  if (!isset($schema[$kind])) throw new DomainException('Неизвестный раздел.');
  $row=[]; foreach($schema[$kind] as $field=>$max) $row[$field]=input($field,$max);
  if ($kind==='news') {
   if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$row['date'])||!strtotime($row['date'])) throw new DomainException('Укажите корректную дату публикации.');
   if (textlen($row['title'])<3||textlen($row['text'])<10) throw new DomainException('Заполните заголовок и текст новости.');
  }
  if ($kind==='servers') {
   if ($row['name']===''||!preg_match('/^[a-zA-Z0-9.:-]{1,253}$/',$row['host'])||!ctype_digit($row['port'])||(int)$row['port']<1||(int)$row['port']>65535) throw new DomainException('Укажите название, домен/IP и порт 1–65535.');
   $row['port']=(int)$row['port'];
  }
  if ($kind==='downloads') {
   if ($row['name']==='' || !externalUrl($row['url'])|| !in_array($row['platform'],['windows','android','other'],true)) throw new DomainException('Укажите платформу, название и корректную ссылку на загрузку.');
  }
  if ($kind==='faq' && (textlen($row['question'])<3||textlen($row['answer'])<5)) throw new DomainException('Заполните вопрос и ответ.');
  if ($kind==='products' && (textlen($row['name'])<3||textlen($row['description'])<3)) throw new DomainException('Укажите название и описание товара.');
  $row['id']=$id!==''?$id:uuid();
  store_change(function (&$s) use ($kind,$row,$id) {
   if ($id!=='') { foreach($s[$kind] as &$existing) if ($existing['id']===$id) { $existing=$row; return; } throw new DomainException('Запись не найдена.'); }
   if (count($s[$kind])>=250) throw new DomainException('Превышен лимит записей.');
   $s[$kind][]=$row;
  });
  flash('success','Изменения сохранены.'); redirect('admin','&tab='.$kind);
 case 'admin_delete':
  require_admin(); $kind=input('kind',30); $id=input('id',60);
  if (!in_array($kind,['news','servers','downloads','faq','products'],true)) throw new DomainException('Неизвестный раздел.');
  store_change(function (&$s) use ($kind,$id) { $s[$kind]=array_values(array_filter($s[$kind],static fn($r)=>($r['id']??'')!==$id)); });
  flash('success','Запись удалена.'); redirect('admin','&tab='.$kind);
 case 'admin_user':
  require_admin(); $id=input('id',50); $mode=input('mode',20); $self=account()['id'];
  if ($id===$self) throw new DomainException('Нельзя изменять собственные права или блокировку.');
  if (!in_array($mode,['block','role'],true)) throw new DomainException('Неизвестное действие.');
  store_change(function (&$s) use ($id,$mode) {
   foreach($s['users'] as &$u) if ($u['id']===$id) {
    if ($mode==='block') $u['blocked']=!($u['blocked']??false);
    else $u['role']=($u['role']==='admin'?'player':'admin');
    return;
   } throw new DomainException('Пользователь не найден.');
  });
  flash('success','Пользователь обновлён.'); redirect('admin','&tab=users');
 default: throw new DomainException('Неизвестная операция.');
 }
} catch (DomainException $ex) {
 flash('error',$ex->getMessage());
 $dest=in_array($action,['setup','login','register'],true)?($action==='setup'?'setup':$action):((str_starts_with($action,'admin_'))?'admin':'account');
 redirect($dest,$dest==='admin'?'&tab='.rawurlencode(input('kind',30)?:'overview'):'');
} catch (Throwable $ex) {
 error_log('SUNKORA action failure: '.$ex->getMessage());
 flash('error','Не удалось выполнить операцию. Проверьте доступ к хранилищу и повторите попытку.');
 redirect(admin()?'admin':'home');
}

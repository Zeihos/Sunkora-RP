<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: private, no-store');
if(!empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH']>250000){http_response_code(413);exit('Слишком большой запрос.');}
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')ini_set('session.cookie_secure','1');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/app/actions.php';
require_once __DIR__.'/app/pages.php';
try {
 $state=read_state();
 if($_SERVER['REQUEST_METHOD']==='POST')dispatch_action($state);
 $user=current_user($state);
 if(!$state['installed']){$page='setup';}
 else { $page=trim((string)($_GET['p']??'home'));if($page==='setup')$page='home'; }
 if(!empty($state['settings']['maintenance'])&&!administrator($user)&&$page!=='login'){$page='maintenance';}
 if($page==='login'&&$user)$page='home';if($page==='register'&&$user)$page='home';
 $titles=['home'=>'Главная','board'=>'Раздел','topic'=>'Обсуждение','new'=>'Новая тема','search'=>'Поиск','members'=>'Участники','profile'=>'Профиль','login'=>'Вход','register'=>'Регистрация','setup'=>'Установка','settings'=>'Мои настройки','notifications'=>'Уведомления','edit'=>'Редактирование','report'=>'Жалоба','admin'=>'Админ-панель','moderation'=>'Модерация','maintenance'=>'Технические работы'];
 page_begin($titles[$page]??'Страница не найдена',$state,$user,in_array($page,['search','members','notifications'],true)?$page:'home');
 switch($page){
 case 'home':page_home($state,$user);break;
 case 'board':page_board($state,$user);break;
 case 'topic':page_topic($state,$user);break;
 case 'new':page_new($state,$user);break;
 case 'search':page_search($state,$user);break;
 case 'members':page_members($state,$user);break;
 case 'profile':page_profile($state,$user);break;
 case 'login':case 'register':case 'setup':page_auth($page,$state);break;
 case 'settings':page_settings($state,$user);break;
 case 'notifications':page_notifications($state,$user);break;
 case 'edit':page_edit($state,$user);break;
 case 'report':page_report($state,$user);break;
 case 'admin':page_admin($state,$user);break;
 case 'moderation':page_moderation($state,$user);break;
 case 'maintenance':page_error('Технические работы','Форум на обслуживании. Скоро вернёмся!');break;
 default:http_response_code(404);page_error('Страница не найдена','Проверь ссылку или вернись на главную.');
 }
 page_end($state,$user);
} catch(DomainException $ex){
 $state=read_state();$user=current_user($state);$page=trim((string)($_GET['p']??'home'));$page=$state['installed']?$page:'setup';
 if($_SERVER['REQUEST_METHOD']==='POST'){notice('error',$ex->getMessage());$ref=(string)($_SERVER['HTTP_REFERER']??'');$base=(string)($_SERVER['HTTP_HOST']??'');
  $script=(string)($_SERVER['SCRIPT_NAME']??'/index.php');$basePath=rtrim(dirname($script),'/').'/';$refPath=(string)parse_url($ref,PHP_URL_PATH);
  if($ref&&parse_url($ref,PHP_URL_HOST)===$base&&in_array($refPath,[$script,$basePath],true)){header('Location: '.$ref,true,303);exit;}
  $action=(string)($_POST['action']??'');
  $fallback=match($action){
   'reply_create','topic_watch','topic_moderate'=>route('topic',['t'=>(string)($_POST['topic']??'')]),
   'topic_create'=>route('new',['f'=>(string)($_POST['board']??'')]),
   'post_edit','post_delete','report'=>route('home'),
   'register'=>route('register'),'login'=>route('login'),'setup'=>route('setup'),
   'profile_save','password_change'=>route('settings'),
   'admin_settings'=>route('admin',['tab'=>'settings']),
   'admin_category','admin_board','admin_remove'=>route('admin',['tab'=>'boards']),
   'admin_user'=>route('admin',['tab'=>'users']),
   'report_resolve'=>route('moderation'),
   default=>route($page)
  };
  header('Location: '.$fallback,true,303);exit;
 }
 http_response_code(400);page_begin('Ошибка',$state,$user);page_error('Ошибка',$ex->getMessage());page_end($state,$user);
} catch(Throwable $ex){error_log('[SUNKORA FORUM] '.$ex->getMessage().' in '.$ex->getFile().':'.$ex->getLine());http_response_code(500);echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Ошибка SUNKORA</title><body style="background:#11131c;color:#eee;font:18px system-ui;padding:60px"><h1>Ошибка сервера</h1><p>Проверьте настройки PHP и права записи в storage/. Подробности — в журнале ошибок сервера.</p></body></html>';}

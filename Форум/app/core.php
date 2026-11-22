<?php
declare(strict_types=1);
const STORE_PREFIX = "<?php http_response_code(404); exit; ?>\n";
const BASE_DIR = __DIR__ . '/..';
function now(): string { return gmdate('Y-m-d H:i:s'); }
function uid(string $prefix=''): string { return $prefix . bin2hex(random_bytes(9)); }
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function lower(string $text): string { return strtr(strtolower($text), array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЫЬЪЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщыьъэюя',-1,PREG_SPLIT_NO_EMPTY))); }
function txtlen(string $text): int { return function_exists('mb_strlen') ? mb_strlen($text,'UTF-8') : strlen(iconv('UTF-8','UTF-16LE',$text))/2; }
function textfield(string $key,int $limit=250): string {
 $value=trim((string)($_POST[$key]??''));
 if(txtlen($value)>$limit) throw new DomainException('Поле «'.$key.'» слишком длинное (не более '.$limit.' символов).');
 return $value;
}
function valid_name(string $name): bool { return (bool)preg_match('/^[\p{L}\p{N}_-]{3,24}$/u',$name); }
function route(string $page='home', array $vars=[]): string {return '?'.http_build_query(['p'=>$page]+$vars, '', '&', PHP_QUERY_RFC3986);}
function local_return_url(string $ref,string $host,array $allow): ?string {
 if($ref===''||$host==='')return null;
 $parts=parse_url($ref);$origin=parse_url('http://'.$host);
 if(!is_array($parts)||!is_array($origin)||empty($origin['host']))return null;
 if(!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true))return null;
 if(!isset($parts['host'])||strcasecmp((string)$parts['host'],(string)$origin['host'])!==0)return null;
 if(isset($parts['user'])||isset($parts['pass']))return null;
 $path=(string)($parts['path']??'');$query=(string)($parts['query']??'');
 if(!in_array($path,$allow,true)||preg_match('/[\x00-\x1f]/',$path.$query))return null;
 return $path.($query!==''?'?'.$query:'');
}
function jump(string $page='home',array $vars=[]): never {header('Location: '.route($page,$vars),true,303);exit;}
function safeurl(string $s): bool {return $s==='' || (bool)(filter_var($s,FILTER_VALIDATE_URL)&&in_array(parse_url($s,PHP_URL_SCHEME),['https','http'],true));}
function token(): string {return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function csrf_field(): string {return '<input type="hidden" name="csrf" value="'.h(token()).'">';}
function notice(string $type,string $message): void {$_SESSION['notice']=['type'=>$type,'message'=>$message];}
function require_csrf(): void {if(!is_string($_POST['csrf']??null)||!hash_equals(token(),$_POST['csrf'])) throw new DomainException('Сессия формы истекла. Обновите страницу.');}
function forum_storage_dir(): string {
 $path=getenv('SUNKORA_FORUM_STORAGE_DIR');
 if(!is_string($path)||$path==='')return BASE_DIR.'/storage';
 $dir=realpath($path);$root=realpath(BASE_DIR);
 if($dir===false||!is_dir($dir)||!is_writable($dir))throw new RuntimeException('SUNKORA_FORUM_STORAGE_DIR: каталог недоступен');
 if($root!==false&&($dir===$root||str_starts_with($dir,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)))throw new RuntimeException('Хранилище форума должно быть вне публичного каталога');
 return $dir;
}
function storage_path(): string {return forum_storage_dir().'/state.php'; }
function seed_state(): array {
 return [
  'installed'=>false,
  'settings'=>['name'=>'SUNKORA ROLEPLAY','tagline'=>'Создай свою историю','welcome'=>'Добро пожаловать на официальный форум SUNKORA ROLEPLAY. Обсуждай события, находи друзей и участвуй в жизни проекта.','site_url'=>'','vk'=>'https://vk.ru/sunkora_rp','tg'=>'https://t.me/sunkora_rp','registration'=>true,'maintenance'=>false,'announcement'=>'Официальный форум SUNKORA ROLEPLAY — следите за анонсами проекта.'],
  'users'=>[], 'topics'=>[], 'posts'=>[], 'notifications'=>[], 'reports'=>[], 'attempts'=>[], 'audit'=>[],
  'categories'=>[
    'official'=>['id'=>'official','title'=>'Официальная информация','description'=>'Новости и важные документы проекта','position'=>1],
    'servers'=>['id'=>'servers','title'=>'Игровые серверы','description'=>'Жизнь и управление игровым миром','position'=>2],
    'support'=>['id'=>'support','title'=>'Поддержка и обращения','description'=>'Поможем разобраться с вопросами','position'=>3],
    'community'=>['id'=>'community','title'=>'Сообщество SUNKORA','description'=>'Общение, творчество и знакомства','position'=>4],
  ],
  'boards'=>[
    'news'=>['id'=>'news','category'=>'official','title'=>'Новости проекта','description'=>'Обновления, мероприятия и официальные объявления','icon'=>'megaphone','mode'=>'public','staff_post'=>true,'position'=>1],
    'rules'=>['id'=>'rules','category'=>'official','title'=>'Правила проекта','description'=>'Основные требования и регламенты','icon'=>'book','mode'=>'public','staff_post'=>true,'position'=>2],
    'info'=>['id'=>'info','category'=>'official','title'=>'Информация и FAQ','description'=>'Ответы на часто задаваемые вопросы','icon'=>'circle-help','mode'=>'public','staff_post'=>true,'position'=>3],
    'server'=>['id'=>'server','category'=>'servers','title'=>'Основной сервер','description'=>'Обсуждения, предложения и события игрового сервера','icon'=>'server','mode'=>'public','staff_post'=>false,'position'=>1],
    'factions'=>['id'=>'factions','category'=>'servers','title'=>'Организации и фракции','description'=>'Государственные структуры, бизнес и неофициальные сообщества','icon'=>'shield','mode'=>'public','staff_post'=>false,'position'=>2],
    'applications'=>['id'=>'applications','category'=>'servers','title'=>'Заявления на должности','description'=>'Набор в команды, заявки и анкеты','icon'=>'clipboard-list','mode'=>'public','staff_post'=>false,'position'=>3],
    'complaints'=>['id'=>'complaints','category'=>'support','title'=>'Жалобы и апелляции','description'=>'Обращения видны только автору и команде проекта','icon'=>'shield-alert','mode'=>'private','staff_post'=>false,'position'=>1],
    'help'=>['id'=>'help','category'=>'support','title'=>'Вопросы и техподдержка','description'=>'Подсказки по установке, аккаунтам и игре','icon'=>'life-buoy','mode'=>'public','staff_post'=>false,'position'=>2],
    'suggestions'=>['id'=>'suggestions','category'=>'community','title'=>'Предложения и идеи','description'=>'Обсуждай, каким будет SUNKORA ROLEPLAY','icon'=>'lightbulb','mode'=>'public','staff_post'=>false,'position'=>1],
    'chat'=>['id'=>'chat','category'=>'community','title'=>'Свободное общение','description'=>'Внеигровое общение, знакомства и творчество','icon'=>'messages-square','mode'=>'public','staff_post'=>false,'position'=>2],
  ],
 ];
}
function read_state(): array {
 $f=storage_path(); if(!is_file($f))return seed_state();
 $raw=file_get_contents($f); if(!is_string($raw)||!str_starts_with($raw,STORE_PREFIX))throw new RuntimeException('Повреждено хранилище форума.');
 $data=json_decode(substr($raw,strlen(STORE_PREFIX)),true,512,JSON_THROW_ON_ERROR);
 return is_array($data)?array_replace(seed_state(),$data):seed_state();
}
function change_state(callable $work): mixed {
 $dir=forum_storage_dir(); if(!is_dir($dir)||!is_writable($dir)) throw new RuntimeException('Нет доступа на запись в storage/.');
 $lock=fopen($dir.'/state.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Не удалось заблокировать хранилище.');
 try {
  $state=read_state(); $result=$work($state);
  $json=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
  if(strlen($json)>16*1024*1024)throw new RuntimeException('Достигнут предел размера локальной базы; требуется перенос на SQL.');
  $temp=tempnam($dir,'state_');if($temp===false)throw new RuntimeException('Не удалось сохранить данные.');
  try {
   if(file_put_contents($temp,STORE_PREFIX.$json."\n",LOCK_EX)===false)throw new RuntimeException('Ошибка записи данных.');
   @chmod($temp,0600);if(!rename($temp,storage_path()))throw new RuntimeException('Ошибка замены данных.');
  } finally {if(is_file($temp))@unlink($temp);}
  return $result;
 } finally {flock($lock,LOCK_UN);fclose($lock);}
}
function current_user(array $state): ?array { $id=$_SESSION['user']??'';return is_string($id)?($state['users'][$id]??null):null; }
function staff(?array $user): bool {return in_array($user['role']??'',['admin','moderator'],true);}
function administrator(?array $user): bool {return ($user['role']??'')==='admin';}
function require_user(?array $user): void {if(!$user)throw new DomainException('Войдите или зарегистрируйтесь для этого действия.'); if(!empty($user['banned']))throw new DomainException('Доступ к публикации ограничен администрацией.');}
function require_staff(?array $user): void {require_user($user);if(!staff($user))throw new DomainException('Действие доступно только модераторам.');}
function require_admin(?array $user): void {require_user($user);if(!administrator($user))throw new DomainException('Действие доступно только администратору.');}
function can_read_board(array $board,?array $user): bool {return ($board['mode']??'public')!=='staff'||staff($user);}
function can_read_topic(array $topic, array $board,?array $user): bool {
 return can_read_board($board,$user) && (($board['mode']??'public')!=='private'||staff($user)||($user['id']??'')===$topic['author']);
}
function can_write_board(array $board,?array $user): bool {return $user&&!($user['banned']??false)&&can_read_board($board,$user)&&(!($board['staff_post']??false)||staff($user));}
function can_edit_post(array $post,?array $user): bool {return $user && !(bool)($user['banned']??false) && (staff($user)||($post['author']??'')===$user['id']);}
function forum_actor_for_write(array $state,string $id): array {
 $fresh=$state['users'][$id]??null;
 if(!is_array($fresh))throw new DomainException('Аккаунт не найден. Войдите заново.');
 require_user($fresh);
 return $fresh;
}
function forum_actor_for_action(array $state,string $id,string $scope='user'): array {
 $actor=forum_actor_for_write($state,$id);
 if(!empty($state['settings']['maintenance'])&&!administrator($actor))
  throw new DomainException('Форум на техническом обслуживании.');
 if($scope==='staff')require_staff($actor);
 elseif($scope==='admin')require_admin($actor);
 elseif($scope!=='user')throw new LogicException('Неизвестный уровень доступа');
 return $actor;
}
function forum_require_registration_open(array $state): void {
 if(empty($state['installed']))throw new DomainException('Сначала завершите установку форума.');
 if(empty($state['settings']['registration']))throw new DomainException('Регистрация временно отключена.');
 if(!empty($state['settings']['maintenance']))throw new DomainException('Форум на техническом обслуживании.');
}
function forum_assert_board_permission(array $state,string $id,string $boardId): array {
 $actor=forum_actor_for_write($state,$id);
 if(!empty($state['settings']['maintenance'])&&!administrator($actor))throw new DomainException('Форум на техническом обслуживании.');
 $board=$state['boards'][$boardId]??null;
 if(!is_array($board)||!can_write_board($board,$actor))throw new DomainException('Нет доступа к публикации в этом разделе.');
 return $actor;
}
function forum_assert_move_allowed(array $source,array $destination): void {
 if(($source['mode']??'public')!==($destination['mode']??'public'))
  throw new DomainException('Нельзя переносить тему между разделами с разным режимом доступа.');
}
function forum_assert_board_mode_allowed(array $state,string $boardId,string $newMode): void {
 $existing=$state['boards'][$boardId]??null;
 if(!is_array($existing)||($existing['mode']??'public')===$newMode)return;
 foreach($state['topics'] as $topic)
  if(($topic['board']??'')===$boardId)
   throw new DomainException('Нельзя менять режим раздела, пока он содержит темы.');
}
function ordered_boards(array $state,string $cat): array {
 $result=array_values(array_filter($state['boards'],fn($b)=>$b['category']===$cat));usort($result,fn($a,$b)=>($a['position']<=>$b['position'])?:strcmp($a['title'],$b['title']));return $result;
}
function all_visible_topics(array $state,?array $user): array {
 $topics=[];foreach($state['topics'] as $t){$b=$state['boards'][$t['board']]??null;if(!$b||!can_read_topic($t,$b,$user))continue;$topics[]=$t;}
 usort($topics,fn($a,$b)=>($b['pinned']<=>$a['pinned'])?:strcmp($b['updated'],$a['updated']));return $topics;
}
function thread_posts(array $state,string $topic_id): array {
 $posts=array_values(array_filter($state['posts'],fn($p)=>$p['topic']===$topic_id && !($p['deleted']??false)));
 usort($posts,fn($a,$b)=>strcmp($a['created'],$b['created']));return $posts;
}
function username(array $state,string $id): string {return $state['users'][$id]['name']??'Удалённый пользователь';}
function since(string $time): string {
 $n=max(0,time()-strtotime($time.' UTC'));if($n<60)return 'только что';if($n<3600)return floor($n/60).' мин. назад';if($n<86400)return floor($n/3600).' ч. назад';if($n<604800)return floor($n/86400).' дн. назад';return date('d.m.Y',strtotime($time.' UTC'));
}
function datetime_string(string $d): string{return date('d.m.Y в H:i',strtotime($d.' UTC'));}
function shortbody(string $body,int $limit=145): string { if(txtlen($body)<=$limit)return $body; return preg_replace('/^(.{0,145}).*$/us','$1…',$body)??$body; }
function bm(string $body): string {
 $escaped=h($body); $escaped=preg_replace('/\[b\]([\s\S]*?)\[\/b\]/iu','<strong>$1</strong>',$escaped);
 $escaped=preg_replace('/\[i\]([\s\S]*?)\[\/i\]/iu','<em>$1</em>',$escaped);
 $escaped=preg_replace('/\[code\]([\s\S]*?)\[\/code\]/iu','<code>$1</code>',$escaped);
 $escaped=preg_replace_callback('~(?<![="\'])https?://[^\s<\[\]]+~iu',static function($m){$url=html_entity_decode($m[0],ENT_QUOTES,'UTF-8');return '<a href="'.h($url).'" rel="noopener noreferrer nofollow" target="_blank">'.h($url).'</a>';},$escaped);
 return nl2br($escaped,false);
}
function rate_limit(array &$state,string $key,int $limit,int $window): void {
 $now=time();foreach($state['attempts'] as $k=>$entry){if(($entry['until']??0)<$now)unset($state['attempts'][$k]);}
 $entry=$state['attempts'][$key]??['count'=>0,'until'=>$now+$window];if($entry['until']<$now)$entry=['count'=>0,'until'=>$now+$window];
 if($entry['count']>=$limit)throw new DomainException('Слишком много попыток. Повторите позже.');$entry['count']++;$state['attempts'][$key]=$entry;
}
function throttled(array &$state,string $action,int $limit=10,int $window=300): void {rate_limit($state,hash('sha256',$action.'|'.($_SERVER['REMOTE_ADDR']??'local')),$limit,$window);}
function audit(array &$state,?array $u,string $action): void {$state['audit'][]=['at'=>now(),'by'=>$u['id']??'system','action'=>$action];if(count($state['audit'])>300)$state['audit']=array_slice($state['audit'],-300);}
function notify(array &$state,string $userId,string $message,string $link): void {
 if(!isset($state['users'][$userId]))return;$id=uid('n-');$state['notifications'][$id]=['id'=>$id,'to'=>$userId,'message'=>$message,'link'=>$link,'created'=>now(),'read'=>false];
 if(count($state['notifications'])>2000){uasort($state['notifications'],fn($a,$b)=>strcmp($a['created'],$b['created']));$state['notifications']=array_slice($state['notifications'],-1700,null,true);}
}
function page_int(): int {return max(1,min(999,(int)($_GET['page']??1)));}
function paged(array $items,int $per=12): array {$page=page_int();$pages=max(1,(int)ceil(count($items)/$per));$page=min($pages,$page);return [array_slice($items,($page-1)*$per,$per),$page,$pages];}
function valid_id(string $id): bool {return (bool)preg_match('/^[a-z0-9-]{1,64}$/',$id);}

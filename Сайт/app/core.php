<?php
declare(strict_types=1);
/* SUNKORA ROLEPLAY: small, dependency-free PHP content store. */
const SUNKORA_STORE_HEADER = "<?php http_response_code(404); exit; ?>\n";
function defaults(): array {
 return [
  'installed'=>false, 'users'=>[], 'tickets'=>[], 'attempts'=>[],
  'settings'=>[
   'tagline'=>'Твой город. Твои правила. Твоя история.',
   'description'=>'SUNKORA ROLEPLAY — игровое сообщество с атмосферой большого города и свободой выбора своего пути.',
   'vk'=>'https://vk.ru/sunkora_rp', 'telegram'=>'https://t.me/sunkora_rp',
   'discord'=>'', 'contact_email'=>'', 'registration_enabled'=>true,
   'shop_url'=>'', 'announcement'=>'SUNKORA ROLEPLAY — следите за новостями и анонсами в наших сообществах.',
  ],
  'news'=>[
   ['id'=>'news-welcome','date'=>'2026-10-28','category'=>'Информация','title'=>'Добро пожаловать в SUNKORA ROLEPLAY','text'=>'Официальный сайт проекта SUNKORA ROLEPLAY. Здесь будут публиковаться новости, адреса серверов, ссылки на загрузки и полезная информация. Следите за подтверждёнными объявлениями в наших сообществах.'],
   ['id'=>'news-social','date'=>'2026-10-28','category'=>'Сообщество','title'=>'Мы на связи: ВКонтакте и Telegram','text'=>'Присоединяйтесь к официальному сообществу vk.ru/sunkora_rp и Telegram-каналу t.me/sunkora_rp, чтобы узнавать о событиях проекта из первых рук.'],
  ],
  'servers'=>[], 'downloads'=>[], 'products'=>[],
  'faq'=>[
   ['id'=>'faq-1','question'=>'Как начать играть в SUNKORA?','answer'=>'Открой раздел «Как играть». Когда администрация опубликует официальный лаунчер и адрес сервера, там появятся необходимые ссылки.'],
   ['id'=>'faq-2','question'=>'Где скачать игровой клиент?','answer'=>'Официальная загрузка будет доступна в разделе «Как играть» после публикации ссылки администрацией проекта.'],
   ['id'=>'faq-3','question'=>'Как узнать IP сервера?','answer'=>'После публикации адрес появится в разделе «Серверы». Адрес можно будет скопировать одним нажатием.'],
   ['id'=>'faq-4','question'=>'Где следить за новостями?','answer'=>'В официальных сообществах: vk.ru/sunkora_rp и t.me/sunkora_rp.'],
   ['id'=>'faq-5','question'=>'Можно ли покупать привилегии на сайте?','answer'=>'Покупки недоступны, пока администрация не подключит официальный защищённый магазин. Никому не отправляйте платежи по непроверенным реквизитам.'],
   ['id'=>'faq-6','question'=>'Как обратиться в поддержку?','answer'=>'Зарегистрируйте аккаунт на сайте и создайте обращение в личном кабинете или обратитесь через официальное сообщество.'],
  ],
 ];
}
function site_storage_dir(): string {
 $path=getenv('SUNKORA_SITE_STORAGE_DIR');
 if(!is_string($path)||$path==='')return dirname(__DIR__).'/data';
 $dir=realpath($path);$root=realpath(dirname(__DIR__));
 if($dir===false||!is_dir($dir)||!is_writable($dir))throw new RuntimeException('SUNKORA_SITE_STORAGE_DIR: каталог недоступен');
 if($root!==false&&($dir===$root||str_starts_with($dir,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)))throw new RuntimeException('Хранилище сайта должно быть вне публичного каталога');
 return $dir;
}
function dbfile(): string { return site_storage_dir().'/store.php'; }
function parse_store(string $content): array {
 if (str_starts_with($content,SUNKORA_STORE_HEADER)) $content=substr($content,strlen(SUNKORA_STORE_HEADER));
 elseif ($content!=='') throw new RuntimeException('Неверный формат файла хранилища');
 $decoded=$content==='' ? [] : json_decode($content,true,512,JSON_THROW_ON_ERROR);
 if (!is_array($decoded)) throw new RuntimeException('Некорректные данные');
 return array_replace(defaults(),$decoded);
}
function store_read(): array {
 $file=dbfile(); if (!is_file($file)) return defaults();
 $f=@fopen($file,'rb'); if (!$f) throw new RuntimeException('Хранилище недоступно');
 try { if (!flock($f,LOCK_SH)) throw new RuntimeException('Хранилище заблокировано');
  $raw=stream_get_contents($f); flock($f,LOCK_UN);
  return parse_store((string)$raw);
 } finally { fclose($f); }
}
function store_change(callable $operation): mixed {
 $file=dbfile(); $dir=dirname($file);
 if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Нет прав записи в каталог data/');
 $f=@fopen($file,'c+b'); if (!$f) throw new RuntimeException('Нет прав записи в data/store.php');
 try { if (!flock($f,LOCK_EX)) throw new RuntimeException('Хранилище занято');
  rewind($f); $state=parse_store((string)stream_get_contents($f));
  $result=$operation($state);
  $json=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  rewind($f); if (!ftruncate($f,0)) throw new RuntimeException('Ошибка записи');
  $payload=SUNKORA_STORE_HEADER.$json."\n";
  $n=fwrite($f,$payload); if ($n===false || $n!==strlen($payload)) throw new RuntimeException('Неполная запись');
  fflush($f); @chmod($file,0600); flock($f,LOCK_UN);
  return $result;
 } finally { fclose($f); }
}
function uuid(): string { return bin2hex(random_bytes(12)); }
function e(mixed $s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function url(string $page): string { return $page==='home' ? './' : '?page='.rawurlencode($page); }
function active(string $name,string $page): string { return $page===$name ? ' active' : ''; }
function externalUrl(?string $value): bool {
 if (!$value || !filter_var($value,FILTER_VALIDATE_URL)) return false;
 return in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['https','http'],true);
}
function csrf(): string { if (!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function redirect(string $page='home',string $extra=''): never { header('Location: '.url($page).$extra, true,303); exit; }
function input(string $name,int $max=255): string { return trim(substr((string)($_POST[$name]??''),0,$max*4)); }
function account(?array $state=null): ?array {
 $uid=$_SESSION['uid']??null; if (!is_string($uid)) return null;
 foreach (($state??store_read())['users'] as $person) if (hash_equals((string)$person['id'],$uid)) return $person;
 return null;
}
function admin(?array $state=null): bool { return (account($state)['role']??'')==='admin'; }
function require_login(): void { if (!account()) { flash('error','Войдите в аккаунт, чтобы продолжить.'); redirect('login'); } }
function require_admin(): void { if (!admin()) { http_response_code(403); flash('error','Нет доступа к панели управления.'); redirect('login'); } }
function state_settings(array $state): array { return array_replace(defaults()['settings'], $state['settings']??[]); }
function valid_nick(string $s): bool { return (bool)preg_match('/^[\p{L}\p{N}_-]{3,24}$/u',$s); }
function textlen(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s,'UTF-8') : strlen($s); }
function clean_url(string $s): string { return ($s==='' || externalUrl($s)) ? $s : ''; }
function age_limiter(string $key,int $limit,int $window): bool {
 return store_change(function (&$st) use ($key,$limit,$window) {
   $now=time(); foreach($st['attempts'] as $id=>$entry) if (($entry['until']??0)<$now) unset($st['attempts'][$id]);
   $entry=$st['attempts'][$key]??['count'=>0,'until'=>$now+$window];
   if ($entry['count'] >= $limit && $entry['until']>$now) return false;
   if ($entry['until']<=$now) $entry=['count'=>0,'until'=>$now+$window];
   $entry['count']++; $st['attempts'][$key]=$entry; return true;
 });
}
function ipkey(string $action): string { return hash('sha256',$action.'|'.($_SERVER['REMOTE_ADDR']??'unknown')); }
function date_human(string $d): string { $t=strtotime($d); return $t?date('d.m.Y',$t):$d; }
function sampStatus(string $host,int $port): ?array {
 if ($port<1 || $port>65535 || !preg_match('/^[a-z0-9.:-]{1,253}$/i',$host)) return null;
 $ip=filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)?$host:gethostbyname($host);
 if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) return null;
 $parts=array_map('intval',explode('.',$ip));
 $query='SAMP'.pack('C4',...$parts).pack('v',$port).'i';
 $socket=@stream_socket_client('udp://'.$ip.':'.$port,$errno,$errstr,0.35);
 if (!$socket) return null;
 stream_set_timeout($socket,0,400000); @fwrite($socket,$query); $reply=@fread($socket,4096); fclose($socket);
 if (!$reply || strlen($reply)<20 || substr($reply,0,4)!=='SAMP'|| substr($reply,10,1)!=='i') return null;
 $count=unpack('v',substr($reply,12,2))[1]??0; $max=unpack('v',substr($reply,14,2))[1]??0;
 return ['players'=>$count,'max'=>$max];
}
function icon(string $name): string {
 $icons = [
  'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>','up'=>'<path d="M7 17 17 7M8 7h9v9"/>',
  'play'=>'<path d="m8 5 11 7-11 7V5Z"/>','users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  'shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
  'compass'=>'<circle cx="12" cy="12" r="10"/><path d="m16.2 7.8-2.4 6-6 2.4 2.4-6 6-2.4Z"/>',
  'spark'=>'<path d="m12 3-1.9 6.1L4 11l6.1 1.9L12 19l1.9-6.1L20 11l-6.1-1.9L12 3Z"/>',
  'download'=>'<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>','server'=>'<rect width="18" height="8" x="3" y="3" rx="2"/><rect width="18" height="8" x="3" y="13" rx="2"/><path d="M7 7h.01M7 17h.01M11 7h8M11 17h8"/>',
  'gift'=>'<rect width="18" height="14" x="3" y="8" rx="2"/><path d="M12 8v14M3 12h18M12 8H8a3 3 0 1 1 3-3c0 1 1 3 1 3Zm0 0h4a3 3 0 1 0-3-3c0 1-1 3-1 3Z"/>',
  'news'=>'<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h10M7 11h10M7 15h4M15 15h2"/>','help'=>'<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
  'vk'=>'<path d="M3 7.5c.2 5 2.9 8.5 7.2 9V13c2.3.2 4 1.4 4.6 3.5h3.5c-.7-2.7-2.3-4.1-3.2-4.7 1-.6 2.7-2.3 3.1-4.3H15c-.6 1.8-1.9 3.5-2.8 3.7V7.5H9.5v6C7.9 13 6.7 10.5 6.5 7.5H3Z"/>',
  'telegram'=>'<path d="m21 3-3 18-6.7-5.2-3.5 3-.4-4.3L3 12l18-9ZM8.8 14.6 18 6.7"/>',
  'check'=>'<path d="m5 12 4 4L19 6"/>','copy'=>'<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
  'clock'=>'<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>','chevron'=>'<path d="m6 9 6 6 6-6"/>','menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
  'close'=>'<path d="M18 6 6 18M6 6l12 12"/>','phone'=>'<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>','monitor'=>'<rect x="3" y="3" width="18" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
  'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
  'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M4 12h2m12 0h2M12 4v2m0 12v2m-5.7-4.3 1.4-1.4m8.6-8.6 1.4-1.4m-11.4 0 1.4 1.4m8.6 8.6 1.4 1.4"/>',
 ];
 return '<svg class="i" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$name]??'').'</svg>';
}

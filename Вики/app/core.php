<?php
declare(strict_types=1);

const WIKI_ROOT = __DIR__ . '/..';
// Use a private directory outside the web root on Nginx or other servers.
// Existing installations keep /storage until deliberately migrated.
$privateDir = getenv('SUNKORA_WIKI_STORAGE_DIR');
if (is_string($privateDir) && $privateDir !== '') {
    $resolved = realpath($privateDir);
    $publicRoot = realpath(WIKI_ROOT);
    if ($resolved === false || !is_dir($resolved) || !is_writable($resolved)) {
        throw new RuntimeException('SUNKORA_WIKI_STORAGE_DIR должен указывать на существующий каталог с правом записи');
    }
    if ($publicRoot !== false && ($resolved === $publicRoot || str_starts_with($resolved, rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Каталог SUNKORA_WIKI_STORAGE_DIR должен находиться вне публичного корня Wiki');
    }
    define('WIKI_STORE_DIR', $resolved);
} else {
    define('WIKI_STORE_DIR', WIKI_ROOT . '/storage');
}
define('WIKI_DATA', WIKI_STORE_DIR . '/wiki.json');
define('WIKI_LOCK', WIKI_STORE_DIR . '/.lock');

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function wiki_url(array $params = []): string {
    return 'index.php' . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
}
function safe_external_url(mixed $url): string {
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 500 || !preg_match('~^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?(?:[/#?][^\s<>]*)?$~i', $url)) return '';
    return $url;
}
function clean_text(mixed $text, int $max = 500): string {
    if (!is_scalar($text) && $text !== null) return '';
    $text = trim(str_replace("\0", '', (string)$text));
    return strlen($text) <= $max * 4 ? $text : substr($text, 0, $max * 4);
}
function wiki_length(string $text): int {
    return preg_match_all('/./us', $text) ?: 0;
}
function wiki_now(): string { return gmdate('Y-m-d H:i:s'); }
function wiki_date(?string $date): string {
    if (!$date) return '';
    try { return (new DateTimeImmutable($date . ' UTC'))->format('d.m.Y'); }
    catch (Throwable) { return ''; }
}
function wiki_read(): ?array {
    if (!is_file(WIKI_DATA)) return null;
    $lock = fopen(WIKI_LOCK, 'c');
    if (!$lock) throw new RuntimeException('Не удалось открыть блокировку хранилища');
    try {
        if (!flock($lock, LOCK_SH)) throw new RuntimeException('Не удалось заблокировать хранилище');
        $raw = file_get_contents(WIKI_DATA);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) throw new RuntimeException('Файл данных повреждён; восстановите резервную копию');
        return $data;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function wiki_change(callable $callback): mixed {
    $lock = fopen(WIKI_LOCK, 'c');
    if (!$lock) throw new RuntimeException('Нет прав записи в storage/');
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Не удалось заблокировать хранилище');
        $raw = is_file(WIKI_DATA) ? file_get_contents(WIKI_DATA) : false;
        $db = $raw === false ? null : json_decode($raw, true);
        if ($raw !== false && !is_array($db)) throw new RuntimeException('Файл данных повреждён');
        $result = $callback($db);
        if (!is_array($db)) throw new RuntimeException('Недопустимая структура данных');
        $json = json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        $tmp = tempnam(dirname(WIKI_DATA), '.wiki-');
        if ($tmp === false) throw new RuntimeException('Нет прав записи в storage/');
        try {
            if (file_put_contents($tmp, $json . "\n") === false) throw new RuntimeException('Не удалось записать данные');
            @chmod($tmp, 0600);
            if (!rename($tmp, WIKI_DATA)) throw new RuntimeException('Не удалось обновить базу данных');
        } finally { if (is_file($tmp)) @unlink($tmp); }
        return $result;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function wiki_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = str_replace('\\', '/', dirname($script));
    $path = ($dir === '/' || $dir === '.') ? '/' : rtrim($dir, '/') . '/';
    session_name('SUNKORA_WIKI');
    session_set_cookie_params(['lifetime'=>0, 'path'=>$path, 'secure'=>$https, 'httponly'=>true, 'samesite'=>'Lax']);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function wiki_csrf(): string { return (string)($_SESSION['csrf'] ?? ''); }
function wiki_csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(wiki_csrf()) . '">'; }
function wiki_check_csrf(): void {
    if (!hash_equals(wiki_csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Проверка безопасности формы не пройдена. Обновите страницу.');
    }
}
function wiki_admin(): bool { return !empty($_SESSION['wiki_admin']); }
function wiki_require_admin(): void {
    if (!wiki_admin()) { http_response_code(403); exit('Нет доступа'); }
}
function wiki_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type'=>$type, 'message'=>$message];
}
function wiki_take_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash;
}
function wiki_redirect(array $params = []): never {
    header('Location: ' . wiki_url($params), true, 303); exit;
}
function wiki_find(array $list, int|string $value, string $field='id'): ?array {
    foreach ($list as $item) if ((string)($item[$field] ?? '') === (string)$value) return $item;
    return null;
}
function wiki_category_articles(array $db, int $id, bool $admin=false): array {
    return array_values(array_filter($db['articles'], fn($a) => (int)$a['category_id'] === $id && ($admin || $a['status'] === 'published')));
}
function wiki_public_articles(array $db): array {
    $catIds = array_map('intval', array_column($db['categories'], 'id'));
    return array_values(array_filter($db['articles'], fn($a) => $a['status'] === 'published' && in_array((int)$a['category_id'], $catIds, true)));
}
function wiki_sort_articles(array &$articles, string $sort='recent'): void {
    usort($articles, match ($sort) {
        'alpha' => fn($a,$b)=>strnatcasecmp($a['title'], $b['title']),
        'popular' => fn($a,$b)=>($b['views'] <=> $a['views']) ?: strcmp($b['updated_at'], $a['updated_at']),
        default => fn($a,$b)=>strcmp($b['updated_at'], $a['updated_at'])
    });
}
function wiki_unique_slug(string $name, array $list, int $except=0): string {
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $upper = array_combine(preg_split('//u', 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ', -1, PREG_SPLIT_NO_EMPTY), array_keys($map));
    $name = strtr(strtr(strtolower($name), $upper), $map);
    $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';
    $name = trim(substr($name, 0, 75), '-');
    if ($name === '') $name = 'page';
    $slug = $name;
    for ($i=2; wiki_slug_exists($slug,$list,$except); $i++) $slug=$name . '-' . $i;
    return $slug;
}
function wiki_slug_exists(string $slug, array $items, int $except=0): bool {
    foreach ($items as $item) if (($item['slug'] ?? '') === $slug && (int)$item['id'] !== $except) return true;
    return false;
}
function wiki_next_id(array $list): int { return $list ? max(array_map('intval', array_column($list,'id'))) + 1 : 1; }
function wiki_search(array $db, string $query): array {
    $query = trim($query);
    if ($query === '') return [];
    $words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $res=[];
    foreach (wiki_public_articles($db) as $article) {
        $hay = $article['title'] . ' ' . $article['summary'] . ' ' . $article['content'];
        $ok = true; $score=0;
        foreach ($words as $word) {
            if (preg_match('/' . preg_quote($word,'/') . '/iu', $hay) !== 1) { $ok=false; break; }
            if (preg_match('/' . preg_quote($word,'/') . '/iu', $article['title'])) $score+=5;
            if (preg_match('/' . preg_quote($word,'/') . '/iu', $article['summary'])) $score+=2;
        }
        if ($ok) { $article['_score']=$score; $res[]=$article; }
    }
    usort($res, fn($a,$b)=>($b['_score']<=>$a['_score']) ?: strcmp($b['updated_at'],$a['updated_at']));
    return array_slice($res,0,80);
}
function wiki_inline(string $content): string {
    // Render only a small, explicitly allowed Markdown subset; raw HTML is always escaped.
    $escaped = e($content);
    $escaped = preg_replace_callback('~!\[([^\]]*)\]\(([^)]+)\)~u', function($m) {
        $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^assets/(?:uploads|img)/[a-zA-Z0-9._/-]+\.(?:png|jpe?g|webp|gif)$~', $url) || str_contains($url, '..')) return e($m[0]);
        return '<img loading="lazy" class="article-image" src="'.e($url).'" alt="'.e(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5,'UTF-8')).'">';
    }, $escaped) ?? $escaped;
    $escaped = preg_replace_callback('~\[([^\]\n]{1,160})\]\(([^)]+)\)~u', function($m) {
        $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^https?://[^\s<>"\']+$~i', $url) && !preg_match('~^mailto:[^\s<>"\']+$~i', $url) && !preg_match('~^\?(?:[a-z0-9_%=&-]+)$~i',$url)) return e($m[0]);
        return '<a href="'.e($url).'" rel="noopener noreferrer"'.(str_starts_with($url, 'http')?' target="_blank"':'').'>'.$m[1].'</a>';
    }, $escaped) ?? $escaped;
    $escaped = preg_replace('~\*\*(.+?)\*\*~us','<strong>$1</strong>',$escaped) ?? $escaped;
    $escaped = preg_replace('~(?<!\*)\*([^*\n]+)\*(?!\*)~u','<em>$1</em>',$escaped) ?? $escaped;
    $escaped = preg_replace('~`([^`\n]+)`~u','<code>$1</code>',$escaped) ?? $escaped;
    return $escaped;
}
function wiki_markdown(string $markdown): string {
    $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
    $html=''; $list=''; $inCode=false; $code=''; $paragraph=[];
    $flushP = function() use (&$html,&$paragraph) { if ($paragraph) { $html.='<p>'.wiki_inline(implode("\n",$paragraph)).'</p>'; $paragraph=[]; } };
    $closeList = function() use (&$html,&$list) { if ($list) { $html.='</'.$list.'>'; $list=''; } };
    foreach ($lines as $line) {
        $t = trim($line);
        if (preg_match('/^```/', $t)) {
            $flushP(); $closeList();
            if (!$inCode) { $inCode=true; $code=''; }
            else { $html.='<pre><code>'.e(rtrim($code,"\n")).'</code></pre>'; $inCode=false; }
            continue;
        }
        if ($inCode) { $code.=$line."\n"; continue; }
        if ($t==='') { $flushP(); $closeList(); continue; }
        if (preg_match('/^(#{1,3})\s+(.+)$/u',$line,$m)) {
            $flushP();$closeList();$n=strlen($m[1]);$html.='<h'.$n.'>'.wiki_inline($m[2]).'</h'.$n.'>';continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/u',$t,$m)) {
            $flushP(); if ($list!=='ul') { $closeList();$html.='<ul>';$list='ul'; } $html.='<li>'.wiki_inline($m[1]).'</li>'; continue;
        }
        if (preg_match('/^\d+\.\s+(.+)$/u',$t,$m)) {
            $flushP(); if ($list!=='ol') { $closeList();$html.='<ol>';$list='ol'; } $html.='<li>'.wiki_inline($m[1]).'</li>'; continue;
        }
        $closeList();
        if (preg_match('/^>\s*(.+)$/u',$t,$m)) { $flushP();$html.='<blockquote>'.wiki_inline($m[1]).'</blockquote>';continue; }
        if (preg_match('/^---+$/',$t)) { $flushP();$html.='<hr>';continue; }
        $paragraph[]=$line;
    }
    if ($inCode) $html.='<pre><code>'.e(rtrim($code,"\n")).'</code></pre>';
    $flushP();$closeList();
    return $html;
}
function wiki_excerpt(string $text, int $max=155): string {
    $text=trim(preg_replace('/\s+/u',' ',preg_replace('/[`#*\[\]()>_-]/u','',$text) ?? $text) ?? $text);
    if (wiki_length($text)<=$max) return $text;
    preg_match_all('/./us',$text,$m);
    return implode('',array_slice($m[0],0,$max)).'…';
}

// Shared IP-based lockout, in addition to the session-specific limit.
function wiki_login_limit(string $mode): bool {
    $file = WIKI_STORE_DIR.'/login-limits.json';
    $handle = fopen($file, 'c+');
    if (!$handle) return false;
    @chmod($file,0600);
    try {
        if (!flock($handle, LOCK_EX)) return false;
        rewind($handle);
        $data = json_decode(stream_get_contents($handle) ?: '{}', true);
        if (!is_array($data)) $data = [];
        $now = time();
        foreach ($data as $ip=>$entry) if (($now-(int)($entry['start']??0))>900) unset($data[$ip]);
        $key=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        $entry=$data[$key]??['start'=>$now,'count'=>0];
        if ($mode==='check') return (int)$entry['count']<8;
        if ($mode==='failure') {
            if ($now-(int)$entry['start']>900) $entry=['start'=>$now,'count'=>0];
            $entry['count']++;
            $data[$key]=$entry;
        } else if ($mode==='clear') unset($data[$key]);
        rewind($handle);
        if (!ftruncate($handle,0)) return false;
        if (fwrite($handle,json_encode($data,JSON_THROW_ON_ERROR))===false) return false;
        fflush($handle);
        @chmod($file,0600);
        return true;
    } finally { flock($handle,LOCK_UN);fclose($handle); }
}

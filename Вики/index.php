<?php
declare(strict_types=1);
require __DIR__.'/app/core.php';
require __DIR__.'/app/seeds.php';
require __DIR__.'/app/actions.php';

ini_set('display_errors','0');
error_reporting(E_ALL);
wiki_start_session();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data:; script-src \'self\'; style-src \'self\'; font-src \'self\'; object-src \'none\'; base-uri \'self\'; form-action \'self\'; frame-ancestors \'self\'');
header('Cache-Control: no-store');
try { $db=wiki_read(); }
catch (Throwable $e) { http_response_code(503);exit('Не удалось открыть базу знаний. Проверьте права папки storage/ и данные сайта.'); }
if (wiki_admin() && (!isset($db['admin']['password_hash']) || !hash_equals(hash('sha256',$db['admin']['password_hash']), (string)($_SESSION['wiki_auth_version']??'')))) {
    unset($_SESSION['wiki_admin'],$_SESSION['wiki_auth_version']);
}
if ($_SERVER['REQUEST_METHOD']==='POST') wiki_post_action($db);
$page=(string)($_GET['page']??'home');
if ($db===null || empty($db['admin'])) $page='setup';
if ($db!==null && !empty($db['settings']['maintenance']) && !wiki_admin() && !in_array($page,['login','setup'],true)) $page='maintenance';
$s=$db['settings']??['site_title'=>'SUNKORA WIKI','site_subtitle'=>'База знаний SUNKORA ROLEPLAY','vk_url'=>'https://vk.ru/sunkora_rp','telegram_url'=>'https://t.me/sunkora_rp'];
$flash=wiki_take_flash();

function wiki_header(string $title, string $description=''): void {
    global $s,$page,$flash;
    $name=(string)($s['site_title']??'SUNKORA WIKI');
    $subtitle=$description?:($s['site_subtitle']??'База знаний SUNKORA ROLEPLAY');
    ?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#10101c">
<meta name="description" content="<?=e(wiki_excerpt($subtitle,150))?>">
<meta property="og:type" content="website"><meta property="og:title" content="<?=e($title)?> — <?=e($name)?>">
<meta property="og:description" content="<?=e($subtitle)?>"><meta property="og:image" content="assets/img/sunkora-cover.jpg">
<title><?=e($title)?> — <?=e($name)?></title>
<link rel="icon" href="favicon.png"><link rel="stylesheet" href="assets/css/wiki.css">
<script src="assets/js/wiki.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<div class="site-wrap">
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?=e(wiki_url())?>" aria-label="SUNKORA WIKI — главная">
      <span class="brand-mark"><img src="assets/img/sunkora-avatar.jpg" alt=""></span>
      <span class="brand-text"><strong>SUNKORA <span>WIKI</span></strong><small>База знаний RolePlay</small></span>
    </a>
    <nav class="header-nav" aria-label="Основное меню">
      <a class="<?= $page==='home'?'active':'' ?>" href="<?=e(wiki_url())?>">Главная</a>
      <a class="<?= $page==='categories'?'active':'' ?>" href="<?=e(wiki_url(['page'=>'categories']))?>">Разделы</a>
      <a href="<?=e(wiki_url(['page'=>'search']))?>">Поиск</a>
      <?php if (!empty($s['site_url'])): ?><a href="<?=e($s['site_url'])?>" target="_blank" rel="noopener noreferrer">Сайт</a><?php endif; ?>
      <?php if (!empty($s['forum_url'])): ?><a href="<?=e($s['forum_url'])?>" target="_blank" rel="noopener noreferrer">Форум</a><?php endif; ?>
    </nav>
    <div class="header-actions">
      <?php if (wiki_admin()): ?><a class="header-admin" href="<?=e(wiki_url(['page'=>'admin']))?>">⚙ <span>Панель</span></a>
      <?php else: ?><a class="header-admin" href="<?=e(wiki_url(['page'=>'login']))?>" title="Администрирование">⚙</a><?php endif; ?>
      <button type="button" class="menu-toggle" data-menu-toggle aria-label="Открыть меню" aria-expanded="false">☰</button>
    </div>
  </div>
</header>
<?php if ($flash): ?><div class="container"><div class="notice <?=e($flash['type'])?>" role="alert"><?=e($flash['message'])?></div></div><?php endif; ?>
<main id="main" class="main-area">
<?php }
function wiki_footer(): void {
    global $s;
    ?>
</main>
<footer class="footer">
  <div class="container footer-main">
    <div class="footer-brand"><img src="assets/img/sunkora-avatar.jpg" alt=""><div><strong>SUNKORA <span>WIKI</span></strong><small><?=e($s['footer_text']??'База знаний SUNKORA ROLEPLAY')?></small></div></div>
    <div class="footer-links"><a href="<?=e(wiki_url())?>">Главная</a><a href="<?=e(wiki_url(['page'=>'categories']))?>">Категории</a>
    <?php if (!empty($s['vk_url'])): ?><a href="<?=e($s['vk_url'])?>" target="_blank" rel="noopener noreferrer">ВКонтакте</a><?php endif; ?>
    <?php if (!empty($s['telegram_url'])): ?><a href="<?=e($s['telegram_url'])?>" target="_blank" rel="noopener noreferrer">Telegram</a><?php endif; ?>
    </div>
  </div><div class="container footer-bottom"><span>© <?=date('Y')?> SUNKORA ROLEPLAY</span><span>Играй свою историю.</span></div>
</footer></div>
</body></html>
<?php }
function wiki_searchbox(string $query='', bool $hero=false): void { ?>
<form class="search-box <?=$hero?'search-box-hero':''?>" action="index.php" method="get" role="search">
  <input type="hidden" name="page" value="search"><span class="search-icon" aria-hidden="true">⌕</span>
  <label class="sr-only" for="search-<?=$hero?'hero':'page'?>">Поиск по базе знаний</label>
  <input id="search-<?=$hero?'hero':'page'?>" type="search" name="q" maxlength="100" value="<?=e($query)?>" placeholder="Найти статью, инструкцию или ответ…" autocomplete="off" required>
  <button type="submit">Найти <span aria-hidden="true">↗</span></button>
</form>
<?php }
function wiki_breadcrumbs(array $crumbs=[]): void { ?>
<nav class="breadcrumbs" aria-label="Навигация"><a href="<?=e(wiki_url())?>">⌂ Главная</a>
<?php foreach ($crumbs as $c): ?><span class="breadcrumb-sep">/</span><?php if (!empty($c['url'])): ?><a href="<?=e($c['url'])?>"><?=e($c['name'])?></a><?php else: ?><span><?=e($c['name'])?></span><?php endif; ?><?php endforeach; ?>
</nav>
<?php }
function wiki_article_list_item(array $a,array $db,bool $showCategory=false): void {
    $cat=wiki_find($db['categories'],$a['category_id']); ?>
<a class="article-row" href="<?=e(wiki_url(['page'=>'article','slug'=>$a['slug']]))?>"><span class="article-row-icon">▤</span><span class="article-row-main"><strong><?=e($a['title'])?></strong><small><?=e($a['summary']?:wiki_excerpt($a['content'],85))?></small></span><span class="article-row-meta"><?php if ($showCategory && $cat): ?><span><?=e($cat['name'])?></span><?php else: ?><span><?=e(wiki_date($a['updated_at']))?></span><?php endif; ?><b aria-hidden="true">→</b></span></a>
<?php }
function wiki_category_card(array $cat,array $db): void {
    $count=count(wiki_category_articles($db,$cat['id'])); ?>
<a class="category-card" href="<?=e(wiki_url(['page'=>'category','slug'=>$cat['slug']]))?>">
  <span class="category-emoji" aria-hidden="true"><?=e($cat['icon'])?></span><span class="category-card-body"><strong><?=e($cat['name'])?></strong><small><?=e($cat['description'])?></small><span class="category-count"><?=$count?> <?= $count===1?'статья':(in_array($count%10,[2,3,4],true)&&!in_array($count%100,[12,13,14],true)?'статьи':'статей') ?></span></span><span class="category-arrow" aria-hidden="true">↗</span>
</a>
<?php }
function wiki_sort_categories(array &$cats): void {usort($cats,fn($a,$b)=>($a['sort']<=>$b['sort']) ?: ($a['id']<=>$b['id']));}

if ($page==='setup') {
    wiki_header('Первоначальная установка'); ?>
<section class="container setup-container"><div class="setup-banner"><img src="assets/img/sunkora-avatar.jpg" alt="SUNKORA ROLEPLAY"><span>НАСТРОЙКА СИСТЕМЫ</span><h1>Добро пожаловать в SUNKORA WIKI</h1><p>Создайте учётную запись владельца. Категории и стартовые статьи будут созданы автоматически.</p></div>
<?php if ($db!==null && !empty($db['admin'])): ?><div class="panel"><p>Установка уже завершена.</p><a class="btn primary" href="<?=e(wiki_url(['page'=>'login']))?>">Войти в панель</a></div>
<?php else: ?>
<form class="panel form-grid setup-form" method="post" action="index.php">
<?=wiki_csrf_field()?><input type="hidden" name="action" value="setup">
<div class="form-tip">🔒 Ключ установки находится в файле <code>config/install-key.php</code> на вашем хостинге. Не сообщайте его посторонним.</div>
<label>Ключ установки <input required name="install_key" type="text" autocomplete="off" placeholder="Вставьте ключ из файла"></label>
<label>Имя администратора <input required name="name" minlength="2" maxlength="60" placeholder="Владелец SUNKORA"></label>
<label>Email администратора <input required name="email" type="email" maxlength="150" placeholder="admin@example.com"></label>
<div class="two-cols"><label>Пароль (минимум 12 символов) <input required name="password" type="password" minlength="12" autocomplete="new-password"></label><label>Повторите пароль <input required name="password_confirm" type="password" minlength="12" autocomplete="new-password"></label></div>
<button class="btn primary large" type="submit">Установить SUNKORA WIKI →</button></form>
<?php endif; ?></section><?php wiki_footer();exit;
}
if ($page==='login') {
    if (wiki_admin()) wiki_redirect(['page'=>'admin']);
    wiki_header('Вход в управление'); ?>
<section class="container auth-wrap"><div class="auth-graphic"><img src="assets/img/sunkora-avatar.jpg" alt=""><h1>Панель<br>управления</h1><p>Публикация статей, организация разделов и управление базой знаний SUNKORA ROLEPLAY.</p></div><form class="panel form-grid auth-panel" method="post" action="index.php"><h2>Вход для администрации</h2><p class="muted">Используйте аккаунт, созданный при установке.</p><?=wiki_csrf_field()?><input type="hidden" name="action" value="login"><label>Email <input required type="email" name="email" autocomplete="username"></label><label>Пароль <input required type="password" name="password" autocomplete="current-password"></label><button class="btn primary large" type="submit">Войти →</button><a class="text-link" href="<?=e(wiki_url())?>">← Вернуться к базе знаний</a></form></section><?php wiki_footer();exit;
}
if ($page==='maintenance') { http_response_code(503); header('Retry-After: 3600'); wiki_header('Технические работы'); ?>
<section class="container empty-state maintenance"><span>🛠️</span><h1>Мы скоро вернёмся</h1><p>В SUNKORA WIKI проходят технические работы. Новости публикуются в официальных каналах сообщества.</p><a class="btn primary" href="https://vk.ru/sunkora_rp" target="_blank" rel="noopener noreferrer">Наш ВКонтакте</a><a class="text-link" href="<?=e(wiki_url(['page'=>'login']))?>">Вход для администрации</a></section><?php wiki_footer();exit; }
if ($page==='admin') {wiki_admin_page($db);exit;}
if ($page==='home') {
    $categories=$db['categories'];wiki_sort_categories($categories);
    $all=wiki_public_articles($db);$recent=$all;wiki_sort_articles($recent);
    $featured=array_values(array_filter($all,fn($a)=>$a['featured']));wiki_sort_articles($featured,'popular');
    wiki_header('Главная'); ?>
<section class="hero" aria-labelledby="hero-heading"><div class="hero-image"></div><div class="container hero-content"><span class="hero-eyebrow"><span></span> ОФИЦИАЛЬНАЯ БАЗА ЗНАНИЙ ПРОЕКТА</span><h1 id="hero-heading"><?=e($s['hero_title'])?></h1><p><?=e($s['hero_description'])?></p><?php wiki_searchbox('',true); ?><div class="hero-chips"><span>Популярные запросы:</span><a href="<?=e(wiki_url(['page'=>'search','q'=>'начать играть']))?>">Начало игры</a><a href="<?=e(wiki_url(['page'=>'search','q'=>'подключиться']))?>">Подключение</a><a href="<?=e(wiki_url(['page'=>'search','q'=>'RP']))?>">RP-термины</a></div></div></section>
<div class="container homepage">
<div class="section-heading"><div><span class="eyebrow">НАВИГАЦИЯ</span><h2>Категории помощи</h2><p>Выберите интересующий раздел — мы собрали всё по темам.</p></div><a class="section-more" href="<?=e(wiki_url(['page'=>'categories']))?>">Все разделы →</a></div>
<div class="category-grid"><?php foreach ($categories as $cat) wiki_category_card($cat,$db); ?></div>
<div class="home-panels"><section class="panel popular-panel"><div class="panel-heading"><span class="eyebrow">ПОДБОРКА</span><h2>Популярные инструкции</h2></div><div class="article-list"><?php foreach (array_slice($featured,0,5) as $a) wiki_article_list_item($a,$db); ?></div></section>
<section class="panel recent-panel"><div class="panel-heading"><span class="eyebrow">АКТУАЛЬНОЕ</span><h2>Последние статьи</h2></div><div class="article-list"><?php foreach(array_slice($recent,0,5) as $a) wiki_article_list_item($a,$db); ?></div></section></div>
<section class="help-cta"><div class="help-cta-icon">💬</div><div><span class="eyebrow">ОСТАЛИСЬ ВОПРОСЫ?</span><h2>Мы рядом с сообществом</h2><p>Следите за объявлениями, задавайте вопросы и узнавайте об обновлениях SUNKORA ROLEPLAY.</p></div><div class="help-cta-actions"><a class="btn primary" href="<?=e($s['vk_url']?:'https://vk.ru/sunkora_rp')?>" target="_blank" rel="noopener noreferrer">ВКонтакте ↗</a><a class="btn ghost" href="<?=e($s['telegram_url']?:'https://t.me/sunkora_rp')?>" target="_blank" rel="noopener noreferrer">Telegram ↗</a></div></section>
</div><?php wiki_footer();exit;
}
if ($page==='categories') {
    $cats=$db['categories'];wiki_sort_categories($cats);
    wiki_header('Все категории');?><div class="container inner-page"><?php wiki_breadcrumbs([['name'=>'Категории']]); ?><div class="page-title"><span class="eyebrow">БИБЛИОТЕКА ЗНАНИЙ</span><h1>Все категории</h1><p>Инструкции, ответы на вопросы и помощь игрокам SUNKORA ROLEPLAY.</p></div><div class="category-grid"><?php foreach ($cats as $cat) wiki_category_card($cat,$db);?></div></div><?php wiki_footer();exit;
}
if ($page==='category') {
    $cat=wiki_find($db['categories'],(string)($_GET['slug']??''),'slug');
    if (!$cat) {wiki_404();exit;}
    $items=wiki_category_articles($db,$cat['id']);wiki_sort_articles($items,'alpha');
    wiki_header($cat['name'],$cat['description']);?><div class="container inner-page"><?php wiki_breadcrumbs([['name'=>'Категории','url'=>wiki_url(['page'=>'categories'])],['name'=>$cat['name']]]);?>
<div class="category-title"><span class="category-emoji big"><?=e($cat['icon'])?></span><div><span class="eyebrow">РАЗДЕЛ БАЗЫ ЗНАНИЙ</span><h1><?=e($cat['name'])?></h1><p><?=e($cat['description'])?></p><small><?=count($items)?> материалов</small></div></div>
<div class="content-layout"><section class="panel category-articles"><div class="panel-heading"><h2>Статьи раздела</h2><span class="count-pill"><?=count($items)?></span></div><div class="article-list"><?php foreach ($items as $a) wiki_article_list_item($a,$db);?></div><?php if (!$items): ?><div class="empty-simple">Статей пока нет. Скоро здесь появятся новые материалы.</div><?php endif;?></section><?php wiki_sidebar($db,$cat['id']);?></div></div><?php wiki_footer();exit;
}
if ($page==='article') {
    $slug=clean_text($_GET['slug']??'',100);
    $a=wiki_find($db['articles'],$slug,'slug');
    if (!$a || ($a['status']!=='published' && !wiki_admin())) {wiki_404();exit;}
    $cat=wiki_find($db['categories'],$a['category_id']);
    if (!$cat && !wiki_admin()) {wiki_404();exit;}
    // Count published article views; admin preview does not inflate statistics.
    if ($a['status']==='published' && !wiki_admin()) {
        wiki_change(function(&$data) use ($a) {foreach($data['articles'] as &$entry) if ($entry['id']===$a['id']) {$entry['views']++;break;} });
        $a['views']++;
    }
    wiki_header($a['title'],$a['summary']);?><div class="container inner-page article-page">
<?php wiki_breadcrumbs([['name'=>'Категории','url'=>wiki_url(['page'=>'categories'])],['name'=>$cat['name']??'Раздел','url'=>$cat?wiki_url(['page'=>'category','slug'=>$cat['slug']]):''],['name'=>$a['title']]]);?>
<div class="content-layout"><article class="panel article-panel">
<div class="article-topline"><span class="article-tag"><?=e($cat['icon']??'📄')?> <?=e($cat['name']??'Без категории')?></span><?php if ($a['status']==='draft'): ?><span class="status-pill draft">ЧЕРНОВИК</span><?php endif; ?><?php if (wiki_admin()): ?><a class="edit-float" href="<?=e(wiki_url(['page'=>'admin','tab'=>'articles','edit'=>$a['id']]))?>">✎ Редактировать</a><?php endif;?></div>
<h1><?=e($a['title'])?></h1><p class="article-summary"><?=e($a['summary'])?></p><div class="article-meta"><span>◷ Обновлено <?=e(wiki_date($a['updated_at']))?></span><span>◉ <?=number_format((int)$a['views'],0,',',' ')?> просмотров</span><button type="button" class="print-button" data-print>⎙ Печать</button></div>
<div class="article-body"><?=wiki_markdown($a['content'])?></div>
<?php if ($a['status']==='published'):?><div class="article-feedback"><div><strong>Была ли эта статья полезной?</strong><small>Ваш отзыв поможет улучшить базу знаний.</small></div><div class="feedback-buttons">
<?php if (empty($_SESSION['wiki_voted'][$a['id']])): foreach (['helpful'=>'👍 Да','unhelpful'=>'👎 Нет'] as $vote=>$label): ?><form method="post" action="index.php"><?=wiki_csrf_field()?><input type="hidden" name="action" value="feedback"><input type="hidden" name="article_id" value="<?=$a['id']?>"><input type="hidden" name="slug" value="<?=e($a['slug'])?>"><input type="hidden" name="vote" value="<?=$vote?>"><button type="submit" class="feedback-btn"><?=$label?></button></form><?php endforeach;else: ?><span class="muted">✓ Вы оценили материал</span><?php endif; ?></div></div><?php endif; ?>
</article><?php wiki_sidebar($db,$a['category_id'],$a['id']);?></div></div><?php wiki_footer();exit;
}
if ($page==='search') {
    $query=clean_text($_GET['q']??'',100);$query=wiki_excerpt($query,100);
    $results=wiki_search($db,$query);
    wiki_header('Поиск по базе знаний');?><div class="container inner-page search-page"><?php wiki_breadcrumbs([['name'=>'Поиск']]);?><div class="page-title"><span class="eyebrow">ПОИСК ПО SUNKORA WIKI</span><h1>Найдём ответ вместе</h1><p>Введите слово или вопрос — поиск проверит заголовки, описания и тексты статей.</p></div><?php wiki_searchbox($query); ?>
<section class="panel search-results"><div class="panel-heading"><h2><?=($query!==''?'Результаты: '.e($query):'Воспользуйтесь поиском')?></h2><?php if ($query!==''): ?><span class="count-pill"><?=count($results)?></span><?php endif;?></div><div class="article-list"><?php foreach($results as $a) wiki_article_list_item($a,$db,true); ?></div><?php if ($query!=='' && !$results): ?><div class="empty-simple">Ничего не найдено. Попробуйте другой запрос или просмотрите <a href="<?=e(wiki_url(['page'=>'categories']))?>">все разделы</a>.</div><?php endif;?></section></div><?php wiki_footer();exit;
}
wiki_404();

function wiki_sidebar(array $db, int $activeCat=0, int $exceptArticle=0): void {
    $cats=$db['categories'];wiki_sort_categories($cats);
    $related=wiki_category_articles($db,$activeCat);$related=array_values(array_filter($related,fn($a)=>$a['id']!==$exceptArticle));wiki_sort_articles($related,'popular'); ?>
<aside class="sidebar"><section class="sidebar-card"><span class="eyebrow">БЫСТРЫЙ ПЕРЕХОД</span><h3>Категории</h3><nav aria-label="Категории Wiki"><?php foreach($cats as $cat):?><a class="side-category <?=$cat['id']===$activeCat?'active':''?>" href="<?=e(wiki_url(['page'=>'category','slug'=>$cat['slug']]))?>"><span><?=e($cat['icon'])?></span><?=e($cat['name'])?><b>›</b></a><?php endforeach;?></nav></section>
<?php if ($related):?><section class="sidebar-card"><span class="eyebrow">ПО ТЕМЕ</span><h3>Другие статьи</h3><?php foreach(array_slice($related,0,4) as $a):?><a class="related-link" href="<?=e(wiki_url(['page'=>'article','slug'=>$a['slug']]))?>"><?=e($a['title'])?> <span>↗</span></a><?php endforeach;?></section><?php endif;?>
<section class="sidebar-help"><span>💬</span><h3>Не нашли ответ?</h3><p>Обратитесь в официальное сообщество SUNKORA ROLEPLAY.</p><a href="https://vk.ru/sunkora_rp" target="_blank" rel="noopener noreferrer">Написать в VK ↗</a></section>
</aside><?php }
function wiki_404(): void { http_response_code(404);wiki_header('Страница не найдена');?><section class="container empty-state"><span>404</span><h1>Страница не найдена</h1><p>Возможно, статья удалена, ещё не опубликована или ссылка содержит ошибку.</p><a class="btn primary" href="<?=e(wiki_url())?>">← На главную</a></section><?php wiki_footer(); }

function wiki_admin_page(array $db): void {
    wiki_require_admin();
    $tab=(string)($_GET['tab']??'overview');
    $tabs=['overview'=>'Обзор','articles'=>'Статьи','categories'=>'Категории','media'=>'Изображения','settings'=>'Настройки','security'=>'Безопасность','backup'=>'Резервная копия'];
    if (!isset($tabs[$tab])) $tab='overview';
    wiki_header('Администрирование'); ?>
<div class="container admin-page"><?php wiki_breadcrumbs([['name'=>'Панель управления']]);?><div class="admin-heading"><div><span class="eyebrow">SUNKORA CMS</span><h1>Управление Wiki</h1><p>Содержимое и настройки базы знаний проекта.</p></div><form method="post" action="index.php"><?=wiki_csrf_field()?><input type="hidden" name="action" value="logout"><button class="btn ghost" type="submit">Выйти из аккаунта</button></form></div>
<div class="admin-layout"><aside class="admin-sidebar"><div class="admin-profile"><span>⚙</span><div><strong><?=e($db['admin']['name'])?></strong><small>Администратор</small></div></div><nav aria-label="Панель управления"><?php foreach($tabs as $key=>$label):?><a class="<?=$tab===$key?'active':''?>" href="<?=e(wiki_url(['page'=>'admin','tab'=>$key]))?>"><?=e($label)?> <span>›</span></a><?php endforeach;?></nav><a class="admin-to-site" href="<?=e(wiki_url())?>">← Открыть Wiki</a></aside><section class="admin-content">
<?php if ($tab==='overview'):
    $published=count(wiki_public_articles($db));$drafts=count(array_filter($db['articles'],fn($a)=>$a['status']==='draft'));$views=array_sum(array_column($db['articles'],'views'));?>
<div class="admin-title"><h2>Обзор проекта</h2><p>Состояние вашей базы знаний.</p></div><div class="stats-grid"><div class="stat-card"><span>▤</span><strong><?=$published?></strong><small>Опубликовано статей</small></div><div class="stat-card"><span>◈</span><strong><?=count($db['categories'])?></strong><small>Категорий</small></div><div class="stat-card"><span>✎</span><strong><?=$drafts?></strong><small>Черновиков</small></div><div class="stat-card"><span>◉</span><strong><?=$views?></strong><small>Просмотров статей</small></div></div>
<div class="panel admin-section"><div class="admin-row-heading"><h3>Быстрые действия</h3></div><div class="quick-actions"><a class="btn primary" href="<?=e(wiki_url(['page'=>'admin','tab'=>'articles','new'=>1]))?>">+ Создать статью</a><a class="btn secondary" href="<?=e(wiki_url(['page'=>'admin','tab'=>'categories','new'=>1]))?>">+ Добавить категорию</a><a class="btn secondary" href="<?=e(wiki_url(['page'=>'admin','tab'=>'settings']))?>">⚙ Настройки</a></div></div>
<div class="panel admin-section"><h3>Последние изменения</h3><div class="admin-list"><?php $recent=$db['articles'];wiki_sort_articles($recent);foreach(array_slice($recent,0,7) as $a):?><div><span><strong><?=e($a['title'])?></strong><small><?=e(wiki_date($a['updated_at']))?></small></span><a href="<?=e(wiki_url(['page'=>'admin','tab'=>'articles','edit'=>$a['id']]))?>">Изменить →</a></div><?php endforeach;?></div></div>
<?php elseif ($tab==='articles'): $editId=(int)($_GET['edit']??0);$showForm=$editId>0||isset($_GET['new']);$edit=$editId?wiki_find($db['articles'],$editId):null;
    if ($editId && !$edit) { echo '<p>Статья не найдена.</p>'; $showForm=false; }
    if ($showForm): ?>
<div class="admin-title"><h2><?=$edit?'Редактирование статьи':'Новая статья'?></h2><p>Редактор поддерживает базовую разметку Markdown.</p></div>
<form class="panel form-grid admin-form" method="post" action="index.php"><?=wiki_csrf_field()?><input type="hidden" name="action" value="save_article"><input type="hidden" name="return_tab" value="articles"><input type="hidden" name="id" value="<?=e($edit['id']??0)?>">
<label>Заголовок статьи <input name="title" required minlength="3" maxlength="140" value="<?=e($edit['title']??'')?>" placeholder="Название инструкции"></label>
<div class="two-cols"><label>Категория <select name="category_id" required><option value="">Выберите раздел</option><?php $cats=$db['categories'];wiki_sort_categories($cats);foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($edit['category_id']??0)===$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></label><label>Статус <select name="status"><option value="draft" <?=($edit['status']??'draft')==='draft'?'selected':''?>>Черновик (не виден игрокам)</option><option value="published" <?=($edit['status']??'')==='published'?'selected':''?>>Опубликовано</option></select></label></div>
<label>Краткое описание <textarea name="summary" rows="2" maxlength="280" placeholder="Короткий текст для карточки статьи"><?=e($edit['summary']??'')?></textarea></label>
<label>Основной текст <span class="form-label-note">Markdown: # Заголовок, ## Подзаголовок, **жирный**, - список, [ссылка](https://...)</span><textarea id="article-editor" name="content" class="content-editor" required rows="17" placeholder="# Заголовок статьи&#10;&#10;Подробный ответ…"><?=e($edit['content']??'')?></textarea></label>
<label class="check-label"><input type="checkbox" name="featured" value="1" <?=!empty($edit['featured'])?'checked':''?>> Добавить в подборку популярных инструкций</label>
<div class="form-actions"><button class="btn primary" type="submit">Сохранить статью</button><a class="btn ghost" href="<?=e(wiki_url(['page'=>'admin','tab'=>'articles']))?>">Отмена</a><?php if ($edit && $edit['status']==='published'):?><a class="btn secondary" target="_blank" rel="noopener" href="<?=e(wiki_url(['page'=>'article','slug'=>$edit['slug']]))?>">Посмотреть ↗</a><?php endif;?></div>
</form>
<?php else: ?><div class="admin-title inline-heading"><div><h2>Статьи</h2><p>Публикации, черновики и редактирование материалов.</p></div><a class="btn primary" href="<?=e(wiki_url(['page'=>'admin','tab'=>'articles','new'=>1]))?>">+ Новая статья</a></div><div class="panel table-wrap"><table class="admin-table"><thead><tr><th>Статья</th><th>Категория</th><th>Статус</th><th>Обновлено</th><th>Действия</th></tr></thead><tbody><?php $articles=$db['articles'];wiki_sort_articles($articles);foreach($articles as $a): $cat=wiki_find($db['categories'],$a['category_id']);?><tr><td><strong><?=e($a['title'])?></strong><small><?=e(wiki_excerpt($a['summary'],65))?></small></td><td><?=e($cat['name']??'—')?></td><td><span class="status-pill <?=$a['status']==='published'?'published':'draft'?>"><?=$a['status']==='published'?'Опубликовано':'Черновик'?></span></td><td><?=e(wiki_date($a['updated_at']))?></td><td class="action-cell"><a href="<?=e(wiki_url(['page'=>'admin','tab'=>'articles','edit'=>$a['id']]))?>">Изменить</a><form method="post" action="index.php" data-confirm="Удалить статью без возможности отмены?"><?=wiki_csrf_field()?><input type="hidden" name="action" value="delete_article"><input type="hidden" name="return_tab" value="articles"><input type="hidden" name="id" value="<?=$a['id']?>"><button class="danger-link" type="submit">Удалить</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;
elseif ($tab==='categories'): $editId=(int)($_GET['edit']??0);$edit=$editId?wiki_find($db['categories'],$editId):null;$showForm=isset($_GET['new'])||$edit;
    if ($showForm): ?><div class="admin-title"><h2><?=$edit?'Редактировать категорию':'Новая категория'?></h2><p>Измените данные раздела и его порядок.</p></div><form class="panel form-grid admin-form" method="post" action="index.php"><?=wiki_csrf_field()?><input type="hidden" name="action" value="save_category"><input type="hidden" name="return_tab" value="categories"><input type="hidden" name="id" value="<?=e($edit['id']??0)?>"><label>Название <input required name="name" maxlength="80" value="<?=e($edit['name']??'')?>"></label><label>Описание <textarea name="description" maxlength="200" rows="3"><?=e($edit['description']??'')?></textarea></label><div class="two-cols"><label>Иконка (emoji) <input name="icon" maxlength="20" value="<?=e($edit['icon']??'📁')?>"></label><label>Порядок сортировки <input name="sort" type="number" min="-1000" max="1000" value="<?=e($edit['sort']??100)?>"></label></div><div class="form-actions"><button class="btn primary" type="submit">Сохранить категорию</button><a class="btn ghost" href="<?=e(wiki_url(['page'=>'admin','tab'=>'categories']))?>">Отмена</a></div></form>
<?php else: ?><div class="admin-title inline-heading"><div><h2>Категории</h2><p>Структура разделов SUNKORA WIKI.</p></div><a class="btn primary" href="<?=e(wiki_url(['page'=>'admin','tab'=>'categories','new'=>1]))?>">+ Новая категория</a></div><div class="panel admin-list categories-admin"><?php $cats=$db['categories'];wiki_sort_categories($cats);foreach($cats as $c):?><div><span class="category-admin-name"><span><?=e($c['icon'])?></span><strong><?=e($c['name'])?></strong><small><?=count(wiki_category_articles($db,$c['id'],true))?> статей</small></span><span class="action-cell"><a href="<?=e(wiki_url(['page'=>'admin','tab'=>'categories','edit'=>$c['id']]))?>">Изменить</a><form method="post" action="index.php" data-confirm="Удалить категорию? Удаление возможно, только если в ней нет статей."><?=wiki_csrf_field()?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="return_tab" value="categories"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="danger-link" type="submit">Удалить</button></form></span></div><?php endforeach;?></div><?php endif;
elseif ($tab==='media'): ?><div class="admin-title"><h2>Медиатека</h2><p>Загрузите JPEG, PNG или WebP до 5 МБ и вставьте код изображения в статью.</p></div><form class="panel form-grid admin-form upload-form" method="post" action="index.php" enctype="multipart/form-data"><?=wiki_csrf_field()?><input type="hidden" name="action" value="upload_media"><input type="hidden" name="return_tab" value="media"><label>Файл изображения <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></label><button class="btn primary" type="submit">Загрузить изображение</button></form>
<div class="media-grid"><?php foreach(array_reverse($db['media']) as $m): $code='![Изображение](assets/uploads/'.$m['filename'].')';?><div class="panel media-card"><img loading="lazy" src="<?=e('assets/uploads/'.$m['filename'])?>" alt="<?=e($m['title'])?>"><div class="media-info"><small><?=e($m['title'])?></small><input aria-label="Markdown-код изображения" type="text" readonly value="<?=e($code)?>" data-copy-source><button class="btn secondary" type="button" data-copy>Скопировать код</button><form method="post" action="index.php" data-confirm="Удалить изображение?"><?=wiki_csrf_field()?><input type="hidden" name="action" value="delete_media"><input type="hidden" name="return_tab" value="media"><input type="hidden" name="id" value="<?=$m['id']?>"><button class="danger-link" type="submit">Удалить</button></form></div></div><?php endforeach;?></div>
<?php elseif ($tab==='settings'): ?><div class="admin-title"><h2>Настройки Wiki</h2><p>Название, контакты, навигация и публичный режим.</p></div><form class="panel form-grid admin-form" method="post" action="index.php"><?=wiki_csrf_field()?><input type="hidden" name="action" value="save_settings"><input type="hidden" name="return_tab" value="settings"><div class="two-cols"><label>Название Wiki <input name="site_title" value="<?=e($db['settings']['site_title'])?>" maxlength="90"></label><label>Подзаголовок <input name="site_subtitle" value="<?=e($db['settings']['site_subtitle'])?>" maxlength="160"></label></div><label>Заголовок на главной <input name="hero_title" value="<?=e($db['settings']['hero_title'])?>" maxlength="120"></label><label>Описание на главной <textarea name="hero_description" rows="2" maxlength="240"><?=e($db['settings']['hero_description'])?></textarea></label><div class="two-cols"><label>Ссылка VK <input type="url" name="vk_url" value="<?=e($db['settings']['vk_url'])?>"></label><label>Ссылка Telegram <input type="url" name="telegram_url" value="<?=e($db['settings']['telegram_url'])?>"></label></div><div class="two-cols"><label>Адрес основного сайта (необязательно) <input type="url" name="site_url" placeholder="https://..." value="<?=e($db['settings']['site_url'])?>"></label><label>Адрес форума (необязательно) <input type="url" name="forum_url" placeholder="https://..." value="<?=e($db['settings']['forum_url'])?>"></label></div><label>Email поддержки (необязательно) <input name="support_email" type="email" value="<?=e($db['settings']['support_email'])?>"></label><label>Текст в подвале <input name="footer_text" value="<?=e($db['settings']['footer_text'])?>" maxlength="180"></label><label class="check-label"><input type="checkbox" name="maintenance" value="1" <?=!empty($db['settings']['maintenance'])?'checked':''?>> Технические работы: скрыть статьи от посетителей (вход администратора останется доступен)</label><button class="btn primary" type="submit">Сохранить настройки</button></form>
<?php elseif ($tab==='security'): ?><div class="admin-title"><h2>Безопасность</h2><p>Изменение пароля владельца и информация об учётной записи.</p></div><div class="panel admin-section"><strong><?=e($db['admin']['name'])?></strong><p class="muted"><?=e($db['admin']['email'])?></p></div><form class="panel form-grid admin-form" method="post" action="index.php"><?=wiki_csrf_field()?><input type="hidden" name="action" value="change_password"><input type="hidden" name="return_tab" value="security"><label>Текущий пароль <input type="password" name="old_password" autocomplete="current-password" required></label><label>Новый пароль (от 12 символов) <input type="password" name="new_password" autocomplete="new-password" minlength="12" required></label><label>Подтвердите новый пароль <input type="password" name="confirm_password" autocomplete="new-password" minlength="12" required></label><button class="btn primary" type="submit">Обновить пароль</button></form>
<?php elseif ($tab==='backup'): ?><div class="admin-title"><h2>Резервная копия</h2><p>Загрузите базу знаний в формате JSON для хранения вне хостинга.</p></div><div class="panel backup-panel"><span>🗂️</span><h3>Экспорт данных Wiki</h3><p>Копия содержит категории, статьи, настройки и хеш пароля администратора. Сохраните её в безопасном месте. <strong>Загруженные изображения нужно дополнительно скопировать из папки assets/uploads/.</strong></p><form method="post" action="index.php"><?=wiki_csrf_field()?><input type="hidden" name="action" value="backup"><button class="btn primary" type="submit">↓ Скачать JSON-резервную копию</button></form></div>
<div class="panel admin-section"><h3>Восстановление из резервной копии</h3><p>Восстановление заменит статьи, категории, настройки и медиатеку. <strong>Текущий пароль и аккаунт владельца сохранятся.</strong> Прежде чем начать, скачайте свежую копию выше и перенесите необходимые изображения в <code>assets/uploads/</code>.</p><form class="form-grid admin-form" method="post" action="index.php" enctype="multipart/form-data" data-confirm="Вы уверены? Текущие статьи, категории и настройки будут заменены содержимым архива."><?=wiki_csrf_field()?><input type="hidden" name="action" value="restore_backup"><input type="hidden" name="return_tab" value="backup"><label>JSON-резервная копия (до 6 МБ) <input type="file" name="backup_file" accept=".json,application/json" required></label><label>Текущий пароль администратора <input type="password" name="current_password" autocomplete="current-password" required></label><label>Введите ВОССТАНОВИТЬ <input type="text" name="restore_confirmation" autocomplete="off" required></label><p class="muted">Перед заменой база автоматически сохраняется в закрытой папке Wiki с именем <code>.wiki-before-restore-*.json</code>. Изображения восстанавливаются отдельно.</p><button class="btn danger-link" type="submit">Восстановить данные</button></form></div>
<?php endif;?></section></div></div><?php wiki_footer();
}

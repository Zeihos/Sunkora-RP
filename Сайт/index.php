<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: frame-ancestors 'self'");
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$isHttps=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';
if ($isHttps) header('Strict-Transport-Security: max-age=31536000');
session_name('SUNKORA_SESSION');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
session_start();
require __DIR__.'/app/core.php';
if ($_SERVER['REQUEST_METHOD']==='POST') require __DIR__.'/app/actions.php';
try { $state=store_read(); }
catch (Throwable $ex) { error_log('SUNKORA store: '.$ex->getMessage()); http_response_code(503); exit('Хранилище недоступно. Проверьте права на каталог data/.'); }
$config=array_replace((require __DIR__.'/config/site.php'),state_settings($state));
$config['servers']=$state['servers']; $config['news']=$state['news']; $config['downloads']=$state['downloads'];
$user=account($state);
if ($user && !empty($user['blocked'])) { unset($_SESSION['uid']); $user=null; flash('error','Доступ к аккаунту ограничен.'); }
$pages=[
 'home'=>'Главная','start'=>'Начать играть','servers'=>'Серверы','shop'=>'Магазин','news'=>'Новости','faq'=>'Помощь',
 'login'=>'Вход','register'=>'Регистрация','account'=>'Личный кабинет','admin'=>'Администрирование','setup'=>'Установка',
 'rules'=>'Правила','terms'=>'Условия использования','privacy'=>'Конфиденциальность',
];
$page=isset($_GET['page']) && is_string($_GET['page'])?$_GET['page']:'home';
if (!isset($pages[$page])) { http_response_code(404); $page='404'; }
if (!$state['installed'] && in_array($page,['login','register','account','admin'],true)) redirect('setup');
if ($state['installed'] && $page==='setup') redirect('home');
if (in_array($page,['account','admin'],true) && !$user) { flash('error','Войдите, чтобы продолжить.'); redirect('login'); }
if ($page==='admin' && ($user['role']??'')!=='admin') { http_response_code(403); $page='404'; }
if ($user && in_array($page,['login','register'],true)) redirect('account');
$title=$pages[$page]??'Страница не найдена';
$description=$page==='home'?$config['description']:($title.' — SUNKORA ROLEPLAY');
$serverList=$config['servers']; $newsList=$config['news']; $downloads=$config['downloads'];
usort($newsList,static fn($a,$b)=>strcmp((string)($b['date']??''),(string)($a['date']??'')));
require __DIR__.'/app/views.php';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10121b">
    <meta name="description" content="<?= e($description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SUNKORA ROLEPLAY">
    <meta property="og:title" content="<?= e($title . ' — SUNKORA ROLEPLAY') ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:image" content="assets/img/sunkora-cover.jpg">
    <title><?= e($title . ' — SUNKORA ROLEPLAY') ?></title>
    <link rel="icon" href="favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Unbounded:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <script src="assets/js/app.js?v=2" defer></script>
</head>
<body class="page-<?= e($page) ?>">
<div class="ambient ambient-one" aria-hidden="true"></div><div class="ambient ambient-two" aria-hidden="true"></div>
<header class="site-header" id="top">
 <div class="container header-inner">
  <a class="brand" href="./" aria-label="SUNKORA ROLEPLAY — главная">
   <img class="brand-icon" src="assets/img/sunkora-avatar.jpg" alt="" width="46" height="46">
   <span class="brand-text"><strong>SUNKORA</strong><small>ROLEPLAY</small></span>
  </a>
  <nav class="main-nav" id="site-nav" aria-label="Главное меню">
   <a class="nav-link<?= active('home',$page) ?>" href="./">Главная</a>
   <a class="nav-link<?= active('start',$page) ?>" href="<?= url('start') ?>">Как играть</a>
   <a class="nav-link<?= active('servers',$page) ?>" href="<?= url('servers') ?>">Серверы</a>
   <a class="nav-link<?= active('shop',$page) ?>" href="<?= url('shop') ?>">Магазин</a>
   <a class="nav-link<?= active('news',$page) ?>" href="<?= url('news') ?>">Новости</a>
   <a class="nav-link<?= active('faq',$page) ?>" href="<?= url('faq') ?>">Помощь</a>
   <a class="nav-link mobile-account" href="<?= url($user ? 'account' : 'login') ?>"><?= $user ? 'Кабинет' : 'Войти' ?></a>
  </nav>
  <div class="header-actions">
   <a class="header-social" href="<?= e($config['telegram']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Telegram"><?= icon('telegram') ?></a>
   <a class="btn btn-outline btn-small header-account" href="<?= url($user ? 'account' : 'login') ?>"><?= icon('user') ?> <?= $user ? e($user['username']) : 'Войти' ?></a>
   <a class="btn btn-primary btn-small header-join" href="<?= url('start') ?>">Играть <?= icon('arrow') ?></a>
   <button class="menu-toggle" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="Открыть меню"><?= icon('menu') ?></button>
  </div>
 </div>
</header>
<?php if (isset($_SESSION['flash'])): $notice=$_SESSION['flash'];unset($_SESSION['flash']); ?><div class="flash-wrap container" role="status"><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?><button type="button" aria-label="Закрыть уведомление" onclick="this.parentNode.remove()">×</button></div></div><?php endif; ?>
<?php if ($state['installed'] && ($config['announcement']??'')!=='' && $page==='home'): ?><div class="announcement"><div class="container"><span class="pulse"></span><span><?= e($config['announcement']) ?></span><a href="<?= url('news') ?>">НОВОСТИ <?= icon('arrow') ?></a></div></div><?php endif; ?>
<main>
<?php if ($page === 'home'): ?>
 <section class="hero container">
  <div class="hero-topline"><span class="pulse"></span> ОФИЦИАЛЬНОЕ СООБЩЕСТВО SUNKORA <span class="topline-streak"></span> ROLEPLAY WORLD</div>
  <div class="hero-grid">
   <div class="hero-copy">
    <div class="eyebrow">НОВАЯ ИСТОРИЯ НАЧИНАЕТСЯ ЗДЕСЬ <span>✦</span></div>
    <h1>ТВОЙ ГОРОД.<br>ТВОИ <span class="gradient-text">ПРАВИЛА.</span><br>ТВОЯ ИСТОРИЯ.</h1>
    <p>Открой для себя атмосферу большого города. Выбирай собственный путь, находи единомышленников и создавай моменты, которые запомнятся.</p>
    <div class="hero-buttons"><a class="btn btn-primary" href="<?= url('start') ?>"><?= icon('play') ?> НАЧАТЬ ИГРАТЬ</a><a class="btn btn-outline" href="<?= e($config['vk']) ?>" target="_blank" rel="noopener noreferrer">НАШЕ СООБЩЕСТВО <?= icon('up') ?></a></div>
    <div class="hero-caption"><span class="mini-line"></span> SUNKORA ROLEPLAY <span class="dot-separator">·</span> СОЗДАЙ СВОЮ ЛЕГЕНДУ</div>
   </div>
   <div class="hero-visual"><img src="assets/img/sunkora-avatar.jpg" alt="Фирменный логотип SUNKORA ROLEPLAY на фоне города" fetchpriority="high"><div class="visual-border"></div><div class="hero-visual-tag">EST. 2026 <span>✦</span> ROLEPLAY EXPERIENCE</div></div>
  </div>
  <div class="hero-banner"><img src="assets/img/sunkora-cover.jpg" alt="SUNKORA ROLEPLAY — закатный город с пальмами и автомобилем" loading="lazy"><div class="banner-frame"></div></div>
 </section>
 <section class="section container" id="about">
  <div class="section-head"><div><span class="kicker"><span class="kicker-dot"></span> ПРОЕКТ</span><h2>МИР, В КОТОРОМ<br><em>РЕШАЕШЬ ТЫ.</em></h2></div><p class="section-side">SUNKORA — пространство для твоих историй, встреч и новых впечатлений в атмосфере RolePlay.</p></div>
  <div class="feature-grid">
   <article class="feature-card"><div class="feature-number">01 <span> / 04</span></div><div class="feature-icon amber"><?= icon('compass') ?></div><h3>СВОЙ ПУТЬ</h3><p>Начни с чистого листа и решай сам, каким будет твой персонаж и его история.</p><div class="feature-line"></div></article>
   <article class="feature-card"><div class="feature-number">02 <span> / 04</span></div><div class="feature-icon violet"><?= icon('users') ?></div><h3>СООБЩЕСТВО</h3><p>Общайся, объединяйся с другими игроками и находи свою команду.</p><div class="feature-line"></div></article>
   <article class="feature-card"><div class="feature-number">03 <span> / 04</span></div><div class="feature-icon pink"><?= icon('spark') ?></div><h3>НОВЫЕ ВПЕЧАТЛЕНИЯ</h3><p>Исследуй игровой мир, создавай свои события и сохраняй яркие моменты.</p><div class="feature-line"></div></article>
   <article class="feature-card"><div class="feature-number">04 <span> / 04</span></div><div class="feature-icon blue"><?= icon('shield') ?></div><h3>ROLEPLAY</h3><p>Погрузись в атмосферу ролевой игры, где важны характер, решения и взаимодействие.</p><div class="feature-line"></div></article>
  </div>
 </section>
 <section class="section container start-promo"><div class="promo-light"></div><div class="promo-content"><span class="kicker">ТВОЯ ПЕРВАЯ ГЛАВА</span><h2>ГОТОВ <span class="gradient-text">НАЧАТЬ?</span></h2><p>Узнай, как присоединиться к SUNKORA ROLEPLAY, и следи за анонсами запуска.</p><a href="<?= url('start') ?>" class="btn btn-white">КАК НАЧАТЬ ИГРАТЬ <?= icon('arrow') ?></a></div><div class="promo-art"><img src="assets/img/sunkora-avatar.jpg" alt="" loading="lazy"></div></section>
 <section class="section container"><div class="section-head"><div><span class="kicker"><span class="kicker-dot"></span> МЕДИАЦЕНТР</span><h2>НОВОСТИ <em>ПРОЕКТА.</em></h2></div><a href="<?= url('news') ?>" class="btn btn-outline btn-small">ВСЕ НОВОСТИ <?= icon('arrow') ?></a></div><div class="news-grid"><?php foreach(array_slice($newsList,0,3) as $item): ?><article class="news-card"><div class="news-illustration"><img src="assets/img/sunkora-cover.jpg" alt="" loading="lazy"><span><?= e($item['category']??'Информация') ?></span></div><div class="news-body"><time datetime="<?= e($item['date']) ?>"><?= e(date_human($item['date'])) ?></time><h3><?= e($item['title']) ?></h3><p><?= e($item['text']) ?></p></div></article><?php endforeach; ?></div></section>
 <section class="section container community-section"><div class="section-head"><div><span class="kicker"><span class="kicker-dot"></span> БУДЬ В КУРСЕ</span><h2>ВСЕ НОВОСТИ<br><em>РЯДОМ С ТОБОЙ.</em></h2></div><p class="section-side">Публикации, анонсы и новости проекта появляются в наших официальных сообществах.</p></div><div class="social-grid"><a class="social-card vk" href="<?= e($config['vk']) ?>" target="_blank" rel="noopener noreferrer"><span class="social-symbol"><?= icon('vk') ?></span><span><small>ОФИЦИАЛЬНОЕ СООБЩЕСТВО</small><strong>ВКонтакте</strong><span>vk.ru/sunkora_rp</span></span><span class="social-arr"><?= icon('up') ?></span></a><a class="social-card tg" href="<?= e($config['telegram']) ?>" target="_blank" rel="noopener noreferrer"><span class="social-symbol"><?= icon('telegram') ?></span><span><small>НОВОСТИ И АНОНСЫ</small><strong>Telegram</strong><span>t.me/sunkora_rp</span></span><span class="social-arr"><?= icon('up') ?></span></a></div></section>
<?php elseif ($page === 'start'): ?>
 <section class="inner-hero container"><div class="kicker"><span class="kicker-dot"></span> ПУТЬ В SUNKORA</div><h1>НАЧАТЬ <span class="gradient-text">ИГРАТЬ.</span></h1><p>Три простых шага для знакомства с проектом. Здесь публикуются подтверждённые способы подключения.</p></section>
 <section class="container subpage-section"><div class="steps-grid">
  <article class="step-card"><span class="step-num">01</span><div class="step-symbol"><?= icon('users') ?></div><h2>ПРИСОЕДИНЯЙСЯ</h2><p>Подпишись на официальные сообщества, чтобы не пропустить анонсы и информацию о старте.</p><a href="<?= e($config['telegram']) ?>" target="_blank" rel="noopener noreferrer" class="text-link">Перейти в Telegram <?= icon('arrow') ?></a></article>
  <article class="step-card"><span class="step-num">02</span><div class="step-symbol"><?= icon('download') ?></div><h2>УСТАНОВИ КЛИЕНТ</h2><p>Когда будет опубликован официальный лаунчер, выбери подходящую платформу и установи игру.</p><a href="#download" class="text-link">Доступные загрузки <?= icon('arrow') ?></a></article>
  <article class="step-card"><span class="step-num">03</span><div class="step-symbol"><?= icon('play') ?></div><h2>ПОДКЛЮЧАЙСЯ</h2><p>Выбери игровой сервер и следуй опубликованной инструкции по подключению.</p><a href="<?= url('servers') ?>" class="text-link">Список серверов <?= icon('arrow') ?></a></article>
 </div><div class="content-panel" id="download"><div class="panel-top"><div><span class="kicker">ЗАГРУЗКИ</span><h2>ВЫБЕРИ <em>ПЛАТФОРМУ.</em></h2></div><div class="small-pill"><?= icon('download') ?> ОФИЦИАЛЬНЫЕ ССЫЛКИ</div></div>
 <?php if ($downloads): ?><div class="download-grid"><?php foreach($downloads as $item): if (!externalUrl($item['url'] ?? '')) continue; ?><a class="download-card" href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer"><div class="download-icon"><?= icon(($item['platform'] ?? '') === 'android' ? 'phone' : 'monitor') ?></div><div><strong><?= e($item['name'] ?? 'Скачать клиент') ?></strong><small><?= e($item['caption'] ?? 'Официальная загрузка') ?></small></div><?= icon('download') ?></a><?php endforeach; ?></div>
 <?php else: ?><div class="empty-state"><span class="empty-symbol"><?= icon('clock') ?></span><strong>ЗАГРУЗКИ ПОКА НЕ ОПУБЛИКОВАНЫ</strong><p>Ссылки на официальные клиенты будут добавлены после подтверждения. Не скачивай файлы из непроверенных источников.</p><a href="<?= e($config['vk']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline">СЛЕДИТЬ ЗА АНОНСАМИ <?= icon('up') ?></a></div><?php endif; ?></div></section>
<?php elseif ($page === 'servers'): ?>
 <section class="inner-hero container"><div class="kicker"><span class="kicker-dot"></span> СЕРВЕРЫ SUNKORA</div><h1>ИГРОВЫЕ <span class="gradient-text">МИРЫ.</span></h1><p>Здесь появятся адреса серверов и их актуальное состояние после подключения к мониторингу.</p></section>
 <section class="container subpage-section"><div class="panel-top"><div><span class="kicker">МОНИТОРИНГ</span><h2>СПИСОК <em>СЕРВЕРОВ.</em></h2></div><span class="small-pill"><?= icon('server') ?> SA-MP QUERY</span></div>
 <?php if ($serverList): ?><div class="servers-list"><?php foreach($serverList as $server):
    $host = (string)($server['host'] ?? ''); $port = (int)($server['port'] ?? 7777);
    $status = sampStatus($host, $port); $display = $host . ':' . $port;
 ?><article class="server-card"><div class="server-emblem"><?= icon('server') ?></div><div class="server-name"><h3><?= e($server['name'] ?? 'SUNKORA') ?></h3><span><span class="status-dot <?= $status ? 'online':'offline' ?>"></span><?= $status ? 'ДОСТУПЕН':'НЕТ ОТВЕТА' ?></span></div><div class="server-pop"><small>ИГРОКОВ ОНЛАЙН</small><strong><?= $status ? e((string)$status['players']).'<span> / '.e((string)$status['max']).'</span>' : '—' ?></strong></div><button class="btn btn-outline ip-copy" type="button" data-copy="<?= e($display) ?>"><?= icon('copy') ?><span><?= e($display) ?></span></button></article><?php endforeach; ?></div><p class="helper-note">Статус определяется запросом SA-MP по UDP. Если провайдер блокирует запросы, сервер может отображаться недоступным.</p>
 <?php else: ?><div class="empty-state large-empty"><span class="empty-symbol"><?= icon('server') ?></span><strong>АДРЕСА СЕРВЕРОВ ГОТОВЯТСЯ</strong><p>Проверенные адреса и мониторинг появятся после официального объявления. Следи за публикациями SUNKORA ROLEPLAY.</p><a href="<?= e($config['telegram']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">ПЕРЕЙТИ В TELEGRAM <?= icon('arrow') ?></a></div><?php endif; ?></section>
<?php elseif ($page === 'shop'): ?>
 <section class="inner-hero container"><div class="kicker"><span class="kicker-dot"></span> SUNKORA STORE</div><h1>ИГРОВОЙ <span class="gradient-text">МАГАЗИН.</span></h1><p>Отдельное пространство для будущих игровых товаров и привилегий. Оплата будет доступна только после подключения защищённого платёжного сервиса.</p></section>
 <section class="container subpage-section"><div class="shop-info"><span class="small-pill"><?= icon('shield') ?> БЕЗОПАСНОСТЬ</span><p>Товары публикует администрация. <?= externalUrl($config['shop_url'])?'Для перехода к оплате используется отдельный официальный магазин.':'Оплата на сайте отключена до подключения официального платёжного сервиса.' ?></p></div>
 <?php if ($state['products']): ?><div class="product-grid"><?php foreach($state['products'] as $product): ?><article class="product-card"><div class="product-art"><span><?= icon('gift') ?></span><small><?= e($product['category']?:'SUNKORA STORE') ?></small></div><div class="product-info"><h3><?= e($product['name']) ?></h3><p><?= e($product['description']) ?></p><div class="product-bottom"><strong><?= e($product['price_label']?:'Цена уточняется') ?></strong><?php if (externalUrl($config['shop_url'])): ?><a class="btn btn-primary btn-small" href="<?= e($config['shop_url']) ?>" target="_blank" rel="noopener noreferrer">В МАГАЗИН <?= icon('up') ?></a><?php else: ?><span class="unavailable">СКОРО</span><?php endif; ?></div></div></article><?php endforeach; ?></div>
 <?php else: ?><div class="shop-placeholder"><div class="shop-orbit orbit-one"></div><div class="shop-orbit orbit-two"></div><div class="shop-center"><?= icon('gift') ?></div><span class="kicker">SUNKORA STORE</span><h2>МАГАЗИН <span class="gradient-text">ГОТОВИТСЯ.</span></h2><p>Каталог товаров появится после публикации администрацией. Мы не принимаем переводы через этот сайт.</p><a href="<?= e($config['vk']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">СЛЕДИТЬ ЗА НОВОСТЯМИ <?= icon('up') ?></a></div><?php endif; ?></section>

<?php elseif ($page === 'news'): ?>
 <section class="inner-hero container"><div class="kicker"><span class="kicker-dot"></span> МЕДИАЦЕНТР SUNKORA</div><h1>НОВОСТИ <span class="gradient-text">ПРОЕКТА.</span></h1><p>Свежие объявления, события и новости SUNKORA ROLEPLAY.</p></section>
 <section class="container subpage-section"><div class="panel-top"><div><span class="kicker">НОВОСТНАЯ ЛЕНТА</span><h2>ПОСЛЕДНИЕ <em>ПУБЛИКАЦИИ.</em></h2></div></div>
 <?php if ($newsList): ?><div class="news-grid"><?php foreach($newsList as $item): ?><article class="news-card"><div class="news-illustration"><img src="assets/img/sunkora-cover.jpg" alt="" loading="lazy"><span><?= e($item['category'] ?? 'Новости') ?></span></div><div class="news-body"><time datetime="<?= e($item['date'] ?? '') ?>"><?= e(date_human($item['date']??'')) ?></time><h3><?= e($item['title'] ?? '') ?></h3><p><?= e($item['text'] ?? '') ?></p></div></article><?php endforeach; ?></div>
 <?php else: ?><div class="empty-state large-empty"><span class="empty-symbol"><?= icon('news') ?></span><strong>ПЕРВЫЕ НОВОСТИ ЕЩЁ ВПЕРЕДИ</strong><p>Как только появятся официальные публикации, они будут размещены здесь. Пока подписывайся на наши социальные сети.</p><a href="<?= e($config['telegram']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">НОВОСТИ В TELEGRAM <?= icon('arrow') ?></a></div><?php endif; ?></section>
<?php elseif ($page === 'faq'): ?>
 <section class="inner-hero container"><div class="kicker"><span class="kicker-dot"></span> ПОДДЕРЖКА</div><h1>ЧАСТЫЕ <span class="gradient-text">ВОПРОСЫ.</span></h1><p>Ответы на основные вопросы об игре, запуске и официальных каналах SUNKORA ROLEPLAY.</p></section>
 <section class="container subpage-section faq-layout"><div class="faq-main">
    <?php foreach($state['faq'] as $idx=>$faq): ?><details class="faq-item" <?= $idx===0?'open':'' ?>><summary><span><?= e(str_pad((string)($idx+1),2,'0',STR_PAD_LEFT)) ?>.</span><?= e($faq['question']) ?><span class="faq-chevron"><?= icon('chevron') ?></span></summary><div class="faq-answer"><?= nl2br(e($faq['answer'])) ?></div></details><?php endforeach; ?></div><aside class="faq-aside"><div class="faq-aside-logo"><img src="assets/img/sunkora-avatar.jpg" alt="SUNKORA ROLEPLAY" loading="lazy"></div><span class="kicker">НУЖНА ПОМОЩЬ?</span><h2>МЫ НА <em>СВЯЗИ.</em></h2><p>Следи за информацией и задавай вопросы через официальные каналы сообщества.</p><a href="<?= e($config['vk']) ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer">ВКОНТАКТЕ <?= icon('up') ?></a><a href="<?= e($config['telegram']) ?>" class="btn btn-outline" target="_blank" rel="noopener noreferrer">TELEGRAM <?= icon('up') ?></a></aside></section>
<?php elseif (in_array($page,['login','register','account','admin','setup','rules','privacy','terms'],true)): ?>
 <?php require __DIR__.'/app/sections.php'; ?>
<?php else: ?>
 <section class="inner-hero container"><span class="kicker">ОШИБКА 404</span><h1>СТРАНИЦА <span class="gradient-text">НЕ НАЙДЕНА.</span></h1><p>Проверь адрес или вернись на главную страницу.</p><a href="./" class="btn btn-primary">НА ГЛАВНУЮ <?= icon('arrow') ?></a></section>
<?php endif; ?>
</main>
<footer class="footer"><div class="container"><div class="footer-top"><div class="footer-identity"><a class="brand footer-brand" href="./"><img class="brand-icon" src="assets/img/sunkora-avatar.jpg" alt="" width="46" height="46"><span class="brand-text"><strong>SUNKORA</strong><small>ROLEPLAY</small></span></a><p>Твой город. Твои правила. Твоя история.</p></div><div class="footer-col"><strong>ПРОЕКТ</strong><a href="./">Главная</a><a href="<?= url('start') ?>">Начать играть</a><a href="<?= url('servers') ?>">Серверы</a><a href="<?= url('shop') ?>">Магазин</a></div><div class="footer-col"><strong>ИНФОРМАЦИЯ</strong><a href="<?= url('news') ?>">Новости</a><a href="<?= url('faq') ?>">Помощь</a><a href="<?= url('rules') ?>">Правила</a><a href="<?= url('privacy') ?>">Конфиденциальность</a><a href="<?= url('terms') ?>">Условия использования</a><a href="<?= e($config['vk']) ?>" target="_blank" rel="noopener noreferrer">ВКонтакте</a><a href="<?= e($config['telegram']) ?>" target="_blank" rel="noopener noreferrer">Telegram</a></div><div class="footer-col footer-cta"><strong>НА СВЯЗИ</strong><p>Присоединяйся к сообществу SUNKORA ROLEPLAY.</p><div class="footer-social"><a href="<?= e($config['vk']) ?>" target="_blank" rel="noopener noreferrer" aria-label="VK"><?= icon('vk') ?></a><a href="<?= e($config['telegram']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Telegram"><?= icon('telegram') ?></a></div></div></div><div class="footer-bottom"><span>© <?= date('Y') ?> SUNKORA ROLEPLAY.</span><span>СОЗДАВАЙ СВОЮ ИСТОРИЮ <span class="footer-star">✦</span></span><a href="#top">НАВЕРХ ↑</a></div></div></footer>
<div class="toast" role="status" aria-live="polite" id="toast"></div>
</body></html>

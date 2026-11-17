<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';
function ico(string $name,int $size=20): string {
 $paths=[
 'home'=>'<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1Z"/>',
 'message-square'=>'<rect x="3" y="3" width="18" height="15" rx="3"/><path d="m8 18-3 3v-3"/>',
 'messages-square'=>'<path d="M17 10h4v9l-3-3h-5M3 5h14v11H7l-4 4V5Z"/>',
 'megaphone'=>'<path d="m3 11 12-6v14L3 13v-2Zm12-4 4-2v14l-4-2M5 14l2 6h4l-2-5"/>',
 'book'=>'<path d="M12 7a8 8 0 0 0-9-2v15a8 8 0 0 1 9 2 8 8 0 0 1 9-2V5a8 8 0 0 0-9 2Zm0 0v15"/>',
 'circle-help'=>'<circle cx="12" cy="12" r="10"/><path d="M9.5 9a3 3 0 1 1 4 3c-1 .6-1.5 1.3-1.5 2.6M12 18h.01"/>',
 'server'=>'<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6h.01M7 17h.01M12 7h6M12 18h6"/>',
 'shield'=>'<path d="m12 2 9 4v6c0 5-4 9-9 10-5-1-9-5-9-10V6l9-4Z"/><path d="m8 12 3 3 5-6"/>',
 'shield-alert'=>'<path d="m12 2 9 4v6c0 5-4 9-9 10-5-1-9-5-9-10V6l9-4Z"/><path d="M12 7v6M12 17h.01"/>',
 'clipboard-list'=>'<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/>',
 'life-buoy'=>'<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><path d="m5 5 4 4m6 6 4 4m0-14-4 4m-6 6-4 4"/>',
 'lightbulb'=>'<path d="M9 18h6M9 21h6M8 15c-2-1-3-3-3-6a7 7 0 1 1 14 0c0 3-1 5-3 6l-1 3H9l-1-3Z"/>',
 'search'=>'<circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/>','user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21c0-5 3-8 8-8s8 3 8 8"/>',
 'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3c2 1 3 3 3 6"/>',
 'arrow-right'=>'<path d="M4 12h16m-7-7 7 7-7 7"/>','chevron-right'=>'<path d="m9 18 6-6-6-6"/>',
 'plus'=>'<path d="M12 5v14M5 12h14"/>','clock'=>'<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
 'pin'=>'<path d="m16 3 5 5-4 1-4 4v4l-3 3-2-7-5-2 3-3h4l4-4Z"/>','lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
 'bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
 'settings'=>'<circle cx="12" cy="12" r="3"/><path d="m5 5 2 2m10 10 2 2M12 2v3m0 14v3M2 12h3m14 0h3M5 19l2-2M17 7l2-2"/>',
 'log-out'=>'<path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M13 8l4 4-4 4m-9-4h13"/>',
 'edit'=>'<path d="M4 20h4L20 8l-4-4L4 16v4ZM14 6l4 4"/>',
 'trash'=>'<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v7m4-7v7"/>',
 'flag'=>'<path d="M4 22V4m0 1c6-4 10 4 16 0v12c-6 4-10-4-16 0"/>',
 'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
 'send'=>'<path d="m22 2-7 20-4-9-9-4 20-7ZM11 13 22 2"/>',
 'external-link'=>'<path d="M13 4h7v7M20 4 10 14M19 15v5H4V5h6"/>',
 'menu'=>'<path d="M3 6h18M3 12h18M3 18h18"/>',
 'check'=>'<path d="m4 12 5 5L20 6"/>',
 'star'=>'<path d="m12 2 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-6Z"/>',
 ];
 return '<svg class="i" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['message-square']).'</svg>';
}
function avatar(array $user,int $size=44): string {$initial=function_exists('mb_substr')?mb_substr($user['name']??'?',0,1,'UTF-8'):preg_replace('/^(.{1}).*$/us','$1',$user['name']??'?');return '<span class="avatar" style="--av-size:'.$size.'px">'.h(strtoupper($initial)).'</span>';}
function role_tag(array $u): string {$role=$u['role']??'user';$names=['admin'=>'Администратор','moderator'=>'Модератор','user'=>'Участник'];return '<span class="role role-'.h($role).'">'.h($names[$role]??'Участник').'</span>';}
function page_begin(string $title,array $state,?array $user,string $active='home'): void {
 $settings=$state['settings'];$unread=$user?count(array_filter($state['notifications'],fn($n)=>$n['to']===$user['id']&&!$n['read'])):0;
 header('Content-Type: text/html; charset=utf-8');
 ?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#11131c"><meta name="description" content="Официальный форум SUNKORA ROLEPLAY — новости, правила, игровые разделы, поддержка и сообщество."><title><?=h($title)?> — <?=h($settings['name'])?></title><link rel="icon" href="favicon.png"><link rel="stylesheet" href="assets/css/forum.css?v=1"></head><body>
 <div class="ambient ambient-one"></div><div class="ambient ambient-two"></div><div class="page-shell">
 <header class="topbar"><div class="container topbar-inner"><a href="<?=route()?>" class="brand"><span class="brand-symbol"><img src="assets/img/sunkora-avatar.jpg" alt="SUNKORA" loading="eager"></span><span class="brand-copy"><strong>SUNKORA</strong><small>ROLEPLAY FORUM</small></span></a>
 <button class="mobile-toggle" type="button" aria-label="Открыть навигацию" aria-expanded="false" data-toggle-nav><?=ico('menu',23)?></button>
 <nav class="main-nav" id="site-nav"><a class="nav-item <?=$active==='home'?'selected':''?>" href="<?=route()?>"><?=ico('home',17)?> Форум</a><a class="nav-item <?=$active==='search'?'selected':''?>" href="<?=route('search')?>"><?=ico('search',17)?> Поиск</a><?php if($settings['site_url']):?><a class="nav-item" href="<?=h($settings['site_url'])?>" target="_blank" rel="noopener noreferrer"><?=ico('external-link',17)?> Сайт</a><?php endif;?><a class="nav-item <?=$active==='members'?'selected':''?>" href="<?=route('members')?>"><?=ico('users',17)?> Участники</a><?php if($user):?><a class="nav-item <?=$active==='notifications'?'selected':''?>" href="<?=route('notifications')?>"><?=ico('bell',17)?> Уведомления<?php if($unread):?><span class="count-dot"><?=min($unread,99)?></span><?php endif;?></a><?php endif;?></nav>
 <div class="nav-actions"><?php if($user):?><a class="profile-pill" href="<?=route('profile',['u'=>$user['id']])?>"><?=avatar($user,30)?><span><?=h($user['name'])?></span></a><?php if(staff($user)):?><a href="<?=route(administrator($user)?'admin':'moderation')?>" class="icon-btn" title="Панель управления"><?=ico('settings')?></a><?php endif;?><?php else:?><a class="text-link" href="<?=route('login')?>">Войти</a><a class="btn btn-primary btn-small" href="<?=route('register')?>">Регистрация <?=ico('arrow-right',15)?></a><?php endif;?></div>
 </div></header>
 <main class="container content">
 <?php if(isset($_SESSION['notice'])):$note=$_SESSION['notice'];unset($_SESSION['notice']);?><div class="alert alert-<?=h($note['type'])?>"><?=ico('check',18)?> <?=h($note['message'])?></div><?php endif;?>
 <?php
}
function page_end(array $state,?array $user): void { $s=$state['settings']; ?>
 </main><footer class="footer"><div class="container footer-inner"><div class="footer-brand"><strong>SUNKORA<span> ROLEPLAY</span></strong><p>Твой город. Твоя история.</p></div><div class="footer-links"><a href="<?=route()?>">Форум</a><a href="<?=route('search')?>">Поиск</a><?php if($s['site_url']):?><a href="<?=h($s['site_url'])?>" target="_blank" rel="noopener noreferrer">Сайт проекта</a><?php endif;?><a href="<?=h($s['vk'])?>" target="_blank" rel="noopener noreferrer">ВКонтакте</a><a href="<?=h($s['tg'])?>" target="_blank" rel="noopener noreferrer">Telegram</a></div><small>© <?=date('Y')?> SUNKORA ROLEPLAY. Независимое сообщество.</small></div></footer></div><script src="assets/js/forum.js?v=1" defer></script></body></html>
 <?php }
function breadcrumb(array $items): void {echo '<nav class="breadcrumb"><a href="'.route().'">Главная</a>';foreach($items as $item){echo ico('chevron-right',14);if(is_array($item))echo '<a href="'.h($item[1]).'">'.h($item[0]).'</a>';else echo '<span>'.h($item).'</span>';}echo '</nav>';}
function stat_counts(array $s): array {return [count($s['topics']),count(array_filter($s['posts'],fn($p)=>!($p['deleted']??false))),count($s['users'])];}
function stats_cards(array $state): void {$stats=stat_counts($state);echo '<div class="stats-grid"><div class="stat"><div class="stat-icon">'.ico('message-square',22).'</div><div><strong>'.number_format($stats[0],0,',',' ').'</strong><span>Темы</span></div></div><div class="stat"><div class="stat-icon">'.ico('messages-square',22).'</div><div><strong>'.number_format($stats[1],0,',',' ').'</strong><span>Сообщения</span></div></div><div class="stat"><div class="stat-icon">'.ico('users',22).'</div><div><strong>'.number_format($stats[2],0,',',' ').'</strong><span>Участники</span></div></div></div>';}
function topic_row(array $state,array $topic,bool $showBoard=false): void {
 $author=$state['users'][$topic['author']]??['name'=>'Удалённый пользователь'];$board=$state['boards'][$topic['board']]??['title'=>'Удалённый раздел'];$posts=thread_posts($state,$topic['id']);$replies=max(0,count($posts)-1);
 ?><a class="topic-row" href="<?=route('topic',['t'=>$topic['id']])?>"><span class="topic-avatar"><?=avatar($author,40)?></span><span class="topic-main"><strong><?php if($topic['pinned']):?><span class="tiny-pin"><?=ico('pin',13)?></span><?php endif;?><?php if($topic['locked']):?><span class="tiny-pin muted"><?=ico('lock',13)?></span><?php endif;?><?=h($topic['title'])?></strong><small><?=h($author['name'])?> · <?=since($topic['created'])?><?php if($showBoard):?> · <?=h($board['title'])?><?php endif;?></small></span><span class="topic-replies"><strong><?=$replies?></strong><small>ответов</small></span><span class="topic-latest"><span><?=since($topic['updated'])?></span><small><?=h(username($state,$topic['last_author']))?></small></span><?=ico('chevron-right',16)?></a>
 <?php
}
function mini_sidebar(array $state,?array $user): void {$s=$state['settings'];$recent=array_slice(all_visible_topics($state,$user),0,5);?><aside class="sidebar">
 <div class="panel community-panel"><div class="panel-heading"><span class="eyebrow">НАШЕ СООБЩЕСТВО</span><h3>На связи 24/7</h3><p>Будь в курсе событий и анонсов проекта</p></div><div class="social-stack"><a href="<?=h($s['vk'])?>" class="social social-vk" target="_blank" rel="noopener noreferrer"><span>VK</span><strong>ВКонтакте</strong><?=ico('external-link',17)?></a><a href="<?=h($s['tg'])?>" class="social social-tg" target="_blank" rel="noopener noreferrer"><span>➤</span><strong>Telegram</strong><?=ico('external-link',17)?></a></div></div>
 <div class="panel"><div class="panel-title"><h3>Последняя активность</h3><?=ico('clock',18)?></div><div class="mini-topics"><?php if(!$recent):?><p class="muted-text">Тем пока нет. Начни обсуждение первым!</p><?php endif;foreach($recent as $t):?><a href="<?=route('topic',['t'=>$t['id']])?>"><span class="mini-dot"></span><span><strong><?=h($t['title'])?></strong><small><?=since($t['updated'])?></small></span></a><?php endforeach;?></div></div>
 <?php if($user):?><div class="panel"><div class="panel-title"><h3>Личный кабинет</h3><?=ico('user',18)?></div><div class="quick-links"><a href="<?=route('profile',['u'=>$user['id']])?>">Мой профиль <?=ico('arrow-right',16)?></a><a href="<?=route('settings')?>">Настройки <?=ico('arrow-right',16)?></a><?php if(staff($user)):?><a href="<?=route('moderation')?>">Жалобы <?=ico('arrow-right',16)?></a><?php endif;?></div></div><?php endif;?>
 </aside><?php }
function section_header(string $heading,string $subtitle='',string $action=''): void {echo '<div class="section-header"><div><h2>'.h($heading).'</h2>';if($subtitle)echo '<p>'.h($subtitle).'</p>';echo '</div>'.$action.'</div>';}
function button_post(string $label,string $action,string $class='btn btn-primary'): void {echo '<button class="'.h($class).'" type="submit">'.h($label).' '.ico('arrow-right',17).'</button>';}
function paginator(int $current,int $pages,array $query): void {
 if($pages<=1)return;echo '<div class="pagination">';for($p=max(1,$current-3);$p<=min($pages,$current+3);$p++){echo '<a class="'.($p===$current?'current':'').'" href="'.h(route((string)($query['p']??'home'),array_merge(array_diff_key($query,['p'=>true]),['page'=>$p]))).'">'.$p.'</a>';}echo '</div>';
}
function form_input(string $label,string $name,string $value='',string $type='text',int $max=255,bool $required=false): void {echo '<label class="field"><span>'.h($label).'</span><input name="'.h($name).'" type="'.h($type).'" value="'.h($value).'" maxlength="'.$max.'" '.($required?'required':'').'></label>';}
function form_textarea(string $label,string $name,string $value='',int $maxlength=12000,int $rows=5): void {echo '<label class="field"><span>'.h($label).'</span><textarea name="'.h($name).'" maxlength="'.$maxlength.'" rows="'.$rows.'">'.h($value).'</textarea></label>';}
function role_of(array $u): string {return ['admin'=>'Администратор','moderator'=>'Модератор','user'=>'Участник'][$u['role']??'user']??'Участник';}

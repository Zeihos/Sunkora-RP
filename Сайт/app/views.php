<?php
/* Included within index.php; variables $state, $user, $config, $page are defined. */
function form_fields(array $fields,array $row=[]): void {
 foreach ($fields as $name=>$spec) {
  [$label,$type]=$spec; $val=$row[$name]??($name==='date'?date('Y-m-d'):'');
  echo '<label class="field"><span>'.e($label).'</span>';
  if ($type==='textarea') echo '<textarea name="'.e($name).'" rows="4" maxlength="2500" required>'.e($val).'</textarea>';
  elseif ($type==='platform') { echo '<select name="platform">'; foreach(['windows'=>'Windows','android'=>'Android','other'=>'Другое'] as $v=>$l) echo '<option value="'.e($v).'"'.($val===$v?' selected':'').'>'.e($l).'</option>'; echo '</select>'; }
  else echo '<input name="'.e($name).'" type="'.e($type).'" value="'.e((string)$val).'" maxlength="'.($type==='number'?'5':'2500').'" required>';
  echo '</label>';
 }
}
function post_hidden(string $action,array $others=[]): void {
 echo csrf_input().'<input type="hidden" name="action" value="'.e($action).'">';
 foreach($others as $k=>$v) echo '<input type="hidden" name="'.e($k).'" value="'.e((string)$v).'">';
}
function ticket_render(array $ticket,array $users,bool $isAdmin): void {
 $id=$ticket['id']; $owner=''; foreach($users as $u) if ($u['id']===$ticket['user_id']) { $owner=$u['username']; break; }
 $status=['open'=>'Открыто','answered'=>'Ответ получен','closed'=>'Закрыто'][$ticket['status']]??'Открыто';
 ?>
 <details class="ticket-card" id="ticket-<?= e($id) ?>"><summary><span class="ticket-title"><?= e($ticket['title']) ?><small><?= e($owner) ?> · <?= e(date_human($ticket['created'])) ?></small></span><span class="status-label <?= e($ticket['status']) ?>"><?= e($status) ?></span><?= icon('chevron') ?></summary>
 <div class="ticket-thread"><?php foreach($ticket['messages'] as $msg): $who='Сотрудник'; foreach($users as $u) if ($u['id']===$msg['by']) { $who=$u['username']; break; } ?><div class="ticket-msg"><div><strong><?= e($who) ?></strong><time><?= e(date('d.m.Y H:i',strtotime($msg['at']))) ?></time></div><p><?= nl2br(e($msg['text'])) ?></p></div><?php endforeach; ?></div>
 <?php if ($ticket['status']!=='closed' || $isAdmin): ?><form method="post" class="ticket-reply"><?php post_hidden('ticket_reply',['id'=>$id]); ?><label class="field"><span>Ответить</span><textarea name="message" maxlength="2000" rows="3" minlength="2" required></textarea></label><button type="submit" class="btn btn-primary btn-small">ОТПРАВИТЬ <?= icon('arrow') ?></button></form><?php endif; ?>
 <form method="post" class="ticket-close"><?php post_hidden('ticket_close',['id'=>$id]); ?><button type="submit" class="text-link"><?= $ticket['status']==='closed'?'Открыть заново':'Закрыть обращение' ?></button></form>
 </details>
 <?php
}
function admin_editor(string $kind,array $items): void {
 $fields=[
  'news'=>['date'=>['Дата','date'],'category'=>['Категория','text'],'title'=>['Заголовок','text'],'text'=>['Текст','textarea']],
  'servers'=>['name'=>['Название сервера','text'],'host'=>['IP или домен','text'],'port'=>['Порт','number']],
  'downloads'=>['name'=>['Название','text'],'platform'=>['Платформа','platform'],'url'=>['Ссылка HTTPS','url'],'caption'=>['Подпись','text']],
  'faq'=>['question'=>['Вопрос','text'],'answer'=>['Ответ','textarea']],
  'products'=>['name'=>['Наименование','text'],'category'=>['Категория','text'],'description'=>['Описание','textarea'],'price_label'=>['Отображаемая цена','text']],
 ];
 $title=['news'=>'Новости','servers'=>'Серверы','downloads'=>'Загрузки','faq'=>'FAQ','products'=>'Каталог магазина'][$kind];
 ?>
 <div class="admin-section-head"><div><span class="kicker">РЕДАКТОР</span><h2><?= e($title) ?></h2></div><span class="small-pill"><?= count($items) ?> записей</span></div>
 <details class="admin-add" open><summary>+ ДОБАВИТЬ ЗАПИСЬ <?= icon('chevron') ?></summary><form class="admin-form" method="post"><?php post_hidden('admin_save',['kind'=>$kind]); ?><div class="form-grid"><?php form_fields($fields[$kind],$kind==='servers'?['port'=>7777]:[]); ?></div><button class="btn btn-primary btn-small" type="submit">СОХРАНИТЬ <?= icon('check') ?></button></form></details>
 <div class="admin-records"><?php foreach(array_reverse($items) as $item): ?><details class="admin-record"><summary><strong><?= e($item['title']??$item['name']??$item['question']??'Запись') ?></strong><span>РЕДАКТИРОВАТЬ <?= icon('chevron') ?></span></summary><form method="post" class="admin-form"><?php post_hidden('admin_save',['kind'=>$kind,'id'=>$item['id']]); ?><div class="form-grid"><?php form_fields($fields[$kind],$item); ?></div><button class="btn btn-primary btn-small" type="submit">СОХРАНИТЬ</button></form><form method="post" onsubmit="return confirm('Удалить эту запись без возможности восстановления?')" class="delete-form"><?php post_hidden('admin_delete',['kind'=>$kind,'id'=>$item['id']]); ?><button type="submit" class="danger-btn">Удалить запись</button></form></details><?php endforeach; ?></div>
 <?php if (!$items): ?><p class="muted">Записей пока нет. Добавь первую через форму выше.</p><?php endif; ?>
 <?php
}

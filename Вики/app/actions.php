<?php
declare(strict_types=1);

function wiki_post_action(?array $db): never {
    wiki_check_csrf();
    $action=(string)($_POST['action'] ?? '');
    try {
        if ($action === 'setup') {
            if ($db !== null && !empty($db['admin'])) throw new DomainException('Установка уже завершена.');
            $key=require WIKI_ROOT . '/config/install-key.php';
            if (!is_string($key) || !hash_equals($key,(string)($_POST['install_key']??''))) throw new DomainException('Неверный ключ установки.');
            $name=clean_text($_POST['name']??'',80);
            $email=trim(strtolower((string)($_POST['email']??'')));
            $password=(string)($_POST['password']??'');
            $password2=(string)($_POST['password_confirm']??'');
            if (wiki_length($name)<2 || wiki_length($name)>60) throw new DomainException('Имя: от 2 до 60 символов.');
            if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150) throw new DomainException('Введите корректную почту.');
            if (strlen($password)<12 || strlen($password)>200) throw new DomainException('Пароль должен содержать от 12 до 200 символов.');
            if (!hash_equals($password, $password2)) throw new DomainException('Пароли не совпадают.');
            wiki_change(function(&$data) use ($name,$email,$password) {
                if ($data !== null && !empty($data['admin'])) throw new DomainException('Установка уже завершена.');
                if ($data === null) $data=wiki_seed_data();
                $data['admin']=['email'=>$email,'name'=>$name,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'created_at'=>wiki_now()];
            });
            session_regenerate_id(true); $_SESSION['wiki_admin']=true; $_SESSION['wiki_auth_version']=hash('sha256',wiki_read()['admin']['password_hash']);
            wiki_flash('success','SUNKORA WIKI установлена. База знаний и первые статьи созданы.');
            wiki_redirect(['page'=>'admin']);
        }
        if ($db === null || empty($db['admin'])) throw new DomainException('Сначала выполните установку Wiki.');
        if ($action === 'login') {
            $fail=$_SESSION['wiki_login_fails'] ?? ['count'=>0,'from'=>time()];
            if (time()-(int)($fail['from']??0)>900) $fail=['count'=>0,'from'=>time()];
            if ((int)$fail['count']>=6 || !wiki_login_limit('check')) throw new DomainException('Слишком много попыток входа. Повторите через 15 минут.');
            $currentDb=wiki_read();
            if (!isset($currentDb['admin']['email'],$currentDb['admin']['password_hash'])) throw new DomainException('Сначала установите Wiki.');
            $mail=trim(strtolower((string)($_POST['email']??'')));
            $pass=(string)($_POST['password']??'');
            if (!hash_equals((string)$currentDb['admin']['email'], $mail) || !password_verify($pass,(string)$currentDb['admin']['password_hash'])) {
                $fail['count']++; $_SESSION['wiki_login_fails']=$fail; wiki_login_limit('failure');
                throw new DomainException('Неверная почта или пароль.');
            }
            unset($_SESSION['wiki_login_fails']); wiki_login_limit('clear');
            session_regenerate_id(true);$_SESSION['wiki_admin']=true;$_SESSION['wiki_auth_version']=hash('sha256',$currentDb['admin']['password_hash']);
            wiki_flash('success','Вы вошли в панель управления.');wiki_redirect(['page'=>'admin']);
        }
        if ($action === 'logout') {
            wiki_require_admin();
            unset($_SESSION['wiki_admin'],$_SESSION['wiki_auth_version']);
            session_regenerate_id(true);wiki_flash('success','Вы вышли из панели управления.');wiki_redirect();
        }
        if ($action === 'feedback') {
            $id=filter_var($_POST['article_id']??null,FILTER_VALIDATE_INT);
            $vote=(string)($_POST['vote']??'');
            if (!$id || !in_array($vote,['helpful','unhelpful'],true)) throw new DomainException('Некорректный отзыв.');
            if (!empty($_SESSION['wiki_voted'][$id])) throw new DomainException('Вы уже оценивали эту статью.');
            wiki_change(function(&$data) use ($id,$vote) {
                foreach ($data['articles'] as &$article) {
                    if ($article['id']===$id && $article['status']==='published') { $article[$vote]++; return; }
                }
                throw new DomainException('Статья не найдена.');
            });
            $_SESSION['wiki_voted'][$id]=$vote;
            wiki_flash('success','Спасибо за отзыв!');
            wiki_redirect(['page'=>'article','slug'=>clean_text($_POST['slug']??'',80)]);
        }
        wiki_require_admin();
        if ($action === 'save_category') {
            $id=(int)($_POST['id']??0);
            $name=clean_text($_POST['name']??'',100);
            $desc=clean_text($_POST['description']??'',260);
            $icon=clean_text($_POST['icon']??'📁',30);
            $sort=max(-1000,min(1000,(int)($_POST['sort']??100)));
            if (wiki_length($name)<2 || wiki_length($name)>80) throw new DomainException('Название категории: от 2 до 80 символов.');
            if (wiki_length($desc)>200) throw new DomainException('Описание слишком длинное.');
            if (wiki_length($icon)>8) throw new DomainException('Укажите один короткий значок или emoji.');
            wiki_admin_change(function(&$data) use ($id,$name,$desc,$icon,$sort) {
                if ($id) {
                    foreach ($data['categories'] as &$c) if ($c['id']===$id) {
                        $c['name']=$name; $c['description']=$desc; $c['icon']=$icon; $c['sort']=$sort; return;
                    }
                    throw new DomainException('Категория не найдена.');
                }
                $data['categories'][]=['id'=>wiki_next_id($data['categories']),'slug'=>wiki_unique_slug($name,$data['categories']),'name'=>$name,'description'=>$desc,'icon'=>$icon,'sort'=>$sort];
            });
            wiki_flash('success','Категория сохранена.'); wiki_redirect(['page'=>'admin','tab'=>'categories']);
        }
        if ($action === 'delete_category') {
            $id=(int)($_POST['id']??0);
            wiki_admin_change(function(&$data) use ($id) {
                foreach ($data['articles'] as $a) if ($a['category_id']===$id) throw new DomainException('Сначала перенесите или удалите статьи из категории.');
                if (!wiki_find($data['categories'],$id)) throw new DomainException('Категория не найдена.');
                $data['categories']=array_values(array_filter($data['categories'],fn($c)=>$c['id']!==$id));
            });
            wiki_flash('success','Категория удалена.');wiki_redirect(['page'=>'admin','tab'=>'categories']);
        }
        if ($action === 'save_article') {
            $id=(int)($_POST['id']??0);$cat=(int)($_POST['category_id']??0);
            $title=clean_text($_POST['title']??'',160);
            $summary=clean_text($_POST['summary']??'',300);
            $body=clean_text($_POST['content']??'',80000);
            $status=in_array(($_POST['status']??''),['published','draft'],true)?(string)$_POST['status']:'draft';
            $featured=isset($_POST['featured']);
            if (wiki_length($title)<3 || wiki_length($title)>140) throw new DomainException('Заголовок: 3–140 символов.');
            if (wiki_length($summary)>280) throw new DomainException('Краткое описание: не более 280 символов.');
            if (wiki_length($body)<10 || strlen($body)>320000) throw new DomainException('Текст статьи должен содержать не менее 10 символов.');
            wiki_admin_change(function(&$data) use ($id,$cat,$title,$summary,$body,$status,$featured) {
                if (!wiki_find($data['categories'],$cat)) throw new DomainException('Выберите существующую категорию.');
                if ($id) {
                    foreach ($data['articles'] as &$a) if ($a['id']===$id) {
                        $a['category_id']=$cat;$a['title']=$title;$a['summary']=$summary;$a['content']=$body;$a['status']=$status;$a['featured']=$featured;$a['updated_at']=wiki_now(); return;
                    }
                    throw new DomainException('Статья не найдена.');
                }
                $data['articles'][]=['id'=>wiki_next_id($data['articles']),'slug'=>wiki_unique_slug($title,$data['articles']), 'category_id'=>$cat,'title'=>$title,'summary'=>$summary,'content'=>$body,'status'=>$status,'featured'=>$featured,'views'=>0,'helpful'=>0,'unhelpful'=>0,'created_at'=>wiki_now(),'updated_at'=>wiki_now()];
            });
            wiki_flash('success','Статья сохранена.');wiki_redirect(['page'=>'admin','tab'=>'articles']);
        }
        if ($action === 'delete_article') {
            $id=(int)($_POST['id']??0);
            wiki_admin_change(function(&$data) use ($id) {
                if (!wiki_find($data['articles'],$id)) throw new DomainException('Статья не найдена.');
                $data['articles']=array_values(array_filter($data['articles'],fn($a)=>$a['id']!==$id));
            });
            wiki_flash('success','Статья удалена.');wiki_redirect(['page'=>'admin','tab'=>'articles']);
        }
        if ($action === 'save_settings') {
            $fields=['site_title'=>90,'site_subtitle'=>160,'hero_title'=>120,'hero_description'=>240,'footer_text'=>180];
            $values=[];
            foreach ($fields as $name=>$length) {
                $values[$name]=clean_text($_POST[$name]??'', $length);
                if (wiki_length($values[$name])>$length) throw new DomainException('Слишком длинное поле: '.$name);
            }
            foreach (['vk_url','telegram_url','site_url','forum_url'] as $name) {
                $raw=trim((string)($_POST[$name]??''));$values[$name]=safe_external_url($raw);
                if ($raw!=='' && $values[$name]==='') throw new DomainException('Ссылка должна начинаться с https://: '.$name);
            }
            $email=trim((string)($_POST['support_email']??''));
            if ($email!=='' && (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150)) throw new DomainException('Некорректная почта поддержки.');
            $values['support_email']=$email;$values['maintenance']=isset($_POST['maintenance']);
            wiki_admin_change(function(&$data) use ($values) { $data['settings']=array_merge($data['settings'],$values); });
            wiki_flash('success','Настройки сохранены.');wiki_redirect(['page'=>'admin','tab'=>'settings']);
        }
        if ($action === 'change_password') {
            $old=(string)($_POST['old_password']??'');$new=(string)($_POST['new_password']??'');
            if (strlen($new)<12 || strlen($new)>200) throw new DomainException('Новый пароль должен содержать 12–200 символов.');
            if (!hash_equals($new,(string)($_POST['confirm_password']??''))) throw new DomainException('Пароли не совпадают.');
            $hash=password_hash($new,PASSWORD_DEFAULT);
            wiki_admin_change(function(&$data) use ($old,$hash) {
                if (!password_verify($old,(string)($data['admin']['password_hash']??'')))
                    throw new DomainException('Текущий пароль изменился. Повторите операцию.');
                $data['admin']['password_hash']=$hash;
            });
            session_regenerate_id(true);$_SESSION['wiki_auth_version']=hash('sha256',$hash);wiki_flash('success','Пароль администратора изменён.');wiki_redirect(['page'=>'admin','tab'=>'security']);
        }
        if ($action === 'upload_media') {
            $f=$_FILES['image']??null;
            if (!is_array($f) || (int)($f['error']??99)!==UPLOAD_ERR_OK) throw new DomainException('Не удалось загрузить файл. Проверьте лимит PHP.');
            $tmp=(string)($f['tmp_name']??'');
            if (!is_uploaded_file($tmp)) throw new DomainException('Некорректная загрузка.');
            $ext=wiki_validate_image_file($tmp,(int)($f['size']??0));
            $filename=bin2hex(random_bytes(16)).'.'.$ext;
            $path=WIKI_ROOT.'/assets/uploads/'.$filename;
            if (!move_uploaded_file($tmp,$path)) throw new RuntimeException('Нет прав записи в assets/uploads/.');
            @chmod($path,0644);
            try {
                wiki_admin_change(function(&$data) use ($filename,$f) {
                    $data['media'][]=['id'=>wiki_next_id($data['media']),'filename'=>$filename,'title'=>clean_text(pathinfo((string)$f['name'],PATHINFO_FILENAME),100),'created_at'=>wiki_now()];
                });
            } catch(Throwable $ex) { @unlink($path); throw $ex; }
            wiki_flash('success','Изображение добавлено. Вставьте Markdown-код в статью.');wiki_redirect(['page'=>'admin','tab'=>'media']);
        }
        if ($action === 'delete_media') {
            $id=(int)($_POST['id']??0);
            $filename=wiki_admin_change(function(&$data) use ($id) {
                $found=wiki_find($data['media'],$id);
                if (!$found) throw new DomainException('Файл не найден.');
                foreach ($data['articles'] as $article)
                    if (str_contains((string)$article['content'],(string)$found['filename']))
                        throw new DomainException('Изображение используется в статье. Сначала удалите ссылку на него.');
                $data['media']=array_values(array_filter($data['media'],fn($m)=>$m['id']!==$id));
                return basename((string)$found['filename']);
            });
            @unlink(WIKI_ROOT.'/assets/uploads/'.$filename);
            wiki_flash('success','Изображение удалено.');wiki_redirect(['page'=>'admin','tab'=>'media']);
        }
        if ($action === 'restore_backup') {
            if (($_POST['restore_confirmation']??'')!=='ВОССТАНОВИТЬ')
                throw new DomainException('Введите слово ВОССТАНОВИТЬ для подтверждения.');
            $file=$_FILES['backup_file']??null;
            if (!is_array($file) || (int)($file['error']??99)!==UPLOAD_ERR_OK)
                throw new DomainException('Выберите JSON-файл резервной копии (до 6 МБ).');
            $tmp=(string)($file['tmp_name']??'');
            if (!is_uploaded_file($tmp))throw new DomainException('Некорректная загрузка резервной копии.');
            $actual=@filesize($tmp);
            if ($actual===false || $actual<100 || $actual>6*1024*1024 ||
                (int)($file['size']??0)!==$actual)
                throw new DomainException('Некорректный размер резервной копии (максимум 6 МБ).');
            $json=@file_get_contents($tmp);
            if (!is_string($json) || strlen($json)!==$actual)
                throw new DomainException('Не удалось полностью прочитать резервную копию.');
            $previous=wiki_restore_from_json($json,(string)($_POST['current_password']??''));
            wiki_flash('success','Данные восстановлены. Предыдущая база сохранена в закрытом хранилище: '.$previous);
            wiki_redirect(['page'=>'admin','tab'=>'backup']);
        }
        if ($action === 'backup') {
            $snapshot=wiki_read();
            wiki_assert_admin_state($snapshot);
            $json=json_encode($snapshot,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="sunkora-wiki-backup-'.gmdate('Y-m-d').'.json"');
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
            echo $json;exit;
        }
        throw new DomainException('Неизвестное действие.');
    } catch (DomainException $ex) {
        wiki_flash('error',$ex->getMessage());
        $tab=clean_text($_POST['return_tab']??'',30);
        if ($action==='setup') wiki_redirect(['page'=>'setup']);
        if ($action==='login') wiki_redirect(['page'=>'login']);
        if ($action==='feedback') wiki_redirect(['page'=>'article','slug'=>clean_text($_POST['slug']??'',80)]);
        wiki_redirect(['page'=>'admin','tab'=>$tab?:'overview']);
    }
}

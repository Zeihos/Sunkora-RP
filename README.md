<div align="center">

<img src="Оформление/cover.jpg" alt="SUNKORA ROLEPLAY — фирменная обложка проекта" width="100%">

<br><br>

<img src="Оформление/ava.jpg" alt="Логотип SUNKORA ROLEPLAY" width="108">

<h1>SUNKORA ROLEPLAY</h1>

<p><strong>Твоя история. Твой выбор. Твой RolePlay.</strong></p>

<p>Игровой RP-проект в экосистеме SA-MP / open.mp.<br>
Сайт, форум, база знаний и единый фирменный стиль.</p>

<p>
  <img src="https://img.shields.io/badge/статус-в%20разработке-F0A34C?style=flat-square" alt="Статус: в разработке">
  <img src="https://img.shields.io/badge/язык-русский-64748B?style=flat-square" alt="Русский язык">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&amp;logo=php&amp;logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/SA--MP%20%2F%20open.mp-планируется-8B5CF6?style=flat-square" alt="SA-MP / open.mp — планируется">
</p>

<p>
  <a href="https://github.com/Zeihos/Sunkora-RP/actions/workflows/php-checks.yml"><img src="https://github.com/Zeihos/Sunkora-RP/actions/workflows/php-checks.yml/badge.svg?branch=main" alt="Проверки PHP"></a>
  <a href="https://vk.ru/sunkora_rp"><img src="https://img.shields.io/badge/ВКонтакте-сообщество-0077FF?style=flat-square&amp;logo=vk&amp;logoColor=white" alt="ВКонтакте"></a>
  <a href="https://t.me/sunkora_rp"><img src="https://img.shields.io/badge/Telegram-новости-26A5E4?style=flat-square&amp;logo=telegram&amp;logoColor=white" alt="Telegram"></a>
</p>

<p><a href="#о-проекте"><strong>О проекте</strong></a> · <a href="#что-в-репозитории"><strong>Разделы</strong></a> · <a href="#как-запустить-веб-часть"><strong>Установка</strong></a> · <a href="#разработка-и-планы"><strong>Разработка</strong></a> · <a href="#участие-в-проекте"><strong>Участие</strong></a></p>

</div>

---

## О проекте

**SUNKORA ROLEPLAY** — проект ролевой игры, для которого разрабатывается собственная веб-экосистема: официальный сайт, форум сообщества, Wiki и оформление. Всё собрано в одном репозитории, чтобы поддерживать общий стиль и развивать компоненты независимо друг от друга.

> [!IMPORTANT]
> **Игровой сервер пока не опубликован в этом репозитории.** Здесь находятся исходники веб-части и графические материалы, но **нет** `sunkora.pwn` и готового игрового релиза. Успешные проверки PHP не означают, что мод SA-MP / open.mp уже собирается или запускается.

## Что в репозитории

| Компонент | Назначение | Текущий статус |
|:--|:--|:--|
| 🌐 **[Сайт](./Сайт/)** | Главная страница, новости, серверы, личный кабинет, административная часть | Исходники доступны · нужен тест на хостинге |
| 💬 **[Форум](./Форум/)** | Обсуждения, разделы сообщества и модерация | Исходники доступны · нужен тест на хостинге |
| 📚 **[Wiki](./Вики/)** | Статьи, справочники и управление базой знаний | Исходники доступны · нужен тест на хостинге |
| 🎨 **[Оформление](./Оформление/)** | Логотип, обложка, материалы публикаций | Материалы загружены |
| 🎮 **[Игровой сервер](./server/)** | Будущие исходники игрового режима Pawn | Исходники ещё не загружены |

Каждое PHP-приложение содержит собственные стили, изображения и файлы запуска. Повторяющиеся изображения в папках нужны, чтобы сайт, форум и Wiki можно было разворачивать по отдельности.

## Как запустить веб-часть

Для сайта, форума и Wiki нужен **PHP 8.1+**, HTTPS и настроенный Apache 2.4+ либо Nginx. Это три отдельных приложения — единой авторизации между ними пока нет.

<details>
<summary><strong>Открыть краткую инструкцию по установке</strong></summary>

1. Разместите каталоги `Сайт/`, `Форум/` и `Вики/` в **раздельных корнях сайтов**.
2. На хостинге задайте три **разных новых** ключа установки через переменные окружения:
   - `SUNKORA_SITE_INSTALL_KEY`
   - `SUNKORA_FORUM_INSTALL_KEY`
   - `SUNKORA_WIKI_INSTALL_KEY`
3. Генерируйте каждый ключ отдельно, например командой `openssl rand -hex 32`. Не сохраняйте значения в GitHub.
4. Настройте доступ PHP на запись к каталогам данных и запретите их скачивание по HTTP.
5. Перейдите к установке: сайт — `?page=setup`, форум — `?p=setup`, Wiki — `?page=setup`.

> **Безопасность:** старые установочные ключи когда-то были в истории Git. Перед использованием проекта их обязательно нужно заменить. Для Nginx потребуются отдельные правила защиты.

**Подробно:** [инструкция по развёртыванию](./docs/DEPLOYMENT.md) · [правила безопасности](./SECURITY.md).

</details>

## Разработка и планы

<table>
<tr>
<td valign="top" width="50%">

### Уже в репозитории

- PHP-сайт SUNKORA
- Форум сообщества
- База знаний Wiki
- Фирменные изображения
- Автоматические проверки PHP
- Документация по установке и безопасности

</td>
<td valign="top" width="50%">

### Следующие этапы

- Исходники игрового сервера Pawn
- Настоящая сборка `sunkora.amx`
- Запуск и тестирование с MySQL
- Проверка игровых механик
- Интеграция сервера с веб-частью
- Подготовка публичного релиза

</td>
</tr>
</table>

**[План разработки](./docs/ROADMAP.md)** · **[История изменений](./CHANGELOG.md)** · **[Проверки GitHub Actions](https://github.com/Zeihos/Sunkora-RP/actions)**

Для локальной проверки веб-части:

```bash
php tools/verify-install-keys.php
php tools/test-storage-security.php
php tools/verify-repository.php
```

## Участие в проекте

Заметили ошибку или хотите предложить улучшение? Для этого есть [Issues](https://github.com/Zeihos/Sunkora-RP/issues) и [Pull Requests](https://github.com/Zeihos/Sunkora-RP/pulls). Шаблоны обращений и [рекомендации по участию](./CONTRIBUTING.md) написаны по-русски.

**Пожалуйста, не публикуйте** в Issues пароли, токены, приватные базы данных и подробности уязвимостей. Об этом — в [SECURITY.md](./SECURITY.md).

---

<div align="center">

<img src="Оформление/ava.jpg" alt="SUNKORA" width="64">

<strong>SUNKORA ROLEPLAY</strong>

<sub>Создавай свою историю.</sub>

<br>

[ВКонтакте](https://vk.ru/sunkora_rp) · [Telegram](https://t.me/sunkora_rp)

<sub>Публичный доступ к исходникам не означает автоматического разрешения на их копирование и распространение. Отдельная лицензия пока не опубликована.</sub>

</div>

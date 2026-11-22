# SUNKORA — открытая основа Pawn

Собственный **минимальный режим для технического тестирования** SA-MP/open.mp. Это **не** прежний большой мод и **не релиз** SUNKORA ROLEPLAY.

Уже есть тестовый спавн в Los Santos, локальный чат в радиусе 25 м (с учётом интерьеров и виртуальных миров), команды `/help`, `/rules`, `/spawn`, `/stats`, `/me`, `/do`, `/b`.

## Сборка

Используется официальный [Pawn 3.10.11](https://github.com/openmultiplayer/compiler/releases/tag/v3.10.11):

```bash
export PAWNCC=/absolute/path/to/pawncc
bash server/BUILD-LINUX.sh
```

В GitHub Actions выберите [Pawn Compile](https://github.com/Zeihos/Sunkora-RP/actions/workflows/pawn-build.yml); успешный прогон создаёт артефакт `sunkora-amx` из `sunkora.amx` и `other.amx`.

## Ограничения

Нет авторизации, сохранения аккаунтов, MySQL, экономики, фракций, бизнеса, транспорта или административных систем. Сообщения внутри мода пока на английском из-за ограничений кодировок Pawn. Для реальных тестов необходим отдельный open.mp/SA-MP сервер с `gamemode0 sunkora 1`.

Компиляция подтверждает только сборку, не проверку работающей игры. Большие сторонние исходники нельзя автоматически выкладывать в публичный Git без проверки прав и секретов.

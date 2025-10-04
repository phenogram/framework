# Phenogram - феноменально простой фреймворк Telegram ботов

Это простой фреймворк для создания Telegram ботов любой сложности на PHP.

Главной мотивацией было написание инструмента для работы с API Telegram со строгой типизацией.

Все типы для API лежат в [соседнем проекте: bindings](https://github.com/phenogram/bindings) и 
могут быть использованы и без этого фреймворка. Их будет достаточно, если вы хотите отправлять запросы
и получать типизированные ответы.

Текущая поддерживаемая версия Telegram bot API - **v9.2.0**

В фреймворке же будет чуть больше качества жизни - роуты, мидвари, хэндлеры. Event loop для работы
в режиме long-polling.

Приоритет в разработке - простота использования и производительность.
Есть возможность писать асинхронные хэндлеры, так как под капотом работает amphp и файберы.

# Установка
```bash
composer require phenogram/framework
```

# Использование
## Простейший пример
```php
// bot.php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Phenogram\Framework\TelegramBot;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;

$token = getenv('TELEGRAM_BOT_TOKEN'); // Ваш токен, полученный у @BotFather например 7245389610:AAFHBDYMKpWxYu5JrSnTlQRD9bvPz0OgHkLf

$bot = new TelegramBot($token);
$bot->addHandler(fn (UpdateInterface $update, TelegramBot $bot) => $bot->api->sendMessage(
        chatId: $update->message->chat->id,
        text: $update->message->text
    ))
    ->supports(fn (UpdateInterface $update) => $update->message?->text !== null);

$bot->run();
```

Запускать через `php bot.php`

Вы ничего не увидите на экране, потому что по умолчанию логгер не настроен.
Но бот будет работать и отвечать на любые сообщения.

## Отправка локальных файлов
[Из документации](https://core.telegram.org/bots/api#sending-files) видно, что файлы можно отправлять 3 способами:
1. Указать URL
2. Указать ID файла
3. Загрузить локальный файл 

С отправкой через URL и ID файла всё просто - укажите эти параметры в запросе строками.
Загрузить локальный файл тоже просто, в фреймворке есть 3 способа это сделать:
1. Указать путь до файла в LocalFile
2. Передать (amphp поток)[https://amphp.org/byte-stream] в ReadableStreamFile
3. Отправить уже прочитанный файл в виде строки через BufferedFile 

Рассмотрим отправку файлов на примере [sendDocument](https://core.telegram.org/bots/api#senddocument).

```php
// bot.php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Phenogram\Framework\TelegramBot;
use Phenogram\Framework\Type\LocalFile;
use Phenogram\Framework\Type\ReadableStreamFile;
use Phenogram\Framework\Type\BufferedFile;

$token = getenv('TELEGRAM_BOT_TOKEN');
$bot = new TelegramBot($token);

$chatId = 123456789; // ID чата, куда отправляем файл

// 1. Отправка файла по локальному пути
$bot->api->sendDocument(
    chatId: $chatId,
    document: new LocalFile(
        filepath: '../../README.md'
//        // Необязательный параметр с именем файла. Если отсутствует, будет использовано имя файла из пути
//        filename: 'документация.md',
    ),
);

// 2. Отправка файла через поток
$bot->api->sendDocument(
    chatId: $chatId,
    document: new ReadableStreamFile(
        stream: \Amp\File\openFile('../../README.md', 'r'),
        filename: 'README.md', // Имя файла обязательно
    ),
);

// 3. Отправка файла через строку
$bot->api->sendDocument(
    chatId: $chatId,
    document: new BufferedFile(
        content: 'Здесь может быть ваша реклама',
        filename: 'README.md', // Имя файла обязательно
    ),
);
```


## TODO: дописать ридми и доку

А пока пример бота посложнее (на очень старой версии фреймворка) вы можете [найти здесь](https://github.com/shanginn/abdul-salesman-php)
или [пример посвежее](https://github.com/shanginn/wtf_happend_bot)
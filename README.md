# Phenogram - феноменально простой фреймворк Telegram ботов

> [!WARNING]  
> Проект находится в активной разработке и не рекомендуется к использованию в продакшене.

Это простой фреймворк для создания Telegram ботов любой сложности на PHP.

Главной мотивацией было написание инструмента для работы с API Telegram со строгой типизацией.

Все типы для API лежат в [соседнем проекте: bindings](https://github.com/phenogram/bindings) и 
могут быть использованы и без этого фреймворка. Их будет достаточно, если вы хотите отправлять запросы
и получать типизированные ответы.

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

$token = ''; // Ваш токен

$bot = new TelegramBot($token);
$bot->defineHandlers(function (Router $router) {
    $router
        ->add()
        ->handler(fn (Update $update, TelegramBot $bot) => $bot->api->sendMessage(
            chatId: $update->message->chat->id,
            text: $update->message->text
        ))
        ->supports(fn (Update $update) => $update->message?->text !== null);
});

$bot->run();
```

Запускать через `php bot.php`

Вы ничего не увидите на экране, потому что даже логгер не настроен.
Но бот будет работать и отвечать на любые сообщения.

## TODO: дописать ридми и доку

А пока пример бота посложнее (на очень старой версии фреймворка) вы можете [найти здесь](https://github.com/shanginn/abdul-salesman-php)
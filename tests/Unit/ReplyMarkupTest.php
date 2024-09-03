<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Unit;

use Phenogram\Bindings\Serializer;
use Phenogram\Bindings\Types\InlineKeyboardButton;
use Phenogram\Bindings\Types\InlineKeyboardMarkup;
use Phenogram\Framework\Tests\TestCase;

final class ReplyMarkupTest extends TestCase
{
    public function testInlineKeyboardMarkup()
    {
        $serializer = new Serializer();
        $inlineKeyboardMarkup = new InlineKeyboardMarkup(
            inlineKeyboard: [
                [
                    new InlineKeyboardButton(
                        text: 'Button 1',
                        callbackData: 'data1'
                    ),
                    new InlineKeyboardButton(
                        text: 'Button 2',
                        callbackData: 'data2'
                    ),
                ],
                [
                    new InlineKeyboardButton(
                        text: 'Button 3',
                        callbackData: 'data3'
                    ),
                ],
            ],
        );

        $arrayKeyboard = [
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Button 1', 'callback_data' => 'data1'],
                        ['text' => 'Button 2', 'callback_data' => 'data2'],
                    ],
                    [
                        ['text' => 'Button 3', 'callback_data' => 'data3'],
                    ],
                ],
            ],
        ];

        $json = $serializer->serialize([
            'reply_markup' => $inlineKeyboardMarkup,
        ]);

        $this->assertEquals(json_encode($arrayKeyboard), $json);
    }
}

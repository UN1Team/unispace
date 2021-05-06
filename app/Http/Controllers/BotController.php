<?php

namespace App\Http\Controllers;

require_once "vendor/autoload.php";

use DigitalStar\vk_api\vk_api; // Основной класс
use DigitalStar\vk_api\Coin; // работа с vkcoins
use DigitalStar\vk_api\LongPoll; //работа с longpoll
use DigitalStar\vk_api\Execute; // Поддержка Execute
use DigitalStar\vk_api\Group; // Работа с группами с ключем пользователя
use DigitalStar\vk_api\Auth; // Авторизация
use DigitalStar\vk_api\Post; // Конструктор постов
use DigitalStar\vk_api\Message; // Конструктор сообщений
use DigitalStar\vk_api\VkApiException; // Обработка ошибок

use Illuminate\Http\Request;

define("VK_KEY", "ee9d69070a3121558dc8e9f16b72c3fd10ea6aeaca5a954eccfc7e0e7ead0b8748eca9b9fbc6ed091ecac");
define("VERSION", "5.101");
define("CONFIRM_STR", "27ecbb00");

class BotController extends Controller
{
    public function Submit(){
        $vk = vk_api::create(VK_KEY, VERSION)->setConfirm(CONFIRM_STR);
        $vk->debug();
        $vk->initVars($id, $message, $payload); //инициализация переменных
        $info_btn = $vk->buttonText('Информация', 'blue', ['command' => 'info']); //создание кнопки
        if ($payload) {
            if($payload['command'] == 'info')
                $vk->reply('Тебя зовут %a_full%'); //отвечает пользователю или в беседу
        } else
            $vk->sendButton($id, 'Тестовая кнопка', [[$info_btn]]); //отправляем клавиатуру с сообщением
    }
}

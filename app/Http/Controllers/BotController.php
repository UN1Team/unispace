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
define("CONFIRM_STR", "0e589e51");

class BotController extends Controller
{
    public function Submit(){
        $vk = vk_api::create(VK_KEY, VERSION)->setConfirm(CONFIRM_STR);
        $vk->debug();
        $vk->initVars($id, $message, $payload, $user_id, $type, $data); //инициализация переменных
        if($data->object->action->type == 'chat_invite_user'){
            $vk->sendMessage($id, "//Вступительный текст для бесед");
        }
        if(DB::table('users')->where('vkid', $data['object']['from_id'])->exists()){
            $vk->sendMessage($id, "Ты уже находишься в нашей БД, поздравляю.");
        }
        else{
            $dbUser = DB::table('vkusers')->WHERE('vkid', $data['object']['from_id']);
            if($payload){
                if()
            }
            else if($dbUser->exists() && $dbUser->value('isFirstMessage') == false){
                $isFio_message = new Message($vk);
                $isFio_message->setMessage("Тебя зовут ".$message."?");
                $yesFio_button = $vk->buttonText('Да', 'green', ['command' => 'yesFio']);
                $noFio_button = $vk->buttonText('Нет', 'red', ['command' => 'noFio']);
                //$keyboard = $vk->generateKeyboard([[$yesFio_button], [$noFio_button], true])
                $isFio_message->setKeyboard([$yesFio_button, $noFio_button], true, true);
                $isFio_message->send($id);
            } else{
                $vk->sendMessage($id, "Привет!\n\nЯ твой персональный бот-помощник по учёбе.\nЯ буду тебя информировать о заданиях с их сроками сдачи и о том, что творится в тоей учебной жизни!\n\nХочешь узнать подробнее?\nНапиши своё Имя и Фамилию.");
                $dbUser->update(['isFirstMessage' => false]);
            }
        }
    }
}

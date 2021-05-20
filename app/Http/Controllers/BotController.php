<?php

namespace App\Http\Controllers;

require_once('simplevk-master/autoload.php');

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
use Illuminate\Support\Facades\DB;

define("VK_KEY", "ee9d69070a3121558dc8e9f16b72c3fd10ea6aeaca5a954eccfc7e0e7ead0b8748eca9b9fbc6ed091ecac");
define("VERSION", "5.101");
define("CONFIRM_STR", "665ae444");

class BotController extends Controller
{
    private $vk;
    private $id;
    private $message;
    private $payload;
    private $user_id;
    private $type;
    private $data;
    private $dbUser;

    public function Submit(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        $vk = vk_api::create(VK_KEY, VERSION)->setConfirm(CONFIRM_STR);
        $vk->debug();
        $vk->initVars($id, $message, $payload, $user_id, $type, $data); //инициализация переменных

        $dbUser = DB::table('vkusers')->WHERE('vkid', $data->object->from_id);

        if(isset($data->object->action->type) && $data->object->action->type == 'chat_invite_user'){
            $vk->sendMessage($id, "//Вступительный текст для бесед");
        }
        else if(DB::table('users')->where('vkid', $data->object->from_id)->exists()){
            $stage = $dbUser->value("stage");
            switch($stage){
                case "AddOrGet":
                    ProcessAddOrGetStage();
                    break;
                case "AddInfo":
                    ProcessAddInfoStage();
                    break;
                case "GetInfo":
                    ProcessGetInfoStage();
                    break;
            }
        }
        else
            FIOAuth();
    }

    private function ProcessAddInfoStage(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        $vk->sendMessage($id, 'Данный раздел ещё в разработке');
        $dbUser->update(['stage' => 'AddOrGet']);
        die();

        if($payload){
            if($command == 'CompleteTask'){
                $dbUser->update(['stage' => 'CompleteTask']);
            }
            else if ($command == 'NewTask'){
                $dbUser->update(['stage' => 'NewTask']);
            }
        } else
        {
            $completeTask_button = $vk->buttonText('Отметить сделанное задание', 'green', ['command' => 'CompleteTask']);
            $newTask_button = $vk->buttonText('Добавить в список новое задание', 'blue', ['command' => 'NewTask']);
            $vk->sendButton($id, "Давай решим, что ты хочешь сделать дальше", [[$completeTask_button], [$newTask_button]]);
        }
    }

    private function ProcessGetInfoStage(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        $tagsNames = DB::table('users')->where('vkid', $data->object->from_id)->value('tags');
        $tags = GetTagsByNames($tagsNames);

        if(is_numeric($message) && intval($message) <= count($tagsNames)){
            $tagsNumber = array();
            if(intval($message) == 0){
                for($i = 1; $i <= count($tagsNames); $i++)
                    array_push($tagsNumber, $i);
            }
            else
                array_push($tagNumber, intval($message));

            SendTasksByTagsNumber($tagsNumber, $tagsNames);
            $dbUser->update(['stage' => 'AddOrGet']);
        } 
        else {
            $answer = "Теперь давай решим, что ты хочешь узнать (просто введи цифру предмета):\n0 - Все";
            $counter = 1;
            foreach($tags as $tag){
                $answer .= "\n".$counter." - ".$tag->value("nameRU");
            }
            $vk->sendMessage($id, $answer);
        }
    }

    private function SendTasksByTagsNumber($tagsNumber, $tagsNames){
        global $vk;

        $dbTags = DB::table('tags');
        foreach($tagsNumber as $tagNumber){
            $tagName = $tagsNames[$tagNumber - 1];
            $tasksID = $dbTags->where('name', $tagName)->value('tasksID');
            foreach($tasksID as $taskID)
                $vk->sendMessage($id, TaskToString($taskID));
        }
    }

    private function TaskToString($taskID){
        $task = DB::table('tasks')->where('ID', $taskID)->first();
        return $task->title."\n\n".$task->description."\n\n".$task->deadline."\n\n".$task->link;
    }

    private function GetTagsByNames($tagsNames){
        $tags = array();
        $dbTags = DB::table('tags')->get();
        foreach($tagsNames as $tagName){
            array_push($tags, $dbTags->where('name', $tagName));
        }
    }

    private function ProcessAddOrGetStage(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        if($payload){
            if($command == 'AddInfo'){
                $dbUser->update(['stage' => "AddInfo"]);
                ProcessAddInfoStage();
            }
            else if ($command == 'GetInfo'){
                $dbUser->update(['stage' => "GetInfo"]);
                ProcessGetInfoStage();
            }
        }
        else {
            $add_button = $vk->buttonText('Занести новую информацию', 'blue', ['command' => 'AddInfo']);
            $get_button = $vk->buttonText('Узнать информацию о заданиях', 'blue', ['command' => 'GetInfo']);
            $vk->sendButton($id, "%fn%, что ты хочешь сделать?", [[$add_button], [$get_button]]);
        }
    }

    private function FIOAuth(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        if($payload){
            if($command == 'yesFio'){
                $currentFio = $dbUser->value('tempFio');
                if(CheckFIO($currentFio)){
                    $fio = explode(' ', $currentFio);
                    DB::table('users')->insert(['name' => $fio[0], 'last' => $fio[1], 'vkid' => $data->object->from_id]);
                    $dbUser->update(['stage' => "AddOrGet"]);
                    ProcessAddOrGetStage();
                }
                else
                    $vk->sendMessage($id, "Введи пожалуйста Имя и Фамилию правильно. Первым - имя, вторым фамилию, через пробел, русскими буквами.");
            }
            else
                $vk->sendMessage($id, "Введи свои Имя и Фамилию");
        }
        else if($dbUser->exists() && $dbUser->value('stage') == "Fio"){
            $yesFio_button = $vk->buttonText('Да', 'green', ['command' => 'yesFio']);
            $noFio_button = $vk->buttonText('Нет', 'red', ['command' => 'noFio']);
            $vk->sendButton($id, "Тебя зовут ".$message."?", [[$yesFio_button], [$noFio_button]]);
            $dbUser->update(['tempFio' => $message]);
        }
        else {
            $vk->sendMessage($id, "Привет!\n\nЯ твой персональный бот-помощник по учёбе.\nЯ буду тебя информировать о заданиях с их сроками сдачи и о том, что творится в тоей учебной жизни!\n\nХочешь узнать подробнее?\nНапиши своё Имя и Фамилию.");
            $dbUser->update(['stage' => "Fio"]);
        }
    }

    private function CheckFIO(string $fio){
        if(preg_match('/[^а-яА-Я\s]+/msi',$fio))
            return false;
        $fioarr = explode(" ", $fio);
        if(count($fioarr) != 2)
            return false;
        return true;
    }
}
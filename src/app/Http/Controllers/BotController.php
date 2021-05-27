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

        $logsTable = DB::table('logs');
        $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - submit started']);
        $vk = vk_api::create(VK_KEY, VERSION)->setConfirm(CONFIRM_STR);
        $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - vk created']);
        $vk->debug();
        $vk->initVars($id, $message, $payload, $user_id, $type, $data); //инициализация переменных
        $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - initVars']);

        $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - FROM_ID: '.$data->object->from_id]);
        $dbUser = DB::table('vkusers')->WHERE('vkid', $data->object->from_id);
        $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - get user from db']);

        if(isset($data->object->action->type) && $data->object->action->type == 'chat_invite_user'){
            $vk->sendMessage($id, "//Вступительный текст для бесед");
        }
        else if(DB::table('users')->where('vkid', $data->object->from_id)->exists()){
            $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - on stage chooser']);
            $stage = $dbUser->value("stage");
            switch($stage){
                case "AddOrGet":
                    $this->ProcessAddOrGetStage();
                    break;
                case "AddInfo":
                    $this->ProcessAddInfoStage();
                    break;
                case "GetInfo":
                    $this->ProcessGetInfoStage();
                    break;
                case "NewTask":
                    $this->ProcessNewTaskStage();
                    break;
                case "CompleteTask":
                    $this->ProcessCompleteTaskStage();
                    break;
                default:
                    $vk->sendMessage($id, "Произошла ошибка на сервере, пожалуйста повторите запрос");
                    $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - ERROR: in switch on stage chooser. Stage was \''.$stage.'\'']);
                    break;
            }
        }
        else{
            $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - go to FIOAuth()']);
            $this->FIOAuth();
        }
    }

    private function ProcessNewTaskStage(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;
        
        $tagsNames = DB::table('users')->where('vkid', $data->object->from_id)->value('tags');
        $tags = GetTagsByNames($tagsNames);

        if(is_numeric($message) && intval($message) <= count($tagsNames) && !CheckAddInfo($message)){
            $taskFields = explode('\n');
            if(count($taskFields) == 2){
                $id = DB::table('tasks')->insertGetId([
                    ['title' => $taskFields[0]],
                    ['description' => $taskFields[1]],
                    ['deadline' => $taskFields[2]]
                ]);
            }
            else
            {
                $id = DB::table('tasks')->insertGetId([
                    ['title' => $taskFields[0]],
                    ['description' => $taskFields[1]],
                    ['deadline' => $taskFields[2]],
                    ['link' => $taskFields[3]]
                ]);
            }
            $res = DB::table('tags')->where('name', $tagsNames[$message - 1])->value('tasksID');
            array_push($res, $id);
            DB::table('tags')->where('name', $tagsNames[$message - 1])->update(['tasksID' => $res]);
            $vk->sendMessage($id, 'Задание успешно добавлено!');
            $dbUser->update(['stage' => 'AddOrGet']);
            $this->ProcessAddOrGetStage();
        }
        else
        {
            $answer = "Введи номер предмета, по которому ты хочешь добавить задание, описание задания, срок сдачи (гггг-мм-дд) и ссылку (если есть) с разделением в виде переноса строки (shift+enter на ПК)";
            $counter = 1;
            foreach($tags as $tag){
                $answer .= "\n".$counter." - ".$tag->value("nameRU");
            }
            $vk->sendMessage($id, $answer);
        }
    }

    private function CheckAddInfo(string $info){
        //TODO: Сделать проверку даты либо её преобразование
        $rows = explode('\n', $info);
        return count($rows) >= 2 && count($rows) <= 3;
    }

    private function ProcessCompleteTaskStage(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        $tagsNames = DB::table('users')->where('vkid', $data->object->from_id)->value('tags');
        $tasks = array();
        foreach($tagsNames as $tagName)
            array_push($tasks, GetTasksByTag($tagName));

        if(is_numeric($message) && intval($message) <= count($tasks))
        {
            $res = DB::table('users')->where('vkid', $data->object->from_id)->value('completedTasksID');
            array_push($res, $message);
            DB::table('users')->where('vkid', $data->object->from_id)->update(['completedTasksID' => $res]);
            $vk->sendMessage('Задание выполнено, поздравляем!');
            $dbUser->update(['stage' => 'AddOrGet']);
            $this->ProcessAddOrGetStage();
        }
        else
        {
            $answer = "Введи номер задания:";
            $counter = 1;
            foreach($tasks as $task){
                $answer .= "\n".$counter." - ".$tasks;
            }
            $vk->sendMessage($id, $answer);
        }
    }

    private function ProcessAddInfoStage(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        if($payload){
            if($command == 'CompleteTask'){
                $dbUser->update(['stage' => 'CompleteTask']);
                $this->ProcessCompleteTaskStage();
            }
            else if ($command == 'NewTask'){
                $dbUser->update(['stage' => 'NewTask']);
                $this->ProcessNewTaskStage();
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

            $this->SendTasksByTagsNumberWithoutCompleted($tagsNumber, $tagsNames);
            $dbUser->update(['stage' => 'AddOrGet']);
            $this->ProcessAddOrGetStage();
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

    private function SendTasksByTagsNumberWithoutCompleted($tagsNumber, $tagsNames){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        //TODO: добавить проверку на completed в таблице users
        $dbTags = DB::table('tags');
        foreach($tagsNumber as $tagNumber){
            $tagName = $tagsNames[$tagNumber - 1];
            $tasksID = $dbTags->where('name', $tagName)->value('tasksID');
            foreach($tasksID as $taskID)
                $vk->sendMessage($id, TaskToString($taskID));
        }
    }

    private function GetTasksByTag(string $tag){
        $tasksID = DB::table('tags')->where('name', $tag)->value('tasksID');
        $result = array();
        foreach($tasksID as $taskID)
            array_push($result, TaskToString($taskID));
        return $tasksID;
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
        return $tags;
    }

    private function ProcessAddOrGetStage(){
        global $vk, $id, $message, $payload, $user_id, $type, $data, $dbUser;

        if($payload){
            if($command == 'AddInfo'){
                $dbUser->update(['stage' => "AddInfo"]);
                $this->ProcessAddInfoStage();
            }
            else if ($command == 'GetInfo'){
                $dbUser->update(['stage' => "GetInfo"]);
                $this->ProcessGetInfoStage();
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

        $logsTable = DB::table('logs');
        $logsTable->insert(['title' => 'Bot', date("m.d.y H:m:s").' - text' => 'FIOAuth()']);
        if($payload){
            if($command == 'yesFio'){
                $currentFio = $dbUser->value('tempFio');
                if(CheckFIO($currentFio)){
                    $fio = explode(' ', $currentFio);
                    DB::table('users')->insert(['name' => $fio[0], 'last' => $fio[1], 'vkid' => $data->object->from_id]);
                    $dbUser->update(['stage' => "AddOrGet"]);
                    $this->ProcessAddOrGetStage();
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
            $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - else statement before sendMessage in FIOAuth()']);
            $vk->sendMessage($id, "Привет!\n\nЯ твой персональный бот-помощник по учёбе.\nЯ буду тебя информировать о заданиях с их сроками сдачи и о том, что творится в тоей учебной жизни!\n\nХочешь узнать подробнее?\nНапиши своё Имя и Фамилию.");
            $logsTable->insert(['title' => 'Bot', 'text' => date("m.d.y H:m:s").' - message sent']);
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
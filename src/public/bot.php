<?php
require_once("../vendor/digitalstars/simplevk/autoload.php");
require_once("../lazyphp/db.php");
require_once("../lazyphp/log.php");

use DigitalStar\vk_api\vk_api; // Основной класс
use DigitalStar\vk_api\Coin; // работа с vkcoins
use DigitalStar\vk_api\LongPoll; //работа с longpoll
use DigitalStar\vk_api\Execute; // Поддержка Execute
use DigitalStar\vk_api\Group; // Работа с группами с ключем пользователя
use DigitalStar\vk_api\Auth; // Авторизация
use DigitalStar\vk_api\Post; // Конструктор постов
use DigitalStar\vk_api\Message; // Конструктор сообщений
use DigitalStar\vk_api\VkApiException; // Обработка ошибок
use Anemon\LazyPHP\DB;
use Anemon\LazyPHP\Log;

define("VK_KEY", "ee9d69070a3121558dc8e9f16b72c3fd10ea6aeaca5a954eccfc7e0e7ead0b8748eca9b9fbc6ed091ecac");
define("VERSION", "5.101");
define("CONFIRM_STR", "1d72b153");
define("BOT_DEBUG", true);

$vk;
$id;
$message;
$payload;
$user_id;
$type;
$data;
$db = new DB();
$usersTable = $db->Table("users");
$vkTable = $db->Table("vkusers");
Submit();

function Submit(){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    Log::Normal("Bot", "Submit started", null, "bot.log");
    $vk = vk_api::create(VK_KEY, VERSION)->setConfirm(CONFIRM_STR);
    Log::Normal("Bot", "vk created", null, "bot.log");
    $vk->debug();
    $vk->initVars($id, $message, $payload, $user_id, $type, $data);
    Log::Normal("Bot", "Vars initialised", null, "bot.log");
    if(isset($data->object->action->type) && $data->object->action->type == 'chat_invite_user'){
        $vk->sendMessage($id, "//Вступительный текст для бесед");
    }
    else if($usersTable->Select(['*'], ['vkid' => $data->object->from_id])->rowCount() > 0){
        Log::Normal("Bot", "On stage chooser", null, "bot.log");
        $stage = $vkTable->Select(['stage'], ['vkid' => $data->object->from_id])->fetchAll()[0]['stage'];
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
            case "NewTask":
                ProcessNewTaskStage();
                break;
            case "CompleteTask":
                ProcessCompleteTaskStage();
                break;
            default:
                Log::Warning("Bot", "Unknown stage - $stage", __FILE__."-".__LINE__." line");
                $vk->sendMessage($id, "Произошла ошибка на сервере, пожалуйста повторите запрос");
                break;
        }
    }
    else{
        FIOAuth();
    }
}

function ProcessNewTaskStage(){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    $tagsNames = $usersTable->Select(['*'], ['vkid' => $data->object->from_id]);
    $tagsNames = $db->FetchPDOStatement($tagsNames)[0]['tags'];
    $tags = GetTagsByNames($tagsNames);
    $taskFields = explode('\n', $message);
    if(is_numeric($taskFields[0]) && intval($taskFields[0]) <= count($tagsNames) && CheckAddInfo($message)){
        if(count($taskFields) == 3){
            $taskID = $db->InsertGetID('tasks', [
                'title' => $taskFields[0],
                'description' => $taskFields[1],
                'deadline' => $taskFields[2]
            ]);
        }
        else
        {
            $taskID = $db->InsertGetID('tasks', [
                'title' => $taskFields[0],
                'description' => $taskFields[1],
                'deadline' => $taskFields[2],
                'link' => $taskFields[3]
            ]);
        }
        $res = $db->Select('tags', ['tasksid'], ['name' => $tagsNames[$message - 1]]);
        $res = $db->FetchPDOStatement($res)[0]['tasksid'];
        array_push($res, $taskID);
        $db->Update('tags', ['tasksid' => $res], ['name' => $tagsNames[$message - 1]]);
        $vk->sendMessage($id, 'Задание успешно добавлено!');
        $vkTable->Update(['stage' => 'AddOrGet'], ['vkid' => $data->object->from_id]);
        ProcessAddOrGetStage();
    }
    else
    {
        $answer = "Введи номер предмета, по которому ты хочешь добавить задание, описание задания, срок сдачи (гггг-мм-дд) и ссылку (если есть) с разделением в виде переноса строки (shift+enter на ПК)";
        $counter = 1;
        foreach($tags as $tag){
            $answer .= "\n$counter - ".$tag["nameru"];
        }
        $vk->sendMessage($id, $answer);
    }
}

function CheckAddInfo(string $info){
    $rows = explode('\n', $info);
    //TODO: Сделать проверку даты либо её преобразование
    return count($rows) >= 3 && count($rows) <= 4;
}

function ProcessCompleteTaskStage(){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    $tagsNames = $usersTable->Select(['*'], ['vkid' => $data->object->from_id]);
    $tagsNames = $db->FetchPDOStatement($tagsNames)[0]['tags'];
    $tasks = array();
    foreach($tagsNames as $tagName)
        array_merge($tasks, GetTasksByTag($tagName));
    
    if(is_numeric($message) && intval($message) <= count($tasks))
    {
        $res = $usersTable->Select(['*'], ['vkid' => $data->object->from_id]);
        $res = $db->FetchPDOStatement($res)[0]['completedtasksid'];
        array_push($res, array_keys($tasks)[$message]);
        $usersTable->Update(['completedtasksid' => $res], ['vkid' => $data->object->from_id]);
        $vk->sendMessage('Задание выполнено, поздравляем!');
        $vkTable->Update(['stage' => 'AddOrGet'], ['vkid' => $data->object->from_id]);
        ProcessAddOrGetStage();
    }
    else
    {
        $answer = "Введи номер задания:";
        $counter = 1;
        foreach($tasks as $task){
            $answer .= "\n$counter - $task";
        }
        $vk->sendMessage($id, $answer);
    }
}

function ProcessAddInfoStage(){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    if($payload['command'] == 'CompleteTask'){
        $vkTable->Update(['stage' => 'CompleteTask'], ['vkid' => $data->object->from_id]);
        ProcessCompleteTaskStage();
    }
    else if ($payload['command'] == 'NewTask'){
        $vkTable->Update(['stage' => 'NewTask'], ['vkid' => $data->object->from_id]);
        ProcessNewTaskStage();
    } else
    {
        $completeTask_button = $vk->buttonText('Отметить сделанное задание', 'green', ['command' => 'CompleteTask']);
        $newTask_button = $vk->buttonText('Добавить в список новое задание', 'blue', ['command' => 'NewTask']);
        $vk->sendButton($id, "Давай решим, что ты хочешь сделать дальше", [[$completeTask_button], [$newTask_button]]);
    }
}

function ProcessGetInfoStage(){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    $tagsNames = $usersTable->Select(['*'], ['vkid' => $data->object->from_id]);
    $tagsNames = $db->FetchPDOStatement($tagsNames)[0]['tags'];
    $tags = GetTagsByNames($tagsNames);
    if(is_numeric($message) && intval($message) <= count($tagsNames)){
        $tagsNumber = array();
        if(intval($message) == 0){
            for($i = 1; $i <= count($tagsNames); $i++)
                array_push($tagsNumber, $i);
        }
        else
            array_push($tagNumber, intval($message));
        SendTasksByTagsNumberWithoutCompleted($tagsNumber, $tagsNames);
        $usersTable->Update(['stage' => 'AddOrGet'], ['vkid' => $data->object->from_id]);
        ProcessAddOrGetStage();
    }
    else {
        $answer = "Теперь давай решим, что ты хочешь узнать (просто введи цифру предмета):\n0 - Все";
        $counter = 1;
        foreach($tags as $tag){
            $answer .= "\n$counter - ".$tag["nameru"];
        }
        $vk->sendMessage($id, $answer);
    }
}

function SendTasksByTagsNumberWithoutCompleted($tagsNumber, $tagsNames){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    $completedTasksID = $usersTable->Select(['completedtasksid'], ['vkid' => $data->object->from_id])[0]['completedtasksid'];
    foreach($tagsNumber as $tagNumber){
        $tagName = $tagsNames[$tagNumber - 1];
        //$tasksID = $db->Select('tags', ['*'], "name='".$tagName."'")['tasksID'];
        $tasksID = $db->Select('tags', ['*'], ["name" => $tagName]);
        $tasksID = $db->FetchPDOStatement($tasksID)[0]['tasksid'];
        foreach($tasksID as $taskID){
            if(!in_array($taskID, $completedTasksID))
                $vk->sendMessage($id, TaskToString($taskID));
        }
    }
}

function GetTasksByTag(string $tag){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    $tasksID = $db->Select('tags', ['*'], ["name" => $tag]);
    $tasksID = $db->FetchPDOStatement($tasksID)[0]['tasksid'];
    $result = array();
    foreach($tasksID as $taskID)
        $result[$taskID] = TaskToString($taskID);
    return $tasksID;
}

function TaskToString($taskID){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    $task = $db->Select('tasks', ['*'], ['ID' => $taskID])->fetchAll()[0];
    return $task['title']."\n\n".$task['description']."\n\n".$task['deadline']."\n\n".$task['link'];
}

function GetTagsByNames($tagsNames){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    $tags = array();
    foreach($tagsNames as $tagName){
        //array_push($tags, $dbTags->where('name', $tagName));
        //array_push($tags, $db->Select('tags', ['*'], ['name' => $tagName])->fetchAll());
        array_push($tags, $db->FetchPDOStatement($db->Select('tags', ['*'], ['name' => $tagName])));
    }
    return $tags;
}

function ProcessAddOrGetStage(){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    Log::Normal("Bot", "Come in ProcessAddOrGetStage()", null, "bot.log");
    if($payload['command'] == 'AddInfo'){
        $vkTable->Update(['stage' => 'AddInfo'], ['vkid' => $data->object->from_id]);
        ProcessAddInfoStage();
    }
    else if ($payload['command'] == 'GetInfo'){
        $vkTable->Update(['stage' => 'GetInfo'], ['vkid' => $data->object->from_id]);
        ProcessGetInfoStage();
    }
    else {
        Log::Normal("Bot", "Asking, what next: Add or Get", null, "bot.log");
        $add_button = $vk->buttonText('Занести новую информацию', 'blue', ['command' => 'AddInfo']);
        $get_button = $vk->buttonText('Узнать информацию о заданиях', 'blue', ['command' => 'GetInfo']);
        $vk->sendButton($id, "%a_fn%, что ты хочешь сделать?", [[$add_button], [$get_button]]);
    }
}

function FIOAuth(){
    global $vk, $id, $message, $payload, $user_id, $type, $data, $db, $usersTable, $vkTable;

    Log::Normal("Bot", "Come in FIOAuth()", null, "bot.log");
    $user = $vkTable->Select(['*'], ["vkid" => $data->object->from_id])->fetchAll();
    if($payload){
        if($payload['command'] == 'yesFio'){
            $currentFio = $user[0]['tempfio'];
            if(CheckFIO($currentFio)){
                $fio = explode(' ', $currentFio);
                $usersTable->Insert(['name' => $fio[0], 'last' => $fio[1], 'vkid' => $data->object->from_id]);
                $vkTable->Update(['stage' => 'AddOrGet'], ['vkid' => $data->object->from_id]);
                Log::Normal("Bot", "Verified fio, created row in users table", null, "bot.log");
                ProcessAddOrGetStage();
            }
            else
                $vk->sendMessage($id, "Введи пожалуйста Имя и Фамилию правильно. Первым - имя, вторым фамилию, через пробел, русскими буквами.");
        }
        else
            $vk->sendMessage($id, "Введи свои Имя и Фамилию");
    }
    else if(count($user) > 0 && $user[0]['stage'] == "Fio"){
        $yesFio_button = $vk->buttonText('Да', 'green', ['command' => 'yesFio']);
        $noFio_button = $vk->buttonText('Нет', 'red', ['command' => 'noFio']);
        $vkTable->Update(['tempfio' => $message], ['vkid' => $data->object->from_id]);
        Log::Normal("Bot", "Sending button to verify true fio", null, "bot.log");
        $vk->sendButton($id, "Тебя зовут ".$message."?", [[$yesFio_button], [$noFio_button]]);
    }
    else {
        Log::Normal("Bot", "Sending hello message to new user", null, "bot.log");
        $vk->sendMessage($id, "Привет!\n\nЯ твой персональный бот-помощник по учёбе.\nЯ буду тебя информировать о заданиях с их сроками сдачи и о том, что творится в тоей учебной жизни!\n\nХочешь узнать подробнее?\nНапиши своё Имя и Фамилию.");
        $vkTable->Insert(['vkid' => $data->object->from_id, 'stage' => 'Fio']);
    }
}

function CheckFIO(string $fio){
    if(!preg_match('/[^а-яА-Я\s]+/msi',$fio))
        return false;
    $fioarr = explode(" ", $fio);
    if(count($fioarr) != 2)
        return false;
    return true;
}

function SendNotImplemented(){
    global $vk, $id, $data, $vkTable;
    $vk->sendMessage($id, "Этот раздел находится в разработке, попробуйте позже");
    $vkTable->Update(['stage' => 'AddOrGet'], ['vkid' => $data->object->from_id]);
}
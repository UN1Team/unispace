<?php
require_once("../vendor/digitalstars/simplevk/autoload.php");

use DigitalStar\vk_api\vk_api;

define("VK_KEY", "ee9d69070a3121558dc8e9f16b72c3fd10ea6aeaca5a954eccfc7e0e7ead0b8748eca9b9fbc6ed091ecac");
define("VERSION", "5.101");
define("CONFIRM_STR", "1d72b153");

$vk = vk_api::create(VK_KEY, VERSION)->setConfirm(CONFIRM_STR);
$vk->debug();
$vk->initVars($id, $message, $payload);
$info_btn = $vk->buttonText('Продолжить', 'blue', ['command' => 'info']);
if ($payload) {
    if($payload['command'] == 'info')
        $vk->reply('К сожалению, бот сейчас находится в разработке и не может ответить тебе(');
}
else{
    $vk->sendButton($id, "Привет!\n\nЯ твой персональный бот-помощник по учёбе.\nЯ буду тебя информировать о заданиях с их сроками сдачи и о том, что творится в тоей учебной жизни!\n\nХочешь узнать подробнее?\nНажми на кнопку ниже.", [[$info_btn]]);
    //$vk->sendMessage($id, "Привет!\n\nЯ твой персональный бот-помощник по учёбе.\nЯ буду тебя информировать о заданиях с их сроками сдачи и о том, что творится в тоей учебной жизни!\n\nХочешь узнать подробнее?\nНажми на кнопку ниже.");
}
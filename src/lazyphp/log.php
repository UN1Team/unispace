<?php

namespace Anemon\LazyPHP;

require_once('config.php');

class Log {
    public static function Error(string $title, string $description, string $location = null){
        $text = Log::GetLogText("ERROR", $title, $description, $location);
        Log::Log("error.log", $text);
        Log::Log("all.log", $text);
    }

    public static function Warning(string $title, string $description, string $location = null){
        $text = Log::GetLogText("WARNING", $title, $description, $location);
        Log::Log("warn.log", $text);
        Log::Log("all.log", $text);
    }
    
    public static function Normal(string $title, string $description, string $location = null){
        $text = Log::GetLogText("OK", $title, $description, $location);
        Log::Log("all.log", $text);
    }

    protected static function GetLogText(string $type, string $title, string $description, $location){
        $date = date('d-m-Y_h');
        $type = '['.$type.']';
        $location = !empty($location) ? '{'.$location.'}' : '';
        return $date.$type.$location.$title.": ".$description;
    }

    protected static function Log(string $path, string $text){
        if(!LOG_ANY)
            return;
        $file = fopen(LOG_PATH.$path, "a");
        fputs($file, $text.PHP_EOL);
        fclose($file);
    }
}
<?php

namespace Anemon\LazyPHP;

require_once('config.php');

class Log {

    /**
     * Log error message in file
     * 
     * @param string $title Title of this log. This message will log in "all.log" anyway.
     * @param string $description Description of this log
     * @param string $location Location, where this function was called
     * @param string $path Custom path to log file, "error.log" by default
     */
    public static function Error(string $title, string $description, string $location = null, string $path = "error.log"){
        $text = Log::GetLogText("ERROR", $title, $description, $location);
        Log::Log($path, $text);
        Log::Log("all.log", $text);
    }

    /**
     * Log warning message in file. This message will log in "all.log" anyway.
     * 
     * @param string $title Title of this log
     * @param string $description Description of this log
     * @param string $location Location, where this function was called
     * @param string $path Custom path to log file, "warn.log" by default
     */
    public static function Warning(string $title, string $description, string $location = null, string $path = "warn.log"){
        $text = Log::GetLogText("WARNING", $title, $description, $location);
        Log::Log($path, $text);
        Log::Log("all.log", $text);
    }
    
    /**
     * Log message in file.
     * 
     * @param string $title Title of this log
     * @param string $description Description of this log
     * @param string $location Location, where this function was called
     * @param string $path Custom path to log file, "all.log" by default
     */
    public static function Normal(string $title, string $description, string $location = null, string $path = "all.log"){
        if(!LAZY_LOG_ANY)
            return;
        $text = Log::GetLogText("OK", $title, $description, $location);
        Log::Log($path, $text);
    }

    protected static function GetLogText(string $type, string $title, string $description, $location){
        $date = "[".date('d-m-Y:H-i-s')."]";
        $type = "[$type]";
        $location = !empty($location) ? "[$location]" : '';
        return "$type$date$location$title:$description";
    }

    protected static function Log(string $path, string $text){
        if(!LAZY_LOG_ANY)
            return;
        $file = fopen(LAZY_LOG_PATH.$path, "a");
        fputs($file, $text.PHP_EOL);
        fclose($file);
    }
}
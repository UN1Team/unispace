<?php

namespace Anemon\LazyPHP;

require_once('config.php');
require_once('log.php');

use Anemon\LazyPHP\Log;
use mysqli;
use Exception;


class DB {
    //Connection to the DB
    protected $connection;
    protected $pgconnection;
    protected $type;

    public function __construct(){
        $this->type = DB_TYPE;
        if($this->type == 'mysql'){
            $this->connection = new mysqli(DB_HOST, DB_LOGIN, DB_PASSWORD, DB_NAME);
            if($this->connection->connect_errno){
                Log::Error("DB Connection", "(".$this->connection->connect_error.") ".$this->connection->connect_error, "db.php - __construct()");
                throw new Exception("Connection to DB error: (".$this->connection->connect_errno.") ".$this->connection->connect_error);
            }
        }
        else{
            $this->pgconnection = pg_connect("host=".DB_HOST." dbname=".DB_NAME."user=".DB_LOGIN." password=".DB_PASSWORD)
                or die('Не удалось соединиться с БД: '.pg_last_error());
        }
    }

    public function Select(string $table, array $columns, string ...$params){
        if(count($columns) === 0){
            Log::Warning("SELECT Request", "Trying SELECT nothing from ".$table, "db.php - Select()");
            throw new Exception("Trying SELECT nothing from ".$table);
        }
        $columns = implode(", ", $columns);
        if(count($params) > 0){
            $params = "WHERE ".implode(" AND ", $params);
        } else {
            $params = "";
        }
        return $this->Execute("SELECT ".$columns." FROM ".$table." ".$params);
    }

    public function Insert(string $table, array $columns, array $values){
        if(count($columns) === 0 || count($values) === 0){
            Log::Warning("INSERT Request", "Trying INSERT nothing in ".$table, "db.php - Insert()");
            throw new Exception("Trying INSERT nothing in ".$table);
        }
        if(count($columns) != count($values)){
            Log::Warning("INSERT Request", "Different count of columns and values. Table - ".$table, "db.php - Insert()");
            throw new Exception("Different count of columns and values");
        }
        $columns = implode(", ", $columns);
        $values = "'".implode("', '", $values)."'";
        return $this->Execute("INSERT INTO ".$table." (".$columns.") VALUES (".$values.")");
    }

    public function CreateTable(string $name, array $columns){
        if(count($columns) === 0){
            Log::Warning("CREATE TABLE Request", "Trying CREATE TABLE with out any columns. Table name - ".$name, "db.php - CreateTable()");
            throw new Exception("Trying CREATE TABLE with out any columns");
        }
        $columns = implode(", ", $columns);
        return $this->Execute("CREATE TABLE ".$name."(".$columns.")");
    }

    public function GetLastID(){
        if($this->type == 'mysql')
            return $this->connection->insert_id;
    }

    /*public function Update(string $table, array $columns, array $values){
        if(count($columns) === 0 || count($values) === 0){
            Log::Warning("INSERT Request", "Trying UPDATE nothing in ".$table, "db.php - Update()");
            throw new Exception("Trying UPDATE nothing in ".$table);
        }
        if(count($columns) != count($values)){
            Log::Warning("INSERT Request", "Different count of columns and values. Table - ".$table, "db.php - Update()");
            throw new Exception("Different count of columns and values");
        }
        $columns = implode(", ", $columns);
        $values = "'".implode("', '", $values)."'";
        return $this->Execute("UPDATE SET ");
    }*/

    public function Execute(string $request){
        $result = false;
        if($this->type == 'mysql')
            $result = $this->connection->query($request);
        else
            $result = pg_query($this->pgconnection, $request);
        if($result !== false){
            if(LOG_ALL)
                Log::Normal("DB Request", "Request ".$request." succesfully completed");
            return $result;
        }
        else{
            Log::Warning("DB Request", "Error on request execute: ".$request, "db.php - Execute()");
            throw new Exception("Error on request execute: ".$request);
        }
    }
}
<?php

namespace Anemon\LazyPHP;

require_once('config.php');
require_once('log.php');

use Anemon\LazyPHP\Log;
use Exception;
use PDO;
use PDOException;
use PDOStatement;

class DB {
    protected $connection;

    public function __construct()
    {
        try{
            $this->connection = new PDO(
                LAZY_DB_TYPE.":host=".LAZY_DB_HOST.";dbname=".LAZY_DB_NAME,
                LAZY_DB_LOGIN, LAZY_DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            Log::Normal("DB Connection", "Succesfully connected to DB", __FILE__."-".__LINE__." line");
        } catch (PDOException $e){
            Log::Error("DB Connection", $e->getMessage(), __FILE__."-".__LINE__." line");
            throw new Exception(__FILE__."-".__LINE__." line. "."DB Connection error: ".$e->getMessage());
        }
    }

    public function Table(string $name) : Table{
        return new Table($this, $name);
    }

    /**
     * Do 'SELECT' request to DB and return PDO answer
     * 
     * @param string $table
     * @param array $columns Columns, that need to select
     * @param array $where Where statements (['column1' => 'value1', 'column2' => 'value2'])
     * 
     * @return PDOStatement DB answer in FETCH_ASSOC mode
     */
    public function Select(string $table, array $columns, array $where = array()) : PDOStatement{
        if(empty($table)){
            Log::Warning("SELECT Request", "No table specified",  __FILE__."-".__LINE__." line");
            throw new Exception("No table specified");
        }
        if(count($columns) === 0){
            Log::Warning("SELECT Request", "Trying SELECT nothing from $table",  __FILE__."-".__LINE__." line");
            throw new Exception("Trying SELECT nothing from $table");
        }
        $sql = "SELECT ".implode(", ", $columns)." FROM $table";
        if(count($where) > 0){
            $sql .= " WHERE ".implode("=? AND ", array_keys($where))."=?";
            $args = array_values($where);
        } else {
            $args = array();
        }
        return $this->ExecutePrepare($sql, $args);
    }

    /**
     * Do 'INSERT' request to DB and return count of modified rows.
     * 
     * @return int count of modified rows
     */
    public function Insert(string $table, array $values) : int{
        if(empty($table)){
            Log::Warning("INSERT Request", "No table specified",  __FILE__."-".__LINE__." line");
            throw new Exception("No table specified");
        }
        if(count($values) === 0){
            Log::Warning("INSERT Request", "Trying INSERT nothing in $table", __FILE__."-".__LINE__." line");
            throw new Exception("Trying INSERT nothing in $table");
        }
        $sql = "INSERT INTO $table (".implode(", ", array_keys($values)).") VALUES (?".str_repeat(", ?", count($values) - 1).")";
        $args = array();
        foreach(array_values($values) as $arg){
            if(is_array($arg))
                array_push($args, json_encode($arg));
            else
                array_push($args, $arg);
        }
        return $this->ExecutePrepare($sql, $args)->rowCount();
    }


    /**
     * Do 'INSERT' request to DB and return id of inserted row.
     * 
     * @return int id of inserted row
     */
    public function InsertGetID(string $table, array $values) : int{
        $this->Insert($table, $values);
        return $this->connection->lastInsertId();
    }

    /**
     * Do 'UPDATE' request to DB and return count of modified rows.
     * 
     * @return int count of modified rows
     */
    public function Update(string $table, array $values, array $where = array()) : int{
        if(empty($table)){
            Log::Warning("UPDATE Request", "No table specified",  __FILE__."-".__LINE__." line");
            throw new Exception("No table specified");
        }
        if(count($values) === 0){
            Log::Warning("UPDATE Request", "Trying UPDATE nothing in $table", __FILE__."-".__LINE__." line");
            throw new Exception("Trying UPDATE nothing in $table");
        }
        $sql = "UPDATE $table SET ".implode("=?, ", array_keys($values))."=?";
        $args = array();
        foreach(array_values($values) as $arg){
            if(is_array($arg))
                array_push($args, "{".implode(", ", $arg)."}");
            else
                array_push($args, $arg);
        }
        if(count($where) > 0){
            $sql .= " WHERE ".implode("=? AND ", array_keys($where))."=?";
            foreach(array_values($where) as $arg){
                if(is_array($arg))
                    array_push($args, json_encode($arg));
                else
                    array_push($args, $arg);
            }
        }
        return $this->ExecutePrepare($sql, $args)->rowCount();
    }

    /**
     * Do 'DELETE' request to DB and return count of modified rows.
     * 
     * @return int count of modified rows
     */
    public function Delete(string $table, array $where = array()) : int{
        if(empty($table)){
            Log::Warning("DELETE Request", "No table specified",  __FILE__."-".__LINE__." line");
            throw new Exception("No table specified");
        }
        $sql = "DELETE FROM $table";
        $args = array();
        if(count($where) > 0){
            $sql .= " WHERE ".implode("=? AND ", array_keys($where))."=?";
            foreach(array_values($where) as $arg){
                if(is_array($arg))
                    array_push($args, json_encode($arg));
                else
                    array_push($args, $arg);
            }
        }
        return $this->ExecutePrepare($sql, $args)->rowCount();
    }

    /**
     * Execute SQL request and return PDO answer.
     * ATTENTION: This method does not check request for SQL injection.
     * If you are not shure that arguments of your request are safe, please use 'ExecutePrepare' function.
     * 
     * @param string $request SQL request
     * 
     * @return PDOStatement DB answer in FETCH_ASSOC mode
     */
    public function Execute(string $request) : PDOStatement{
        $answer = $this->connection->query($request);
        if($answer !== false){
            Log::Normal("DB Request", "Request $request succesfully completed", __FILE__."-".__LINE__." line");
            return $answer;
        } else {
            Log::Warning("DB Request", "Error on request execute: $request", __FILE__."-".__LINE__." line");
            throw new Exception("Error on request execute: $request");
        }
    }

    /**
     * Execute SQL request and return PDO answer.
     * This method check request for SQL injection.
     * 
     * @param string $request SQL request
     * @param array $args arguments for prepared string request
     * 
     * @return PDOStatement DB answer in FETCH_ASSOC mode
     */
    public function ExecutePrepare(string $request, array $args) : PDOStatement{
        try{
            $answer = $this->connection->prepare($request);
            if (!$answer->execute($args)){
                throw new PDOException("'$request' request failed. This exception caused as result of 'false' return from execute");
            }
        } catch (PDOException $e) {
            Log::Warning("DB Request (Prepared)", "Error on request execute: ".$e->getMessage(), __FILE__."-".__LINE__." line");
            throw new Exception("Error on request execute: ".$e->getMessage());
        }
        return $answer;
    }

    /**
     * Execute SQL request and return how many rows did he modify.
     * ATTENTION: This method does not check request for SQL injection.
     * If you are not shure that arguments of your request are safe, please use 'ExecutePrepare' function.
     * 
     * @param string $request SQL request
     * 
     * @return int count of modified rows
     */
    public function ExecuteCount(string $request) : int{
        $answer = $this->connection->exec($request);
        if($answer === 0){
            Log::Warning("DB Request (Count)", "Request didn`t modified any rows: $request", __FILE__."-".__LINE__." line");
        } else {
            Log::Normal("DB Request (Count)", "Request $request succesfully completed", __FILE__."-".__LINE__." line");
        }
        return $answer;
    }

    /**
     * Fetching PDO answer with postgres arrays
     * 
     * @param PDOStatement $statement PDO answer
     * 
     * @return array
     */
    public function FetchPDOStatement(PDOStatement $statement) : array{
        $fetched = $statement->fetchAll();
        $res = array();
        foreach($fetched as $rowkey => $row){
            $res[$rowkey] = array();
            foreach($row as $key => $value){
                if(is_string($value) && $value[0] == '{'){
                    $res[$rowkey][$key] = $this->ParseSQLArray($value);
                } else {
                    $res[$rowkey][$key] = $value;
                }
            }
        }
        return $res;
    }

    private function ParseSQLArray(string $sql) : array{
        $sql = mb_substr($sql, 1, strlen($sql) - 2);
        $res = array();
        foreach(explode(',', $sql) as $e){
            if($e[0] == "'")
                array_push($res, mb_substr($e, 1, strlen($e) - 2));
            else
                array_push($res, $e);
        }
        return $res;
    }
}

/**
 * This is a table of the specified database
 */
class Table {
    protected $db;
    protected $name;

    public function __construct(DB $database, string $name)
    {
        $this->db = $database;
        $this->name = $name;
    }

    /**
     * Do 'SELECT' request to DB with this table and return PDO answer
     * 
     * @return PDOStatement DB answer in FETCH_ASSOC mode
     */
    public function Select(array $columns, array $where = array()) : PDOStatement{
        return $this->db->Select($this->name, $columns, $where);
    }

    /**
     * Do 'INSERT' request to DB with this table and return count of modified rows.
     * 
     * @return int count of modified rows
     */
    public function Insert(array $values) : int{
        return $this->db->Insert($this->name, $values);
    }

    /**
     * Do 'INSERT' request to DB and return id of inserted row.
     * 
     * @return int id of inserted row
     */
    public function InsertGetID(array $values) : int{
        return $this->db->InsertGetID($this->name, $values);
    }

    /**
     * Do 'UPDATE' request to DB with this table and return count of modified rows.
     * 
     * @return int count of modified rows
     */
    public function Update(array $values, array $where = array()) : int{
        return $this->db->Update($this->name, $values, $where);
    }
}
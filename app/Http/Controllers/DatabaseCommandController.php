<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatabaseCommandController extends Controller
{
    public function Command(Request $req){        
        try{
            $command = $req->input('command');
            $commandKey = substr($command, 0, stripos($command, ' '));

            switch($commandKey){
                case 'SELECT':
                    $response = DB::select($command);
                    break;
                case 'UPDATE':
                    $response = DB::update($command);
                    break;
                case 'INSERT':
                    $response = DB::insert($command);
                    break;
                default:
                    return $this->Answer('', 'Console support only "SELECT", "UPDATE" or "INSERT" commands. If you need to execute another command, please do it by Laravel code.');
            }

            return $this->Answer($response, 'ok');
        }
        catch (QueryException $e) {
            return 'Exception';
        }
    }

    public static function Answer($result, $err){
        $answer = [
            'error' => $err,
            'result' => $result
        ];
        return view('datatojson', ['data' => $answer]);
    }
}

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>phpPgConsole</title>
        <link rel="stylesheet" href="/UniSpace/public/css/console.css">
    </head>
    <body>
        <section class="pastCommands">
            <div name="console" id="console" readonly></div>
        </section>
        <section class="inputCommand">
            <input type="text" id="inputConsole" name="inputConsole">
            <button id="runConsole">Выполнить</button>
        </section>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
        <script src="/UniSpace/public/js/console.js"></script>
    </body>
</html>
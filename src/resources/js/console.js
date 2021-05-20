$(function(){
    $('#runConsole').on('click', async function(){
        var data = new FormData();
        var input = $('#inputConsole');
        var textarea = $('#console');
        if(input.val() == '/clear'){
            input.val('');
            textarea.html('');
            return;
        }
        $(this).prop('disabled', true);
        input.prop('disabled', true);
        data.append('command', input.val());
        var response = await fetch('../api/dbcommand', {
            method: 'POST',
            body: data,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        var result = await response.json();
        if(result['error'] != 'ok'){
            input.prop('disabled', false);
            $(this).prop('disabled', false);
            var history = textarea.html();
            textarea.html(history+'<br>'+input.val()+'<br> <span style="color: red;">'+result['error']+'</span><br>');
            input.val('');
        } else {
            input.prop('disabled', false);
            $(this).prop('disabled', false);
            var history = textarea.html();
            if(result['result'] === true)
                textarea.html(history+'<br>'+input.val()+'<br> <span style="color: green;">Your request has been successfully completed</span><br>');
            else
                textarea.html(history+'<br>'+input.val()+'<br> <span style="color: green;">'+ArrayToHtml(result['result'])+'</span>');
            input.val('');
        }
    });

    function ArrayToHtml(arr, i = 0){
        if(!Array.isArray(arr)){
            return '--'.repeat(i) + Object.keys(arr)[0] + ': ' + arr[Object.keys(arr)[0]] + '<br>';
        }
        var result = "";
        var arrKeys = Object.keys(arr);
        Array.prototype.forEach.call(arrKeys, (key)=>{
            result = result + '--'.repeat(i) + key + ':<br>';
            result = result + ArrayToHtml(arr[key], i + 1);
        });
        //Array.prototype.forEach.call(arr, (element) => {
        //    result = result + ArrayToHtml(element, i+1) + '<br>';
        //});
        return result;
    }
});
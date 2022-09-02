<?php
if (!isset($_GET["id"])) {
    echo "Please define ID of the agent!";
} else {
    $agentId = ($_GET["id"] == 'master') ? 'false' : $_GET["id"];
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>    
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>LiveSmart Agent Board</title>
        <link rel="shortcut icon" href="favicon.ico">
        <link rel="stylesheet" media="all" type="text/css" href="//code.jquery.com/ui/1.11.0/themes/smoothness/jquery-ui.css" />
        <link rel="stylesheet" href="css/jquery-ui-timepicker-addon.css">
        <link rel="stylesheet" href="css/agent.css">
        <link rel="stylesheet" href="css/simplechat.css">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
        <script type="text/javascript" src="//code.jquery.com/ui/1.11.0/jquery-ui.min.js"></script>
        <script src="js/detect.js"></script>
        <script src="js/datetimepicker.js"></script>
    </head>

    <body>
        <div class="divGenerate">
            <table style="width: 500px;">
                <tr>
                    <td>Date: </td><td><input autocomplete="off" type="text" id="datetime" /></td>
                    <td>Duration: </td><td><select name="duration" id="duration"><option value="">-</option><option value="15">15</option><option value="30">30</option><option value="45">45</option></select></td>
                </tr>
                <tr>
                    <td>Agent Name: </td><td><input autocomplete="off" type="text" id="names" /></td>
                    <td>Agent Short URL: </td><td><input autocomplete="off" type="text" id="shortagent" /></td>
                </tr>
                <tr>
                    <td>Visitor Name: </td><td><input autocomplete="off" type="text" id="visitorName" /></td>
                    <td>Visitor Short URL: </td><td><input autocomplete="off" type="text" id="shortvisitor" /></td>
                </tr>
                <tr>
                    <td>Room: </td><td><input autocomplete="off" type="text" id="roomName" /></td>
                    <td>Password: </td><td><input autocomplete="new-password" type="password" id="roomPass" /></td>
                </tr>
                <tr>
                    <td colspan="4">
                        <a href="#" id="generateBroadcastLink" class="generateButton">Generate Broadcast</a>
                        <a href="#" id="saveLink" class="generateButton">Save Link</a> 
                        <a href="#" id="generateLink" class="generateButton">Generate Video</a>
                    </td>
                </tr>
            </table>
        </div>

        <div id="visitors"></div>  
        <div id="chats-lsv-admin"></div>

        <div id="statusbar"></div>

        <script>
            var isAdmin = true;
            var roomId = false;
            var agentId = "<?php echo $agentId; ?>";
            var agentUrl, visitorUrl, sessionId, shortAgentUrl, shortVisitorUrl, agentBroadcastUrl, viewerBroadcastLink;

            jQuery(document).ready(function ($) {

                $('#saveLink').on('click', function () {
                    generateLink();
                    var datetime = ($('#datetime').val()) ? new Date($('#datetime').val()).toISOString() : '';
                    $.ajax({
                        type: 'POST',
                        url: lsRepUrl + '/server/script.php',
                        data: {'type': 'scheduling', 'agentId': agentId, 'agent': $('#names').val(), 'agenturl': agentUrl, 'visitor': $('#visitorName').val(), 'visitorurl': visitorUrl,
                            'password': $('#roomPass').val(), 'session': sessionId, 'datetime': datetime, 'duration': $('#duration').val(), 'shortVisitorUrl': shortVisitorUrl, 'shortAgentUrl': shortAgentUrl}
                    })
                            .done(function (data) {
                                if (data == 200) {
                                    alert('Successfully saved');
                                } else {
                                    alert(data);
                                }
                            })
                            .fail(function () {
                                console.log('failed');
                            });
                });

                $('#generateLink').on('click', function () {
                    generateLink(false);
                    window.open(agentUrl);
                });

                $('#generateBroadcastLink').on('click', function () {
                    generateLink(true);
                    window.open(agentUrl);
                });

                var d = new Date();
                $('#datetime').datetimepicker({
                    timeFormat: 'h:mm TT',
                    stepHour: 1,
                    stepMinute: 15,
                    controlType: 'select',
                    hourMin: 8,
                    hourMax: 21,
                    minDate: new Date(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), 0),
                    oneLine: true
                });
            });
        </script>
        <script src="YOUR_DOMAIN/js/loader.v2.js" data-source_path="YOUR_DOMAIN/" ></script>
    </body>
</html>

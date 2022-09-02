<?php
if (!isset($_GET["id"])) {
    echo "Please define ID of the agent!";
} else {
    $agentId = ($_GET["id"] == 'master') ? '' : $_GET["id"];
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>    
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Chat Client</title>

        <!-- Favicon -->
        <link rel="shortcut icon" href="img/favicon.ico">

    </head>

    <body>
        <div id="nd-widget-container" class="nd-widget-container"></div>
        <script id="newdev-embed-script" data-message="Start Video Chat" data-agent_id="<?php echo $agentId; ?>" data-source_path="YOUR_DOMAIN/" src="YOUR_DOMAIN/js/widget.js" data-button-css="button_gray.css" data-avatar="../img/avatar.png" data-names="John Doe" async></script>

    </body>
</html>

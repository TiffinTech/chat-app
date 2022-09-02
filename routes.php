<?php

include_once 'server/connect.php';

function checkRoom($short) {

    global $dbPrefix, $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM ' . $dbPrefix . 'rooms WHERE `shortagenturl`= ? or `shortvisitorurl`= ? or `shortagenturl_broadcast`= ? or `shortvisitorurl_broadcast`= ?');
        $stmt->execute([$short, $short, $short, $short]);
        $row = $stmt->fetch();

        if ($row) {
            if ($row['shortagenturl'] == $short) {
                return $row['agenturl'];
            }
            if ($row['shortvisitorurl'] == $short) {
                return $row['visitorurl'];
            }
            if ($row['shortagenturl_broadcast'] == $short) {
                return $row['agenturl_broadcast'];
            }
            if ($row['shortvisitorurl_broadcast'] == $short) {
                return $row['visitorurl_broadcast'];
            }
        } else {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }
}

$redirect = (checkRoom($_GET['short'])) ? checkRoom($_GET['short']) : '/';
Header('HTTP/1.1 301 Moved Permanently');
Header('Location: ' . $redirect);
die();

<?php

if (IS_LOGGED == false) {
    header("Location: " . PT_Link('login'));
    exit();
}

$pt->page_url_ = $pt->config->site_url.'/livesmart/';
$pt->page        = 'livesmart';
$pt->title       = $lang->livesmart . ' | ' . $pt->config->title;
$pt->description = $pt->config->description;
$pt->keyword     = $pt->config->keyword;
$pt->content     = PT_LoadPage('livesmart/content', array(
    'LIVESMART_URL' => $pt->config->live_url,
    'LIVESMART_URL_BASE64' => base64_encode($pt->config->live_url),
    'USERNAME' => $pt->user->username

));

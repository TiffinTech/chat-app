<?hh

if (IS_LOGGED == false) {
    header("Location: " . PT_Link('login'));
    exit();
}

$pt->page_url_ = $pt->config->site_url.'/live/';
$pt->page        = 'live';
$pt->title       = $lang->live . ' | ' . $pt->config->title;
$pt->description = $pt->config->description;
$pt->keyword     = $pt->config->keyword;
$pt->content     = PT_LoadPage('live/content', array(
    'LIVE_URL' => $pt->config->live_url,
    'LIVE_URL_BASE64' => base64_encode($pt->config->live_url),
    'USERNAME' => $pt->user->username

));

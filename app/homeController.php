<?php
namespace App;

/* This class is handling all the requests in the frontend*/

class homeController{

    function __construct() {
        // Verify CSFR
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(! app('csfr')->verifyToken(SECRET_KEY) ){
                //header('HTTP/1.0 403 Forbidden');
                //exit();
            }
        }

        if(isset($_GET['view-as'])){
            if($_SESSION['user']['user_type'] == 1){
                $_SESSION['view-as'] = app('auth')->get_user_by_id($_GET['view-as']);
            }else{
                $url =  "//".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                header('Location: '.strtok($url, '?'));
            }
        }

        if (app('auth')->isIPBlocked()) {
            header('HTTP/1.0 403 Forbidden');
            exit();
        }

    }

    // main index function to  load homepage
    public function index(){
        if (isset(SETTINGS['single_room_mode'])  && SETTINGS['single_room_mode'] == 1) {
            if (!isset(SETTINGS['default_room_slug'])) {
                app('database')->orderBy("id","Asc");
                $default_room = app('database')->getOne('chat_rooms');
                if ($default_room) {
                    $default_room = $default_room['slug'];
                    $this->chat_room($default_room);
                }else{
                    $data = index_helper();
                    echo app('twig')->render('index.html', $data);
                }
            }else{
                $default_room = SETTINGS['default_room_slug'];
                $this->chat_room($default_room);
            }
        }else{
            $data = index_helper();
            echo app('twig')->render('index.html', $data);

        }
    }

    // load chat room pages for a given slug
    public function chat_room($chatroomslug, $activechat=false){
        $data = array();
        app('database')->where ('slug', $chatroomslug);
        if ($chat_room = app('database')->getOne('chat_rooms')) {
            if (app('auth')->isAuthenticated() == true){
                $allow_guest_view = false;
            }else{
                if (isset($chat_room['allow_guest_view']) && $chat_room['allow_guest_view']==true) {
                    $allow_guest_view = true;
                }else{
                    $allow_guest_view = false;
                }
            }
            if (app('auth')->isAuthenticated() == true || $allow_guest_view) {

                app('chat')->markAllNotified(); // mark all unnotified chat as notified
                // Get room's default group
                app('database')->where ('slug', 'general');
                app('database')->where ('chat_room', $chat_room['id']);
                $chat_group = app('database')->getOne('chat_groups');
                $data['chat_group'] = $chat_group;
                // Check if user already in this group else add
                if ($allow_guest_view==false) {
                    app('database')->where ('user', app('auth')->user()['id']);
                    app('database')->where ('chat_group', $chat_group['id']);
                    $exist_user = app('database')->getOne('group_users');
                }

                if ($chat_group) {
                    $join_chat = true;
                    if ($allow_guest_view==false) {
                        if(!$exist_user){
                            if (app('request')->method=='POST') {
                                $post_data = app('request')->body;

                                if ($chat_room['is_protected']){
                                    if (array_key_exists("pin", $post_data)){
                                        if ($chat_room['password'] != $post_data['pin']){
                                            app('msg')->error(__("Wrong PIN"));
                                            $join_chat = false;
                                        }
                                    }else{
                                        app('msg')->error(__("PIN Missing"));
                                        $join_chat = false;
                                    }
                                }
                                if ($join_chat){
                                    $insert_data = Array (
                                        "user" => app('auth')->user()['id'],
                                        "chat_group" => $chat_group['id'],
                                        "user_type" => 2,
                                        "status" => 1,
                                        "created_at" => app('database')->now(),
                                        "updated_at" => app('database')->now()
                                    );
                                    app('database')->insert ('group_users', $insert_data);
                                }

                            }
                        }
                    }

                    // get current room total user count
                    app('database')->join("chat_groups cg", "cg.id=gu.chat_group", "LEFT");
                    app('database')->where ('cg.chat_room', $chat_room['id']);
                    app('database')->where ('cg.slug', 'general');
                    app('database')->get('group_users gu', null, 'gu.*');
                    $chat_room['user_count'] = app('database')->count;
                    $data['chat_room'] = $chat_room;
                    
                    if ($allow_guest_view==false) {
                        $data['dm_user_count'] = app('chat')->get_dm_users_count();
                    }

                    $active_room = true;
                    if($chat_room['status'] == 2){
                        if(app('auth')->user()['user_type'] != 1){
                            $active_room = false;
                        }
                    }

                    $allowed_user = false;
                    if ($allow_guest_view==false) {
                        if($chat_room['allowed_users'] && !empty($chat_room['allowed_users'])){
                            if(!in_array(app('auth')->user()['user_type'], json_decode($chat_room['allowed_users']))){
                                $allowed_user = false;
                            }else{
                                $allowed_user = true;
                            }
                        }elseif(app('auth')->user()['user_type'] == 1){
                            $allowed_user = true;
                        }else{
                            $allowed_user = false;
                        }
                    }else{
                        $allowed_user = true;
                    }

                    $data['active_room'] = $active_room;
                    $data['chat_rooms'] = app('chat')->getChatRooms();
                    $data['lang_list'] = app('database')->get('languages');
                    $data['timezone_list'] = get_timezone_list();
                    include(BASE_PATH.'utils'.DS.'countries.php');
                    $data['country_list'] = $countries;

                    if (isset(SETTINGS['enable_codes'])  && SETTINGS['enable_codes'] == 1) {
                        include(BASE_PATH.'utils'.DS.'code_langs.php');
                        $data['code_langs'] = $code_langs;
                    }

                    app('database')->where('status', 1);
                    $data['radios'] = app('database')->get('radio_stations');
                    $data['next_url'] = URL.$chatroomslug;
                    if (isset(SETTINGS['enable_social_login']) && SETTINGS['enable_social_login'] == 1) {
                        $data['hybridauth_providers'] = hybridauth(get_social_config())->getProviders();
                    }
                    if (isset(SETTINGS['guest_mode'])  && SETTINGS['guest_mode'] == 1) {
                        $data['guest_data'] = app('auth')->nextGuestUser();
                    }

                    if(is_null($chat_room['disable_private_chats'])){
                        if(isset(SETTINGS['disable_private_chats'])){
                            $data['disable_private_chats'] = SETTINGS['disable_private_chats'];
                        }else{
                            $data['disable_private_chats'] = 0;
                        }
                    }else{
                        $data['disable_private_chats'] = $chat_room['disable_private_chats'];
                    }

                    if(is_null($chat_room['hide_chat_list'])){
                        if(isset(SETTINGS['hide_chat_list'])){
                            $data['hide_chat_list'] = SETTINGS['hide_chat_list'];
                        }else{
                            $data['hide_chat_list'] = 0;
                        }
                    }else{
                        $data['hide_chat_list'] = $chat_room['hide_chat_list'];
                    }

                    if (isset($data['hide_chat_list']) && $data['hide_chat_list']) { 
                        $data['disable_private_chats'] = 1; 
                    }

                    if(is_null($chat_room['disable_group_chats'])){
                        if(isset(SETTINGS['disable_group_chats'])){
                            $data['disable_group_chats'] = SETTINGS['disable_group_chats'];
                        }else{
                            $data['disable_group_chats'] = 0;
                        }
                    }else{
                        $data['disable_group_chats'] = $chat_room['disable_group_chats'];
                    }
                    $data['allow_guest_view'] = $allow_guest_view;
                    
                    if($data['disable_private_chats'] == 1){
                        $activechat = false;
                    }

                    if ($active_room){
                        if($allowed_user){
                            if ($join_chat) {
                                if ($allow_guest_view==false) {

                                    app('database')->where ('user', app('auth')->user()['id']);
                                    app('database')->where ('chat_group', $chat_group['id']);
                                    $group_user = app('database')->getOne('group_users');

                                    if ($group_user) {
                                        if($group_user['status'] == 3){
                                            $data['kicked_user'] = true;
                                            echo app('twig')->render('join_chatroom.html', $data);
                                        }else{
                                            $data['timezone_list'] = $this->get_timezone_list();

                                            if (in_array(app('auth')->user()['user_type'], array(1,2,4))) {
                                                if (app('auth')->user()['user_type'] == 2) {
                                                    app('database')->where ('created_by', app('auth')->user()['id']);
                                                }
                                                $my_rooms_list = app('database')->get('chat_rooms');
                                                $my_rooms = array();
                                                foreach ($my_rooms_list as $my_room) {
                                                    if($my_room['allowed_users'] && !empty($my_room['allowed_users'])){
                                                        if(in_array(app('auth')->user()['user_type'], json_decode($my_room['allowed_users']))){
                                                            app('database')->join("chat_groups cg", "cg.id=gu.chat_group", "LEFT");
                                                            app('database')->where ('cg.chat_room', $my_room['id']);
                                                            app('database')->where ('cg.slug', 'general');
                                                            app('database')->get('group_users gu', null, 'gu.*');
                                                            $my_room['users_count'] = app('database')->count;
                                                        }
                                                    }
                                                    array_push($my_rooms, $my_room);
                                                }
                                                $data['my_rooms'] = $my_rooms;

                                            }

                                            if ($activechat) {
                                                app('database')->where ('user_name', $activechat);
                                                if ($activechat = app('database')->getOne('users')) {
                                                    $data['activechat'] = $activechat['id'];
                                                }else{
                                                    header("HTTP/1.0 404 Not Found");
                                                    exit();
                                                }
                                            }

                                            app('database')->where ('user_id', app('auth')->user()['id']);
                                            $data['user_push_devices'] = app('database')->get('push_devices');

                                            $data['chat_btn_count'] = app('chat')->getChatButtonCount();
                                            
                                            if(isset($_GET['view-as'])){
                                                $data['view_as_user'] = $_SESSION['view-as'];
                                            }
                                            $data['joined_room'] = true;
                                            echo app('twig')->render('chat_room.html', $data);
                                        }
                                    }else{
                                        // New Room User
                                        if (isset($chat_room['allow_guest_view']) && $chat_room['allow_guest_view']==true) {
                                            $data['chat_room'] = $chat_room;
                                            $data['joined_room'] = false;
                                            echo app('twig')->render('chat_room.html', $data);
                                        }else{
                                            echo app('twig')->render('join_chatroom.html', $data);
                                        }
                                        
                                    }
                                }else{
                                    // Guest View Room Without loging
                                    $data['chat_room'] = $chat_room;
                                    $data['joined_room'] = false;
                                    echo app('twig')->render('chat_room.html', $data);
                                } 

                            }else{
                                // WRong PIN
                                $data['chat_room'] = $chat_room;
                                echo app('twig')->render('join_chatroom.html', $data);
                            }
                        }else{
                            $data['access_denied'] = true;
                            echo app('twig')->render('join_chatroom.html', $data);
                        }
                    }else{
                        // Room is Inactive
                        echo app('twig')->render('join_chatroom.html', $data);
                    }
                }else{
                    header("HTTP/1.0 404 Not Found");
                }
            }else {
                $data = array();
                $data['chat_room'] = $chat_room;
                $data['next_url'] = URL.$chatroomslug;

                if (isset(SETTINGS['guest_mode']) && SETTINGS['guest_mode'] == 1) {
                    $data['guest_data'] = app('auth')->nextGuestUser();
                    include(BASE_PATH.'utils'.DS.'countries.php');
                    $data['country_list'] = $countries;
                    $data['timezone_list'] = $this->get_timezone_list();
                }
                if (isset(SETTINGS['enable_social_login']) && SETTINGS['enable_social_login'] == 1) {
                    $data['hybridauth_providers'] = hybridauth(get_social_config())->getProviders();
                }
                echo app('twig')->render('login.html', $data);
            }
        }else{
            header("HTTP/1.0 404 Not Found");
        }


    }

    // load login page
    public function login(){

        if (app('request')->method=='POST') {
            $post_data = app('request')->body;

            // reCAPTCHA
            if (isset(SETTINGS['enable_recaptcha']) && SETTINGS['enable_recaptcha'] == true) {
                if (isset($_POST["g-recaptcha-response"])) {
                    if (isset(SETTINGS['recaptcha_version']) && SETTINGS['recaptcha_version'] == 3) {
                        $recaptcha_verify = recaptcha_v3_verify($_POST["g-recaptcha-response"]);
                    }else{
                        $recaptcha_verify = recaptcha_v2_verify($_POST["g-recaptcha-response"]);
                    }
                    if($recaptcha_verify){
                        $recaptcha_ok = true;
                    }else{
                        $recaptcha_ok = false;
                    }
                }else{
                    $recaptcha_ok = false;
                }
            }else{
                $recaptcha_ok = true;
            }

            if ($post_data && array_key_exists("email", $post_data) && array_key_exists("password", $post_data)) {
                if ($recaptcha_ok) {
                    $login = app('auth')->authenticate($post_data['email'], $post_data['password']);
                    if($login){
                        app('auth')->logIP($post_data['email'],1,'Success');
                        if (isset($_GET['next'])) {
                            if (filter_var($_GET['next'], FILTER_VALIDATE_URL) != false) {
                                header("Location: " . $_GET['next']);
                            }else {
                                header("Location: " . route('index'));
                            }
                        }else{
                            header("Location: " . route('index'));
                        }
                    }else{
                        app('auth')->logIP($post_data['email'],1,'Failed');
                        get_login_page();
                    }
                }else{
                    app('msg')->error(__('reCAPTCHA Error!'));
                    app('auth')->logIP($post_data['email'],1,'Recaptcha Error');
                    get_login_page();
                }
            }
        }else{
            if (app('auth')->isAuthenticated()) {
                if (isset($_GET['next'])) {
                    header("Location: " . $_GET['next']);
                }else{
                    if (isset($_SERVER['HTTP_REFERER'])) {
                        header('Location: ' . $_SERVER['HTTP_REFERER']);
                    }else{
                        header("Location: " . route('index'));
                    }
                }
            }else{
                get_login_page();
            }

        }
    }


    // load login page
    public function guest_login(){
        if (app('request')->method=='POST') {
            $post_data = app('request')->body;
            if ($post_data && array_key_exists("guest_name", $post_data) && array_key_exists("guest_username", $post_data)) {

                if (isset(SETTINGS['guest_name_edit']) && SETTINGS['guest_name_edit'] == 1 && isset(SETTINGS['display_name_format']) && SETTINGS['display_name_format'] == 'username') {
                    $guest_username = str_replace(' ', '', $post_data['guest_name']);
                    $guest_username = __('Guest-').$guest_username;
                }else{
                    $guest_username = $post_data['guest_username'];
                }

                if (isset($post_data['sex'])) {
                    $sex=$post_data['sex'];
                }else{
                    $sex=NULL;
                }
                if (isset($post_data['dob'])) {
                    $dob=$post_data['dob'];
                }else{
                    $dob=NULL;
                }
                if (isset($post_data['country'])) {
                    $country=$post_data['country'];
                }else{
                    $country='';
                }
                if (isset($post_data['timezone'])) {
                    $timezone=$post_data['timezone'];
                }else{
                    $timezone='';
                }
                $login = app('auth')->guest_authenticate(
                    __('Guest-').$post_data['guest_name'],
                    $guest_username,
                    $sex,
                    $dob,
                    $country,
                    $timezone
                );
                if($login){
                    app('auth')->logIP($guest_username,1,'Success');
                    if (isset($_GET['next'])) {
                        if (filter_var($_GET['next'], FILTER_VALIDATE_URL) != false) {
                            header("Location: " . $_GET['next']);
                        }else {
                            header("Location: " . route('index'));
                        }
                    }else{
                        header("Location: " . route('index'));
                    }
                }else{
                    get_login_page();
                }
            }
        }else{
            if (app('auth')->isAuthenticated()) {
                if (isset($_GET['next'])) {
                    header("Location: " . $_GET['next']);
                }else{
                    if (isset($_SERVER['HTTP_REFERER'])) {
                        header('Location: ' . $_SERVER['HTTP_REFERER']);
                    }else{
                        header("Location: " . route('index'));
                    }
                }
            }else{
                get_login_page();
            }

        }
    }


    // log out and destroy sessions
    public function logout(){
        session_destroy();
        if (isset($_COOKIE['cn_auth_key'])) {
            unset($_COOKIE['cn_auth_key']);
            cn_setcookie('cn_auth_key', null, time()-1, '/');
        }

        if((isset(SETTINGS['sso_enabled']) && SETTINGS['sso_enabled']) && (isset(SETTINGS['sso_logout_url']) && SETTINGS['sso_logout_url'])){
            // header("Location: " . SETTINGS['sso_logout_url']);
            echo "<script>top.window.location = '".SETTINGS['sso_logout_url']."'</script>";
        }else if (isset($_SERVER['HTTP_REFERER'])) {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }else{
            header("Location: " . route('index'));
        }

    }

    // load register page
    public function register(){
        $member_register = true;
        if(isset(SETTINGS['member_registration']) && SETTINGS['member_registration'] == 0){
            $member_register = false;
        }
        if($member_register){
            if (app('request')->method=='POST') {
                $data = array();
                $data['title'] = 'Sign Up - ' . SETTINGS['site_name'];
                $data = $post_data = app('request')->body;
                include(BASE_PATH.'utils'.DS.'countries.php');
                $data['country_list'] = $countries;
                $data['timezone_list'] = $this->get_timezone_list();
                if ($post_data && array_key_exists("email", $post_data) && array_key_exists("user_name", $post_data)
                    && array_key_exists("first_name", $post_data) && array_key_exists("last_name", $post_data)
                    && array_key_exists("password", $post_data)) {
                        if (isset($post_data['sex'])) {
                            $sex=$post_data['sex'];
                        }else{
                            $sex=NULL;
                        }
                        if (isset($post_data['dob'])) {
                            $dob=$post_data['dob'];
                        }else{
                            $dob=NULL;
                        }
                        if (isset($post_data['country'])) {
                            $country=$post_data['country'];
                        }else{
                            $country='';
                        }
                        if (isset($post_data['timezone'])) {
                            $timezone=$post_data['timezone'];
                        }else{
                            $timezone='';
                        }
                    $registration = app('auth')->registerNewUser(
                        $post_data['user_name'],
                        $post_data['first_name'],
                        $post_data['last_name'],
                        $post_data['email'],
                        $post_data['password'],
                        $post_data['password_repeat'],
                        $sex,
                        $dob,
                        $country,
                        $timezone
                    );
                    if($registration){
                        app('auth')->logIP($post_data['email'],2,'Success');

                        if (get_setting('enable_email_verification')) {
                            $activation_key = uniqid('cn_',true);
                            $update_data = Array ( 'available_status' => 3, 'activation_key' => $activation_key);
                            app('database')->where('id', $registration);
                            app('database')->update ('users', $update_data);
                            app('auth')->sendEmailVerificationLink($registration, $activation_key);
                            header("Location: " . route('login'));
                        }else{
                            $login = app('auth')->authenticate($post_data['email'], $post_data['password']);
                            if($login){
                               header("Location: " . route('index'));
                            }
                        }

                    }else{
                        app('auth')->logIP($post_data['email'],2,'Failed');
                        get_registration_page();
                    }
                }else{
                    get_registration_page();
                }
            }else{
                get_registration_page();
            }
        }else{
            get_login_page();
        }
    }

    // load forget password page
    public function forgot_password(){
        if (app('request')->method=='POST') {
            $data = array();
            $data['title'] = 'Forget Password - ' . SETTINGS['site_name'];
            $post_data = app('request')->body;
            if ($post_data && array_key_exists("email", $post_data)) {
                app('auth')->sendResetPasswordLink($post_data['email']);
            }
            echo app('twig')->render('forgot_password.html', $data);
        }else{
            $data = array();
            $data['title'] = 'Forget Password - ' . SETTINGS['site_name'];
            echo app('twig')->render('forgot_password.html', $data);
        }
    }

    // load reset password page
    public function reset_password(){
        if (app('request')->method=='POST') {
            $data = array();
            $data['title'] = 'Reset Password - ' . SETTINGS['site_name'];
            $post_data = app('request')->body;
            if ($post_data && array_key_exists("password", $post_data) && array_key_exists("reset_key", $post_data)){
                $reset_key = app('purify')->xss_clean($_GET['reset_key']);
                $validate_data = clean_and_validate("password", $post_data['password']);
                $password = $validate_data[0];
                $valid = true;
                $message = '<ul>';
                if(!$validate_data[1][0]){
                    $valid = false;
                    foreach ($validate_data[1][1]['password'] as $each_error) {
                        $message .= "<li>".$each_error."</li>";
                    }
                }
                $message .= "</ul>";

                if($valid){
                    $reset = app('auth')->resetPassword($reset_key,$password);
                    if ($reset[0]) {
                        app('msg')->success($reset[1]);
                        header("Location: " . route('login'));
                    }else{
                        app('msg')->error($reset[1]);
                        if (isset($_GET['reset_key'])) {
                            $value = app('purify')->xss_clean($_GET['reset_key']);
                            $data['reset_key'] = $value;
                        }
                        echo app('twig')->render('reset_password.html', $data);
                    }
                }else {
                    app('msg')->error($message);
                    echo app('twig')->render('reset_password.html', $data);
                }
            }
        }else{
            $data = array();
            $data['title'] = 'Reset Password - ' . SETTINGS['site_name'];
            if (isset($_GET['reset_key'])) {
                $value = app('purify')->xss_clean($_GET['reset_key']);
                $data['reset_key'] = $value;
            }
            echo app('twig')->render('reset_password.html', $data);
        }
    }

    public function get_timezone_list($selected_timezone=False){
        $opt = '';
        $regions = array('Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific');
        $tzs = timezone_identifiers_list();
        $optgroup = '';
        sort($tzs);
        $timestamp = time();
        if (!$selected_timezone) {
            $selected_timezone = SETTINGS['timezone'];
            if(app('auth')->user() && app('auth')->user()['timezone']){
                $selected_timezone = app('auth')->user()['timezone'];
            }
        }

        foreach ($tzs as $tz) {
            $z = explode('/', $tz, 2);
            date_default_timezone_set($tz); //for each timezone offset
            $diff_from_GMT = 'GMT ' . date('P', $timestamp);
            if (count($z) != 2 || !in_array($z[0], $regions)){
                continue;
            }
            if ($optgroup != $z[0]) {
                if ($optgroup !== ''){
                    $opt .= '</optgroup>';
                }
                $optgroup = $z[0];
                $opt .= '<optgroup label="' . htmlentities($z[0]) . '">';
            }

            $selected = "";
            if($selected_timezone == htmlentities($tz)){
                $selected = "selected";
            }
            $opt .= '<option value="' . htmlentities($tz) . '" '. $selected .' >'  . htmlentities(str_replace('_', ' ', $tz)). " - " .$diff_from_GMT . '</option>';
        }
        if ($optgroup !== ''){
            $opt .= '</optgroup>';
        }
        // change back system timezone
        date_default_timezone_set(SETTINGS['timezone']);
        return $opt;

    }

    // load customized color css file
    public function color_css(){
        header("Content-Type: text/css");
        if (isset(app()->theme)) {
            echo app('twig')->render('css/theme-'.app()->theme.'.css');
        }
    }

    // load chatnet js file
    public function chatnet_js(){
        $data = array();

        header("Content-Type: text/javascript");
        echo app('twig')->render('js/chatnet.js', $data);
    }

    // load index js file
    public function index_js(){
        header("Content-Type: text/javascript");
        echo app('twig')->render('js/index.js');
    }

    // load index js file
    public function scripts_js(){
        header("Content-Type: text/javascript");
        echo app('twig')->render('js/scripts.js');
    }

    // set language
    public function setlang($reqlang){
        app('database')->where('code',app('purify')->xss_clean($reqlang));
        $reqlang = app('database')->getOne('languages');
        $reqlang_json = json_encode($reqlang, true);
        if ($reqlang) {
            cn_setcookie('lang', $reqlang_json, time() + (86400 * 100), "/");
        }
        if(isset($_SERVER['HTTP_REFERER'])){
            header('Location: ' .  $_SERVER['HTTP_REFERER']);
        }else{
            header('Location: ' .  route('index'));
        }

    }

    // set theme
    public function settheme($reqtheme){
        if ($reqtheme && in_array($reqtheme, array('default', 'dark', 'custom', 'light'))) {
            cn_setcookie('theme', $reqtheme, time() + (86400 * 100), "/");
        }else{
            cn_setcookie('theme', '', time() - (1000), "/");
        }
        if(isset($_SERVER['HTTP_REFERER'])){
            header('Location: ' .  $_SERVER['HTTP_REFERER']);
        }else{
            header('Location: ' .  route('index'));
        }

    }

    // terms and conditions page
    public function terms(){
        if (SETTINGS['enable_terms']) {
            $data = array();
            $data['title'] = 'Terms & Conditions - ' . SETTINGS['site_name'];
            $data['lang_list'] = app('database')->get('languages');
            echo app('twig')->render('terms.html', $data);
        }else{
            header("HTTP/1.0 404 Not Found");
        }
    }

    // privacy policy page
    public function privacy(){
        if (SETTINGS['enable_privacy']) {
            $data = array();
            $data['title'] = 'Privacy Policy - ' . SETTINGS['site_name'];
            $data['lang_list'] = app('database')->get('languages');
            echo app('twig')->render('privacy.html', $data);
        }else{
            header("HTTP/1.0 404 Not Found");
        }
    }

    // privacy policy page
    public function about(){
        if (SETTINGS['enable_about']) {
            $data = array();
            $data['title'] = 'About Us - ' . SETTINGS['site_name'];
            $data['lang_list'] = app('database')->get('languages');
            echo app('twig')->render('about.html', $data);
        }else{
            header("HTTP/1.0 404 Not Found");
        }
    }

    // privacy policy page
    public function contact(){
        if (isset(SETTINGS['enable_contact']) && SETTINGS['enable_contact']) {
            $data = array();
            $data['title'] = 'Contact Us - ' . SETTINGS['site_name'];
            $data['lang_list'] = app('database')->get('languages');
            if (app('request')->method=='POST') {
                $post_data = app('request')->body;
                $contact_email = app('purify')->xss_clean($post_data['contact_email']);
                $contact_name = app('purify')->xss_clean($post_data['contact_name']);
                $contact_message = app('purify')->xss_clean($post_data['contact_message']);
                if (!empty($contact_email) && !empty($contact_name) && !empty($contact_message)) {

                    if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                        $email_data['contact_email'] = $contact_email;
                        $email_data['contact_name'] = $contact_name;
                        $email_data['contact_message'] = $contact_message;
                        $body = app('twig')->render('emails/contact.html', $email_data);
                        if (isset(SETTINGS['contact_us_email'])) {
                            if (isset(SETTINGS['contact_us_email'])) {
                                $site_name = SETTINGS['site_name'];
                            }else{
                                $site_name = '';
                            }
                            send_mail(SETTINGS['contact_us_email'], $site_name.' - '.__("New Contact Message"), $body);
                            app('msg')->success(__("Contact Message Has Been Sent"));
                        }else{
                            app('msg')->error(__('Sorry, No way to contact.'));
                        }

                    }else{
                        app('msg')->error(__('Email is invalid!'));
                    }
                }else{
                    app('msg')->error(__("All Feilds Are Required"));
                }
            }
            echo app('twig')->render('contact.html', $data);
        }else{
            header("HTTP/1.0 404 Not Found");
        }
    }


    // load index js file
    public function firebase_messaging_sw(){
        if (isset(SETTINGS['push_notifications']) && SETTINGS['push_notifications']==true) {
            header("Content-Type: text/javascript");
            echo app('twig')->render('js/firebase-messaging-sw.js');
        }
    }

    // PWA Manifest
    public function manifest(){
        $data = array();
        $data['start_url'] = URL;
        if (!empty(SETTINGS['pwa_background_color'])) {
            $data['pwa_background_color'] = SETTINGS['pwa_background_color'];
        }else{
            $data['pwa_background_color'] = "#ffffff";
        }
        if (!empty(SETTINGS['pwa_theme_color'])) {
            $data['pwa_theme_color'] = SETTINGS['pwa_theme_color'];
        }else{
            $data['pwa_theme_color'] = "transparent";;
        }
        if (!empty(SETTINGS['pwa_icon'])) {
            $data['pwa_icon'] = URL."media/settings/".SETTINGS['pwa_icon'];
        }else{
            $data['pwa_icon'] = URL."static/img/chatnet_app_192.png";
        }

        header("Content-Type: application/json");
        echo app('twig')->render('manifest.json',$data);
    }

    public function offline(){
        echo app('twig')->render('offline.html');
    }

    public function pwabuilder_sw($chatroomslug=null){
        header("Content-Type: text/JavaScript");
        echo app('twig')->render('js/pwabuilder-sw.js');
    }



    public function hybridauth_callback(){
        try {
            $storage = app('hybridauth_session');

            if (isset($_GET['provider'])) {
                $storage->set('provider', $_GET['provider']);
            }
            if ($provider = $storage->get('provider')) {

                $adapter = hybridauth(get_social_config())->authenticate($provider);

                try {
                    $user_profile = $adapter->getUserProfile();
                } catch (\Exception $e) {
                    $user_profile = false;
                }

                $storage->set('provider', null);

                if ($user_profile && $user_profile->email && ($user_profile->firstName || $user_profile->displayName)) {

                    app('database')->where('email', $user_profile->email);
                    if ($user = app('database')->getOne('users')) {
                        $login = app('auth')->authenticate($user_profile->email, null, true);
                        if($login){
                            if (isset($_GET['next'])) {
                                if (filter_var($_GET['next'], FILTER_VALIDATE_URL) != false) {
                                    header("Location: " . $_GET['next']);
                                }else {
                                    header("Location: " . route('index'));
                                }
                            }else{
                                header("Location: " . route('index'));
                            }
                        }else{
                            app('msg')->error(__('Social Login Failed'));
                            header("Location: " . route('login'));
                        }
                    }else{
                        if ($user_profile->gender) {
                            if ($user_profile->gender == 'male') {
                                $sex = 1;
                            }else if ($user_profile->gender == 'female') {
                                $sex = 2;
                            }else{
                                $sex = null;
                            }
                        }else{
                            $sex = null;
                        }

                        if ($user_profile->firstName == null) {
                            $first_name = $user_profile->displayName;
                        }else{
                            $first_name = $user_profile->firstName;
                        }

                        if ($user_profile->lastName == null) {
                            $last_name = $user_profile->displayName;
                        }else{
                            $last_name = $user_profile->lastName;
                        }


                        $random_pw = randomPassword();
                        $registration = app('auth')->registerNewUser(
                            strstr($user_profile->email, '@', true) . '_' . rand(9,99),
                            $first_name,
                            $last_name,
                            $user_profile->email,
                            $random_pw,
                            $random_pw,
                            $sex,
                            null,
                            null,
                            null
                        );
                        $registration = true;
                        if($registration){
                            if ($user_profile->photoURL) {
                                $avatar_name = uniqid(rand(), true).'.jpg';
                                $image_file = download_image($user_profile->photoURL, 'media/avatars/'.$avatar_name);
                                if ($image_file) {
                                    $social_avatar = Array ( 'avatar' => $avatar_name);
                                    app('database')->where('email', $user_profile->email);
                                    app('database')->update ('users', $social_avatar);
                                }
                            }
                            $login = app('auth')->authenticate($user_profile->email, $random_pw);
                            if($login){
                               header("Location: " . route('index'));
                            }
                        }else{
                            app('msg')->error(__('Social Login Registration Failed'));
                            header("Location: " . route('login'));
                        }
                    }
                }else{
                    app('msg')->error(__('Required Details Not Provided by the Social Network'));
                    header("Location: " . route('login'));
                }
            }else{
                app('msg')->error(__('Unsupported Soical Network'));
                header("Location: " . route('login'));
            }
            if (isset($_GET['logout'])) {
                $adapter = hybridauth(get_social_config())->getAdapter($_GET['logout']);
                $adapter->disconnect();
            }

        } catch (\Exception $e) {
            app('msg')->error(__('Social Login Failed'));
            header("Location: " . route('login'));
        }
    }


    // load resend activation page
    public function resend_activation(){
        if (app('request')->method=='POST') {
            $post_data = app('request')->body;
            if ($post_data && array_key_exists("email", $post_data)) {
                $email = app('purify')->xss_clean($post_data['email']);
                app('database')->where('email', $email);
                app('database')->where('available_status', 3);
                $user_email_exist = app('database')->getOne('users');
                if ($user_email_exist) {
                    app('auth')->sendEmailVerificationLink($user_email_exist['id'], $user_email_exist['activation_key']);
                    header("Location: " . route('login'));
                }else{
                    app('msg')->error(__('Provided email is invalid'));
                    $data = array();
                    $data['title'] = __('Resend Activation Link').' - ' . SETTINGS['site_name'];
                    echo app('twig')->render('resend_activation.html', $data);
                }
            }
        }else{
            $data = array();
            $data['title'] = __('Resend Activation Link').' - ' . SETTINGS['site_name'];
            echo app('twig')->render('resend_activation.html', $data);
        }
    }

    //Activate account
    public function activate(){
        $data = array();
        if (isset($_GET['activation_key'])) {
            $activation_key = app('purify')->xss_clean($_GET['activation_key']);
            if ($activation_key) {
                app('database')->where('activation_key', $activation_key);
                app('database')->where('available_status', 3);
                $user = app('database')->getOne('users');
                if ($user) {
                    $update_data = Array ( 'available_status' => 1, 'activation_key' => '');
                    app('database')->where('id', $user['id']);
                    app('database')->update ('users', $update_data);
                    $email_data = array();
                    $email_data['home_link'] = route('index');
                    $body = app('twig')->render('emails/welcome.html', $email_data);
                    send_mail($user['email'], SETTINGS['site_name'].': ' . __('Welcome aboard!'), $body);

                    app('msg')->success(__('Your account has been activated. You may now login.'));
                    header("Location: " . route('login'));
                }else{
                    app('msg')->error(__('User not found'));
                    header("Location: " . route('login'));
                }

            }else{
                app('msg')->error(__('Invalid activation key'));
                header("Location: " . route('login'));
            }

        }else{
            app('msg')->error(__('Invalid activation key'));
            header("Location: " . route('login'));
        }
    }


    public function pushy_sw(){
        header("Content-Type: text/JavaScript");
        echo app('twig')->render('js/pushy-service-worker.js');
    }

}

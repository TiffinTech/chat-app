<?php
namespace App;

/* This class is handling all the requests for sso*/

class ssoController{

    public function login() {

        if (isset(SETTINGS['sso_enabled']) && SETTINGS['sso_enabled'] == 1) {

            $post_data = app('request')->body;

            if (isset($post_data['email'])) {
                $email = app('purify')->xss_clean($post_data['email']);
            }else{
                $email = null;
            }

            if (isset($post_data['username'])) {
                $username = app('purify')->xss_clean($post_data['username']);
            }else{
                $username = null;
            }

            if (isset($post_data['first_name'])) {
                $first_name = app('purify')->xss_clean($post_data['first_name']);
            }else{
                $first_name = null;
            }

            if (isset($post_data['last_name'])) {
                $last_name = app('purify')->xss_clean($post_data['last_name']);
            }else{
                $last_name = null;
            }

            if (isset($post_data['avatar_url'])) {
                $avatar_url = app('purify')->xss_clean($post_data['avatar_url']);
            }else{
                $avatar_url = null;
            }

            if (isset($post_data['gender'])) {
                $gender = app('purify')->xss_clean($post_data['gender']);
            }else{
                $gender = null;
            }

            
            if (isset($_SERVER['HTTP_ORIGIN'])) {
                $http_origin = $_SERVER['HTTP_ORIGIN'];
            }else{
                die('HTTP_ORIGIN NOT FOUND');
            }

            if (isset(SETTINGS['sso_allowed_orgins']) && !empty(SETTINGS['sso_allowed_orgins'])) {
                $sso_allowed_orgins = str_replace(' ', '', SETTINGS['sso_allowed_orgins']);
                $sso_allowed_orgins = explode(',', $sso_allowed_orgins);
                if (in_array($http_origin, $sso_allowed_orgins)){
                    header("Access-Control-Allow-Origin: $http_origin");
                }else{
                    die('Allowed orgin failed');
                }
            }else{
                die('Allowed orgins not set');
            }

            header("Content-Type: application/json");
            header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
            header('Access-Control-Allow-Credentials: true');
    
            if ($email || $username) {

                app('database')->where('email', $email);
                if ($user = app('database')->getOne('users')) {
                    $login = app('auth')->authenticate($email, null, true);
                    if($login){
                        echo json_encode("OK");
                    }else{
                        echo json_encode("User is missing");
                    }
                }else{
                    if ($gender) {
                        if ($gender == 'male') {
                            $sex = 1;
                        }else if ($gender == 'female') {
                            $sex = 2;
                        }else{
                            $sex = null;
                        }
                    }else{
                        $sex = null;
                    }

                    if ($username == null) {
                        $username = strstr($email, '@', true) . '_' . rand(9,99);
                    }

                    if ($first_name == null) {
                        $first_name = strstr($email, '@', true);
                    }

                    if ($last_name == null) {
                        $last_name = strstr($email, '@', true);
                    }

                    $random_pw = randomPassword();
                    $registration = app('auth')->registerNewUser(
                        $username,
                        $first_name,
                        $last_name,
                        $email,
                        $random_pw,
                        $random_pw,
                        $sex,
                        null,
                        null,
                        null
                    );
                    if($registration){
                        if ($avatar_url) {
                            $avatar_name = uniqid(rand(), true).'.jpg';
                            $image_file = download_image($avatar_url, 'media/avatars/'.$avatar_name);
                            if ($image_file) {
                                $social_avatar = Array ( 'avatar' => $avatar_name);
                                app('database')->where('email', $email);
                                app('database')->update ('users', $social_avatar);
                            }
                        }
                        $login = app('auth')->authenticate($email, $random_pw);
                        if($login){
                            echo json_encode("OK");
                        }else{
                            echo json_encode("Failed to authenticate");
                        }
                    }else{
                        echo json_encode("Failed to register");
                    }
                }
            }else{
                session_destroy();
                if (isset($_COOKIE['cn_auth_key'])) {
                    unset($_COOKIE['cn_auth_key']);
                    cn_setcookie('cn_auth_key', null, time()-1, '/');
                }
                echo json_encode("Email or username is missing");
            }

        }
    }

    public function js(){
        if (isset(SETTINGS['sso_enabled']) && SETTINGS['sso_enabled'] == 1) {
            $data = array();
            header("Content-Type: text/javascript");
            echo app('twig')->render('js/sso.js', $data);
        }
    }

}

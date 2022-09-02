<?php
namespace App;

/* This class is handling all the requests in the cron*/

class cronController{

    function delete_guests() {
        if (SETTINGS['guest_inactive_hours'] > 0) {
            app('database')->where ('user_type', 3);
            app('database')->where ('last_seen <= (NOW() - interval '.SETTINGS['guest_inactive_hours'].' hour) OR last_seen IS NULL');
            $users = app('database')->get('users', null, 'id');
            foreach ($users as $user) {
                $delete_user = $user['id'];
                if (isset(SETTINGS['unlink_with_delete']) && SETTINGS['unlink_with_delete'] == true) {

                    //unlink group chats files
                    app('database')->where('sender_id', $delete_user);
                    app('database')->where('type', Array(2, 6, 7, 8), 'IN');
                    $group_chats = app('database')->get('group_chats');
                    foreach ($group_chats as $chat) {
                        app('chat')->unlink_files($chat['message'], $chat['type']);
                    }

                    //unlink private chats files
                    app('database')->where('sender_id', $delete_user);
                    app('database')->where('type', Array(2, 6, 7, 8), 'IN');
                    $private_chats = app('database')->get('private_chats');
                    foreach ($private_chats as $chat) {
                        app('chat')->unlink_files($chat['message'], $chat['type']);
                    }
                }
                app('database')->where ('user', $delete_user);
                app('database')->delete('group_users');

                app('database')->where ('sender_id', $delete_user);
                app('database')->delete('group_chats');

                app('database')->where ('from_user', $delete_user);
                app('database')->delete('private_chat_meta');

                app('database')->where ('sender_id', $delete_user);
                app('database')->delete('private_chats');

                app('database')->where ('id', $delete_user);
                app('database')->delete('users');
            }
        }
    }

    function delete_group_chats() {
        if (SETTINGS['delete_group_chat_hours'] > 0) {
            if (isset(SETTINGS['unlink_with_delete']) && SETTINGS['unlink_with_delete'] == true) {
                //unlink group chats files
                app('database')->where ('time <= (NOW() - interval '.SETTINGS['delete_group_chat_hours'].' hour)');
                app('database')->where('type', Array(2, 6, 7, 8), 'IN');
                $group_chats = app('database')->get('group_chats');
                foreach ($group_chats as $chat) {
                    app('chat')->unlink_files($chat['message'], $chat['type']);
                }
            }
            app('database')->where ('time <= (NOW() - interval '.SETTINGS['delete_group_chat_hours'].' hour)');
            app('database')->delete('group_chats');
        }
    }

    function delete_private_chats() {
        if (SETTINGS['delete_private_chat_hours'] > 0) {
            if (isset(SETTINGS['unlink_with_delete']) && SETTINGS['unlink_with_delete'] == true) {
                //unlink group chats files
                app('database')->where ('time <= (NOW() - interval '.SETTINGS['delete_private_chat_hours'].' hour)');
                app('database')->where('type', Array(2, 6, 7, 8), 'IN');
                $private_chats = app('database')->get('private_chats');
                foreach ($private_chats as $chat) {
                    app('chat')->unlink_files($chat['message'], $chat['type']);
                }
            }
            app('database')->where ('time <= (NOW() - interval '.SETTINGS['delete_private_chat_hours'].' hour)');
            app('database')->delete('private_chats');
        }
    }
}

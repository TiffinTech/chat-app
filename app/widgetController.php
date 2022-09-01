<?php
namespace App;

/* This class is handling all the requests for widgets*/

class widgetController{

    public function room_list() {
        $data = index_helper();
        if (isset($_GET['style'])) {
            $data['style'] = $_GET['style'];
        }else{
            $data['style'] = 'small';
        }
        if (isset($_GET['page'])) {
            $data['page'] = $_GET['page'];
        }else{
            $data['page'] = '#';
        }
        echo app('twig')->render('widgets/room_list.html', $data);
    } 

}

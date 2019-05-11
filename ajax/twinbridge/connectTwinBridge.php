<?php
    session_start();
    include_once('../../includes/config.php');
    include_once('../../includes/functions.php');
    if(isset($_POST['username']) && isset($_POST['password']) && CSRFValidate()){
        $username = $_POST['username'];
        $password = $_POST['password'];

        $ret = shell_exec('LOGIN="'.$username.'" PASSWORD="'.$password.'" sudo -E /usr/bin/python3 '.TWINBRIDGE_DIR.'/connectScript/phpConnect.py 2>&1 &');
        echo $ret;
    } else {
            echo '"error":true, "reason":"Incorrect parameters"';
    }
    
<?php
    session_start();
    include_once('../../includes/config.php');
    include_once('../../includes/functions.php');
    if(isset($_POST['username']) && isset($_POST['password']) && CSRFValidate()){
        $username = $_POST['username'];
        $password = $_POST['password'];
        $output = null;
        $command = 'LOGIN="'.$username.'" PASSWORD="'.$password.'" sudo -E '.TWINBRIDGE_DIR.'/connectScript/phpConnect.py 2>&1';
        $output = shell_exec($command);
        echo($output);
    } else {
            echo '"error":true, "reason":"Incorrect parameters"';
    }
    
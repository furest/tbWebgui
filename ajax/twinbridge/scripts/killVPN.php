<?php
    session_start();
    include("../../../includes/config.php");
    include("../../../includes/functions.php");

    if(!CSRFValidate()) {
        echo('{"error":true, "reason":"CSRF Error"}');
        die();    
    } 
    session_write_close();  
    $command = "sudo ".TWINBRIDGE_DIR."/bin/flush.sh";
    shell_exec($command);
    echo('{"error":false}');

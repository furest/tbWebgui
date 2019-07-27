<?php
    session_start();
    include("../../../includes/config.php");
    include("../../../includes/functions.php");


    if(!CSRFValidate())
    {
        echo('{"error":true, "reason":"CSRF Error"}');
        die();    
    }
    $intClientIp = ip2long($_SESSION['client_ip']);
    $intMask = cidr2mask((int)$_SESSION['mask']);
    $intMask = ip2long($intMask);
    $intNetId = $intClientIp & $intMask;

    $srvIP = long2ip($intNetId+1);
    session_write_close();  
    $errno=0;
    $errmsg="";
    $fsock = fsockopen($srvIP, 1500, $errno, $errmsg);
    if($fsock === false || errno !=0){
        echo('{"error":true, "reason":"'.$errmsg.'"}');
        die();
    }
    $reqListString = '{"type":"quit"}'.PHP_EOL;
    fwrite($fsock, $reqListString);
    $response = fgets($fsock);

    $jsonResponse = json_decode($response, true);
    if($jsonResponse == NULL){
        echo('{"error":true, "reason":"Response is not JSON"}');
        die();
    }
    echo($response);  

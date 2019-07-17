<?php
    session_start();
    include("../../../includes/config.php");
    include("../../../includes/functions.php");

    if(!isset($_SESSION['client_ip']) || !isset($_SESSION['mask']) || !CSRFValidate()) {
        echo('{"error":true, "reason":"Invalid parameters"}');
        die();
    }
    $intClientIp = ip2long($_SESSION['client_ip']);
    $intMask = cidr2mask((int)$_SESSION['mask']);
    $intMask = ip2long($intMask);
    $intNetId = $intClientIp & $intMask;

    $srvIP = long2ip($intNetId+1);


    $errno=0;
    $errmsg="";
    $fsock = fsockopen($srvIP, 1500, $errno, $errmsg);
    if($fsock === false || errno !=0){
        echo('{"error":true, "reason":"'.$errmsg.'"}');
        die();
    }
    $reqListString = '{"type":"list"}'.PHP_EOL;
    fwrite($fsock, $reqListString);
    $response = fgets($fsock);
    $jsonResponse = json_decode($response, true);
    if($jsonResponse == NULL){
        echo('{"error":true, "reason":"Response is not JSON"}');
        die();
    }
    $form = '<p>';
    $form .= '<h4>'._("Invite an academy to join your lab").'</h4>';
    $form .= '<div class="btn-group btn-block">';
    $form .= '<a href="#" style="padding:10px;float: right;display: block;position: relative;margin-top: -55px;" class="col-md-2 btn btn-danger" id="kill" onclick="killVPN()" csrf="'.htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES).'">Disconnect</a>';
    $form .= '</div>';
    $form .= '<form id="twiningsList">';
    $form .= '<input id="csrf_token" type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES).'"/>';
    $twinings = $jsonResponse['response'];
    foreach($twinings as $twining){
        $form .= '<div class="row form-group" align="center">';
        $form .= '<a href="#" class="btn academy btn-lg btn-info btn-block" onclick="invite('.$twining['academy_id'].')">'.$twining['login'].' ('.$twining['email'].')</a>';
        $form .= '</div>';
        //$form .= '</p>';
    }
    $form .= '</form>';
    $form .='<style>
                .academy{
                    width: 70%;
                }
            </style>';
    $response = array(
        error => false,
        response => $form
    );
    echo(json_encode($response));



    // {   "error": false, 
    //     "response": [   {   
    //                         "login": "twinbridge", 
    //                         "email": "furest@furest.be", 
    //                         "academy_id": 1, 
    //                         "twining_id": 4
    //                     }, 
    //                     {
    //                         "login": "halmstad", 
    //                         "email": "tb.halmstad@yopmail.com", 
    //                         "academy_id": 2, 
    //                         "twining_id": 5
    //                     }, 
    //                     {   "login": "raspi2", 
    //                         "email": "tb.raspi2@yopmail.com", 
    //                         "academy_id": 4, 
    //                         "twining_id": 15
    //                     }
    //                 ]
    // }

<?php
    session_start();
    include("../../../includes/config.php");
    include("../../../includes/functions.php");
    if(!isset($_POST['status']) && !isset($_POST['lab'])){
        echo('{"error":true, "reason":"Invalid parameters"}');
        die();
    }
    $lab = $_POST['lab'];
    $peer = ($_POST['status'] == 'hosting')? $lab['invit_username'] : $lab['init_username'];
    $ip = ($_POST['status'] == 'invited')? $lab['invit_ip'] : $lab['init_ip'];
    $since =  $lab['lab_starttime'] ;

    $page = '</p>';
    $page .= '<h3>' . _("Lab in progress") . '</h3>';
    $page .= '<div class="btn-group btn-block">';
    $page .= '<a href="#" style="padding:10px;float: right;display: block;position: relative;margin-top: -45px;" class="col-md-2 btn btn-danger" id="kill" onclick="killVPN()" csrf="'.htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES).'">Disconnect</a>';
    $page .= '</div>';
    $page .= '<div class="col-lg-6">';
    $page .= '<h4>'. _("Laboration informations") . '</h4>';
    $page .= '<div class="info-item">Peer</div>'.($peer?: '<i>Empty</i>').'<br>';
    $page .= '<div class="info-item">Since</div>'.$since.'<br>';
    $page .= '<div class="info-item">Your IP</div>'.$ip.'<br>';
    $page .= '<br>';
    $page .= '<a class="btn btn-lg btn-primary" id="menu" name="menu" >Menu</a>';
    $page .= '</div>';

    $response = array(
        error => false,
        response => $page
    );
    echo(json_encode($response));

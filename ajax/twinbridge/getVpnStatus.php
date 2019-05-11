<?php
include("../../includes/config.php") ;
$pidfile = TWINBRIDGE_DIR."/openvpn.pid";
if(file_exists($pidfile)){
    $openvpnPID = file_get_contents($pidfile);
    $openvpnPID = trim($openvpnPID);
    exec("ps -A | grep ".$openvpnPID, $stdoutPs);
    $pid_alive = false;
    foreach($stdoutPs as $linePs){
        if(preg_match('/\s*'.$openvpnPID.'\s/', $linePs) != 0){
            $pid_alive = true;
            break;
        }
    }
    if($pid_alive == false){
        echo('{"error":false, "result":"stopped"}');
        die();  
    }
    #Openvpn is running. Now we must check if interface is UP
    exec("ls /sys/class/net | grep tun0", $output);
    if(count($output) == 0) {
        echo('{"error":false, "result":"connecting"}');
        die();
    }
    exec('ip a show tun0', $stdoutIp);
    $stdoutIpAllLinesGlued = implode(" ", $stdoutIp);
    $stdoutIpWRepeatedSpaces = preg_replace('/\s\s+/', ' ', $stdoutIpAllLinesGlued);

    if (!preg_match_all('/inet (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/([0-3][0-9])/i', $stdoutIpWRepeatedSpaces, $matchesIpv4AddrAndSubnet)) {
        echo('{"error":false, "result":"configuring"}');
        die();
    } else {
        $numMatchesIpv4AddrAndSubnet = count($matchesIpv4AddrAndSubnet);
        if($numMatchesIpv4AddrAndSubnet == 3){ //Orig regex + ip + mask
            session_start();
            $_SESSION['client_ip'] = $matchesIpv4AddrAndSubnet[1][0];
            $_SESSION['mask'] = $matchesIpv4AddrAndSubnet[2][0];
            echo('{"error":false, "result":"connected", "ip":"'.$matchesIpv4AddrAndSubnet[1][0].'", "mask":"'.$matchesIpv4AddrAndSubnet[2][0].'"}');
            die();
        }
    }

} else {
    echo('{"error":false, "result":"stopped"}');
    die();
}



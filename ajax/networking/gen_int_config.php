<?php
session_start();
include_once('../../includes/config.php');
include_once('../../includes/functions.php');

if(isset($_POST['generate']) && isset($_POST['csrf_token']) && CSRFValidate()) {
    $cnfNetworking = array_diff(scandir(RASPI_CONFIG_NETWORKING, 1),array('..','.'));
    $cnfNetworking = array_combine($cnfNetworking,$cnfNetworking);
    $strConfFile = "";
    foreach($cnfNetworking as $index=>$file) {
        if($index != "defaults") {
            $cnfFile = parse_ini_file(RASPI_CONFIG_NETWORKING.'/'.$file);
            var_dump($cnfFile);
            if($cnfFile['static'] == true) {
                $strConfFile .= "interface ".$cnfFile['interface']."\n";
                if($cnfFile['ip_address'] != "" && !preg_match('/\s*\/\s*/')){
                    $strConfFile .= "static ip_address=".$cnfFile['ip_address']."\n";
                }
                if($cnfFile['routers'] != ""){
                    $strConfFile .= "static routers=".$cnfFile['routers']."\n";
                }
                if($cnfFile['domain_name_server'] != ""){
                    $strConfFile .= "static domain_name_servers=".$cnfFile['domain_name_server']."\n";
                }
                if($confFile['nohookWPASupplicant'] == true){
                    $strConfFile .= "nohook wpa_supplicant"."\n";
                }
            } elseif($cnfFile['static'] == false && $cnfFile['failover'] === true) {
                $strConfFile .= "profile static_".$cnfFile['interface']."\n";
                if($cnfFile['ip_address'] != "" && !preg_match('/\s*\/\s*/')){
                    $strConfFile .= "static ip_address=".$cnfFile['ip_address']."\n";
                }
                if($cnfFile['routers'] != ""){
                    $strConfFile .= "static routers=".$cnfFile['routers']."\n";
                }
                if($cnfFile['domain_name_server'] != ""){
                    $strConfFile .= "static domain_name_servers=".$cnfFile['domain_name_server']."\n";
                }
                $strConfFile .= "interface ".$cnfFile['interface']."\n";
                $strConfFile .= "fallback static_".$cnfFile['interface']."\n\n";
            } else {
                $strConfFile .= "#DHCP configured for ".$cnfFile['interface']."\n\n";
            }
        } else {
            $defaults = file_get_contents(RASPI_CONFIG_NETWORKING.'/'.$index)."\n\n";
            $strConfFile = $defaults . $strConfFile;
        }
    }

    if(file_put_contents('/tmp/dhcpcddata',$strConfFile)) {
        exec('sudo /bin/cp /tmp/dhcpcddata /etc/dhcpcd.conf');
        shell_exec('sudo /etc/raspap/hostapd/servicestart.sh');
        $output = ['return'=>0,'output'=>'Settings successfully applied'];
    } else {
        $output = ['return'=>2,'output'=>'Unable to write to apply settings'];
    }
    echo json_encode($output);
}

?>

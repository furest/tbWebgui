<?php

/**
 *
 *
 */
function ParseWPASupplicant(){
    exec(' sudo cat ' . RASPI_WPA_SUPPLICANT_CONFIG, $known_return);
    $networks = array();
    $network = NULL;
    //Parses the content of /etc/wpa_supplicant/wpa_supplicant.conf
    foreach ($known_return as $line) {
            /*Initializes the array for the currently parsed network */
        if (preg_match('/network\s*=/', $line)) { 
            $network = array('visible' => false, 'configured' => true, 'connected' => false);
        } 
        elseif ($network !== null) 
        {
                /*Stores the currently parsed network in the $networks array */
            if (preg_match('/^\s*}\s*$/', $line)) {
                $networks[$ssid] = $network;
                $network = null;
                $ssid = null;
            }   /*Sets parameter for currently parsed network */
            elseif ($lineArr = preg_split('/\s*=\s*/', trim($line))) 
            {
                switch (strtolower($lineArr[0])) {
                    case 'ssid':
                        $ssid = trim($lineArr[1], '"');
                        break;
                    case 'psk':
                        //Skips if passphrase already read. Falls through otherwise
                        if (array_key_exists('passphrase', $network)) { 
                            break;
                        }
                    case '#psk': 
                        //Recognises the protocol is WPA-PSK. Falls through.
                        //do nothing
                    case 'wep_key0': // Untested
                        $network['passphrase'] = trim($lineArr[1], '"');
                        break;
                    case 'key_mgmt':
                        if (!array_key_exists('passphrase', $network) && $lineArr[1] === 'NONE') {
                            $network['protocol'] = 'Open';
                        } else {
                            $network['protocol'] = trim($lineArr[1], '"');
                        }
                        break;
                    case 'priority':
                        $network['priority'] = trim($lineArr[1], '"');
                        break;
                    case 'eap':
                        $network['firstphase'] = trim($lineArr[1], '"');
                        break;
                    case 'phase2': //phase2="auth=MSCHAPV2" => [0] = phase2, [1] = "auth, [2] = MSCHAPV2"
                        if (trim($lineArr[1], '"') === "auth" && count($lineArr) > 2) {
                            $network['secondphase'] = trim($lineArr[2], '"');
                        } else {
                            $network['secondphase'] = trim($lineArr[1], '"');
                        }
                        break;
                    case 'identity':
                        $network['username'] = trim($lineArr[1], '"');
                        break;
                    case 'password':
                        $network['passphrase'] = trim($lineArr[1], '"');
                        break;
                }
            }
        }
    }
    return $networks;
}

function WriteWPASupplicant($networks){
    $wpasup  = 'ctrl_interface=DIR=' . RASPI_WPA_CTRL_INTERFACE . ' GROUP=netdev' . PHP_EOL;
    $wpasup .= 'update_config=1' . PHP_EOL;

    foreach ($networks as $ssid => $network) {
        $wpasup .= "network={" . PHP_EOL;
        $wpasup .= "\tssid=\"" . $ssid . "\"" . PHP_EOL;
        if (array_key_exists('priority', $network) && is_numeric($network['priority'])) {
            $wpasup .= "\tpriority=" . ($network['priority'] ?? "0") . PHP_EOL;
        }

        if ($network['protocol'] === 'Open') {
            $wpasup .= "\tkey_mgmt=NONE" . PHP_EOL;
        } elseif ($network['protocol'] === 'WPA-EAP') {
            if (strlen($network['passphrase']) < 8 && strlen($network['passphrase']) > 63) {
                return "Password of network $ssid is not the correct length";
            }
            $wpasup .= "\tkey_mgmt=WPA-EAP" . PHP_EOL;
            $wpasup .= "\teap=" . $network['firstphase'] . PHP_EOL;
            if ($network['secondphase'] !== 'None') {
                $wpasup .= "\tphase2=\"auth=" . $network['secondphase'] . "\"" . PHP_EOL;
            }
            $wpasup .= "\tidentity=\"" . $network['username'] . "\"" . PHP_EOL;
            $wpasup .= "\tpassword=\"" . $network['passphrase'] . "\"" . PHP_EOL;
                
        } else {
            if (strlen($network['passphrase']) < 8 && strlen($network['passphrase']) > 63) {
                return "Password of network $ssid is not the correct length";
            }
            $wpasup .= "\tkey_mgmt=WPA-PSK" . PHP_EOL;
            exec('wpa_passphrase ' . escapeshellarg($ssid) . ' ' . escapeshellarg($network['passphrase']), $wpa_passphrase);
            foreach ($wpa_passphrase as $line) {
                if (preg_match('/^\s*#?psk=.*$/', $line)) {
                    $wpasup .= $line . PHP_EOL;
                }
            }
            unset($wpa_passphrase);
            unset($line);
        }
        $wpasup .= "}" . PHP_EOL;
    }

    if (($wpa_file = fopen('/tmp/wifidata', 'w')) != false) {
        fwrite($wpa_file, $wpasup);
        fclose($wpa_file);
        system('sudo cp /tmp/wifidata ' . RASPI_WPA_SUPPLICANT_CONFIG, $returnval);
        if($returnval != 0){
            return "Error when copying new wpa_supplicant.conf over the old one";
        }
    } else{
        return "Error when creating temporary wpa_supplicant file";
    }
    return NULL;
}

function GetSurroundingNetworks(){

    exec('sudo wpa_cli -i ' . RASPI_WIFI_CLIENT_INTERFACE . ' scan');
    sleep(3);
    exec('sudo wpa_cli -i ' . RASPI_WIFI_CLIENT_INTERFACE . ' scan_results', $scan_return);
    array_shift($scan_return); //skips headers line

    $scanned_networks = array();

    // display output
    foreach ($scan_return as $network) {
        $arrNetwork = preg_split("/[\t]+/", $network);  // split result into array
        /* 0 is bssid
         * 1 is frequency (channel)
         * 2 is signal level
         * 3 is security settings (WPA-PSK, WPA-EAP,...)
         * 4 is SSID
         */
        $ssid = $arrNetwork[4]??"";
        if(preg_match('/^[\\\\x00]+$/', $ssid, $dummy)){ #Networks with SSID set to \x00\x00\x00\x00\x00... are actually just weird hidden network
            $ssid = ""; 
        }
        $scanned_networks[$ssid] = array(
            'configured' => false,
            'protocol' => ConvertToSecurity($arrNetwork[3]),
            'channel' => ConvertToChannel($arrNetwork[1]),
            'passphrase' => '',
            'visible' => true,
            'connected' => false,
            'RSSI' => $arrNetwork[2]
        );
    }
    return $scanned_networks;

}

function DisplayWPAConfig()
{
    /*When connect is clicked:
     * - Check if networks needs to be added, updated or nothing + save file
     * - Connect to the network exec('sudo wpa_cli -i ' . RASPI_WPA_CTRL_INTERFACE . ' select_network ' . strval($_POST['connect']));
     * - Restart wpa_supplicant otherwise: exec('sudo wpa_cli -i ' . RASPI_WIFI_CLIENT_INTERFACE . ' reconfigure', $reconfigure_out, $reconfigure_return);
     * - Scan surrounding networks
     * - Update list of current networks
     * - Get name of currently used network
     */

    $status = new StatusMessages();
    $stored_networks = ParseWPASupplicant();   
    $wpasup_needs_update = false;

    if (isset($_POST['connect']) && CSRFValidate()) { //If "connect" has been clicked on a SSID then simply connect and don't try saving settings
        $connectIndex = $_POST['connect']; //Index of the network to connect to, in the post request

        $connectSSID = $_POST['ssid'.$connectIndex];

        if(array_key_exists($connectSSID, $stored_networks)){

            $newPassphrase = $_POST['passphrase'.$connectIndex];
            $newPriority = $_POST['priority'.$connectIndex];
            if($newPassphrase != $stored_networks[$connectSSID]['passphrase']){
                $stored_networks[$connectSSID]['passphrase'] = $newPassphrase;
                $wpasup_needs_update = true;
            }
            if($newPriority != ($stored_networks[$connectSSID]['priority'] ?? 0)){
                $stored_networks[$connectSSID]['priority'] = $newPriority;
                $wpasup_needs_update = true;
            }

            //Do we need to check additional parameters for EAP networks?
            if(preg_match('/^(WPA\d?-EAP)/', $_POST['protocol'.$connectIndex])){

                $newUsername = $_POST['username'.$connectIndex];
                
                $newFirstPhase = $_POST['firstphase'.$connectIndex];
                $newSecondPhase = $_POST['secondphase'.$connectIndex];
                if($newUsername != $stored_networks[$connectSSID]['username']){
                    $stored_networks[$connectSSID]['username'] = $newUsername;
                    $wpasup_needs_update = true;
                }
                if($newFirstPhase != $stored_networks[$connectSSID]['firstphase']){
                    $stored_networks[$connectSSID]['firstphase'] = $newFirstPhase;
                    $wpasup_needs_update = true;
                }
                if($newSecondPhase != $stored_networks[$connectSSID]['secondphase']){
                    $stored_networks[$connectSSID]['secondphase'] = $newSecondPhase;
                    $wpasup_needs_update = true;
                }
            }
        } else{
            $newNetwork = array(
                'ssid' => $_POST['ssid'.$connectIndex],
                'passphrase' => $_POST['passphrase'.$connectIndex],
                'visible' => false, //Wether to grey it out or not. 
                'connected' => false, //Whether to show the connected logo
                'configured' => true //Whether to show the "V" mark showing it is a saved network
            );

            if($_POST['priority'.$connectIndex] != 0){
                $newNetwork['priority'] = $_POST['priority'.$connectIndex];
            }

            if(preg_match('/^(WPA\d?-EAP)/', $_POST['protocol'.$connectIndex])){
                $newNetwork['protocol'] = "WPA-EAP";
                $newNetwork['username'] = $_POST['username'.$connectIndex];
                $newNetwork['firstphase'] = $_POST['firstphase'.$connectIndex];
                $newNetwork['secondphase'] = $_POST['secondphase'.$connectIndex];
            } else {
                $newNetwork['protocol'] = ($_POST['protocol'.$connectIndex] === "Open" ? "Open" : "WPA-PSK");
            }
            $stored_networks[$newNetwork['ssid']] = $newNetwork;
            $wpasup_needs_update = true;
        }

    } elseif(isset($_POST['delete'])){
        $deletedIndex = $_POST['delete'];
        $deletedSSID = $_POST['ssid'.$deletedIndex];
        unset($stored_networks[$deletedSSID]);
        $wpasup_needs_update = true;
    }

    if($wpasup_needs_update){
        unset($ret);
        $ret = WriteWpaSupplicant($stored_networks);
        if($ret != NULL){
            $status->addMessage($ret, 'danger');
        } else {
            exec('sudo wpa_cli -i ' . RASPI_WIFI_CLIENT_INTERFACE . ' reconfigure', $reconfigure_out, $reconfigure_return);
            sleep(1);
        }
    }    

    if(isset($_POST['connect']) && CSRFValidate()){
        exec('sudo wpa_cli -i '.RASPI_WIFI_CLIENT_INTERFACE." select_network `sudo wpa_cli list_networks | awk -F'\\t' '($2 == \"".escapeshellarg($_POST['ssid'.$_POST['connect']])."\"){print $1}'` 2>&1");
    }

    $scanned_networks = GetSurroundingNetworks();

    foreach ($scanned_networks as $scanned_ssid => $scanned_network) {

        if(array_key_exists($scanned_ssid, $stored_networks)){
            $stored_networks[$scanned_ssid]['visible'] = true;
            $stored_networks[$scanned_ssid]['channel'] = $scanned_network['channel'];
            $stored_networks[$scanned_ssid]['RSSI'] = $scanned_network['RSSI'];
        }else{
            $stored_networks[$scanned_ssid] = $scanned_network;
        }
    }

    $all_networks = $stored_networks;

    exec('iwconfig ' . RASPI_WIFI_CLIENT_INTERFACE, $iwconfig_return);
    foreach ($iwconfig_return as $line) {
        if (preg_match('/ESSID:\"([^"]+)\"/i', $line, $iwconfig_ssid)) {
            $all_networks[$iwconfig_ssid[1]]['connected'] = true;
        }
    }

    exec("ls /sys/class/net | grep wlan", $interfaces);
    $interfaces = array_diff($interfaces, RASPI_HIDDEN_INTERFACES);
    ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-primary">
                <div class="panel-heading"><i class="fa fa-signal fa-fw"></i> <?php echo _("Configure client"); ?>
                </div>
                <div class="panel-body">
                    <p><?php $status->showMessages(); ?></p>
                    <div id="msgNetworking"></div>
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#ssid" aria-controls="ssid" role="tab" data-toggle="tab" aria-expanded="false">SSID</a></li>
                        <li><a href="#interfaces" aria-controls="wlan1" role="tab" data-toggle="tab" aria-expanded="false">Interfaces</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade active in" id="ssid">
                            <div class="panel-body">
                                <h4><?php echo _("Client settings"); ?></h4>
                                <div class="btn-group btn-block">
                                    <a href=".?<?php echo htmlspecialchars($_SERVER['QUERY_STRING'], ENT_QUOTES); ?>" style="padding:10px;float: right;display: block;position: relative;margin-top: -55px;" class="col-md-2 btn btn-info" id="update"><?php echo _("Rescan"); ?></a>
                                </div>

                                <form method="POST" action="?page=wifi_conf" name="wpa_conf_form">
                                    <?php CSRFToken() ?>
                                    <input type="hidden" name="client_settings" ?>
                                    <script>
                                        function showPassword(index) {
                                            var x = document.getElementsByName("passphrase" + index)[0];
                                            if (x.type === "password") {
                                                x.type = "text";
                                            } else {
                                                x.type = "password";
                                            }
                                        }
                                    </script>

                                        <?php 
                                        $index = 0;
                                        foreach ($all_networks as $ssid => $network) { 
                                        if($ssid==""){continue;}
                                        ?>
                                        <div class="col-md-6">
                                            <div class="panel panel-default" <?php if(!$network["visible"]){echo('style="background:#DDDDDD;"');}?>>
                                                <div class="panel-body">

                                                    <input type="hidden" name="ssid<?php echo $index ?>" value="<?php echo htmlentities($ssid, ENT_QUOTES) ?>" />
                                                    <h4><?php echo htmlspecialchars($ssid, ENT_QUOTES); ?></h4>

                                                    <div class="row">
                                                        <div class="col-xs-4 col-md-4">Status</div>
                                                        <div class="col-xs-4 col-md-4">
                                                            <?php if ($network['configured']) { ?>
                                                                <i class="fa fa-check-circle fa-fw"></i>
                                                            <?php } ?>
                                                            <?php if ($network['connected']) { ?>
                                                                <i class="fa fa-exchange fa-fw"></i>
                                                            <?php } ?>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-xs-4 col-md-4">Channel</div>
                                                        <div class="col-xs-4 col-md-4">
                                                            <?php if ($network['visible']) { ?>
                                                                <?php echo htmlspecialchars($network['channel'], ENT_QUOTES) ?>
                                                            <?php } else { ?>
                                                                <span class="label label-warning"> X </span>
                                                            <?php } ?>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-xs-4 col-md-4">RSSI</div>
                                                        <div class="col-xs-6 col-md-6">
                                                            <?php echo htmlspecialchars($network['RSSI'], ENT_QUOTES);
                                                            echo "dB (";
                                                            if ($network['RSSI'] >= -50) {
                                                                echo 100;
                                                            } elseif ($network['RSSI'] <= -100) {
                                                                echo 0;
                                                            } else {
                                                                echo  2 * ($network['RSSI'] + 100);
                                                            }
                                                            echo "%)";
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <input type="hidden" name="protocol<?php echo $index ?>" value="<?php echo htmlspecialchars($network['protocol'], ENT_QUOTES); ?>" />

                                                    <div class="row">
                                                        <div class="col-xs-4 col-md-4">Security</div>
                                                        <div class="col-xs-6 col-md-6"><?php echo $network['protocol'] ?></div>
                                                    </div>
                                                    <?php if (preg_match('/^(WPA\d?-EAP)/', $network['protocol'])) { ?>
                                                        <div class="form-group">
                                                            <div class="input-group col-xs-12 col-md-12">
                                                                <span class="input-group-addon" id="phase1">Phase 1</span>
                                                                <select <?php if(!$network["visible"]){echo('disabled');}?> class="form-control" aria-describedby="" name="firstphase<?php echo $index ?>">
                                                                    <option <?php if ($network['firstphase'] == "PEAP") {
                                                                                echo ('selected="true"');
                                                                            } ?>>PEAP</option>
                                                                    <option <?php if ($network['firstphase'] == "TLS") {
                                                                                echo ('selected="true"');
                                                                            } ?>>TLS</option>
                                                                    <option <?php if ($network['firstphase'] == "TTLS") {
                                                                                echo ('selected="true"');
                                                                            } ?>>TTLS</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <div class="input-group col-xs-12 col-md-12">
                                                                <span class="input-group-addon" id="phase2">Phase 2</span>
                                                                <select <?php if(!$network["visible"]){echo('disabled');}?> class="form-control" aria-describedby="" name="secondphase<?php echo $index ?>">
                                                                    <option <?php if ($network['secondphase'] == "None") {
                                                                                echo ('selected="true"');
                                                                            } ?>>None</option>
                                                                    <option <?php if ($network['secondphase'] == "MSCHAPV2") {
                                                                                echo ('selected="true"');
                                                                            } ?>>MSCHAPV2</option>
                                                                    <option <?php if ($network['secondphase'] == "MD5") {
                                                                                echo ('selected="true"');
                                                                            } ?>>MD5</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <div class="input-group col-xs-12 col-md-12">
                                                                <span class="input-group-addon" id="username">Username</span>
                                                                <input <?php if(!$network["visible"]){echo('disabled');}?> type="text" class="form-control" aria-describedby="username" name="username<?php echo $index ?>" value="<?php echo $network['username'] ?>" style="background-image: url(&quot;data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACIUlEQVQ4EX2TOYhTURSG87IMihDsjGghBhFBmHFDHLWwSqcikk4RRKJgk0KL7C8bMpWpZtIqNkEUl1ZCgs0wOo0SxiLMDApWlgOPrH7/5b2QkYwX7jvn/uc//zl3edZ4PPbNGvF4fC4ajR5VrNvt/mo0Gr1ZPOtfgWw2e9Lv9+chX7cs64CS4Oxg3o9GI7tUKv0Q5o1dAiTfCgQCLwnOkfQOu+oSLyJ2A783HA7vIPLGxX0TgVwud4HKn0nc7Pf7N6vV6oZHkkX8FPG3uMfgXC0Wi2vCg/poUKGGcagQI3k7k8mcp5slcGswGDwpl8tfwGJg3xB6Dvey8vz6oH4C3iXcFYjbwiDeo1KafafkC3NjK7iL5ESFGQEUF7Sg+ifZdDp9GnMF/KGmfBdT2HCwZ7TwtrBPC7rQaav6Iv48rqZwg+F+p8hOMBj0IbxfMdMBrW5pAVGV/ztINByENkU0t5BIJEKRSOQ3Aj+Z57iFs1R5NK3EQS6HQqF1zmQdzpFWq3W42WwOTAf1er1PF2USFlC+qxMvFAr3HcexWX+QX6lUvsKpkTyPSEXJkw6MQ4S38Ljdbi8rmM/nY+CvgNcQqdH6U/xrYK9t244jZv6ByUOSiDdIfgBZ12U6dHEHu9TpdIr8F0OP692CtzaW/a6y3y0Wx5kbFHvGuXzkgf0xhKnPzA4UTyaTB8Ph8AvcHi3fnsrZ7Wore02YViqVOrRXXPhfqP8j6MYlawoAAAAASUVORK5CYII=&quot;); background-repeat: no-repeat; background-attachment: scroll; background-size: 16px 18px; background-position: 98% 50%; cursor: auto;">
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                    <div class="form-group">
                                                        <div class="input-group col-xs-12 col-md-12">
                                                            <span class="input-group-addon" id="passphrase">Passphrase</span>
                                                            <?php if ($network['protocol'] === 'Open') { ?>
                                                                <input <?php if(!$network["visible"]){echo('disabled');}?> type="password" disabled class="form-control" aria-describedby="passphrase" name="passphrase<?php echo $index ?>" value="" />
                                                            <?php } else { ?>
                                                                <input <?php if(!$network["visible"]){echo('disabled');}?> type="password" class="form-control" aria-describedby="passphrase" name="passphrase<?php echo $index ?>" value="<?php echo $network['passphrase'] ?>" onKeyUp="CheckPSK(this, 'update<?php echo $index ?>')">
                                                                <span class="input-group-btn">
                                                                    <button class="btn btn-default" onclick="showPassword(<?php echo $index; ?>)" type="button">Show</button>
                                                                </span>
                                                            <?php } ?>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <div class="input-group col-xs-12 col-md-12">
                                                            <span class="input-group-addon" id="priority">Priority</span>
                                                            <input <?php if(!$network["visible"]){echo('disabled');}?> type="number" min="0" max="255" class="form-control" aria-describedby="priority" name="priority<?php echo $index ?>" value="<?php if (array_key_exists('priority', $network)) {
                                                                                                                                                                                                    echo ($network['passphrase']);
                                                                                                                                                                                                } else {
                                                                                                                                                                                                    echo ("0");
                                                                                                                                                                                                } ?>" />
                                                        </div>
                                                    </div>

                                                    <div class="btn-group btn-block ">
                                                        <button type="submit" class="col-xs-4 col-md-4 btn btn-info <?php if(!$network['visible']){echo("disabled");}?>" name="connect" value="<?php echo $index ?>"><?php echo _("Connect"); ?></button>
                                                        <button type="submit" class="col-xs-4 col-md-4 btn btn-danger <?php if(!$network['configured']){echo("disabled");}?>" name="delete" value="<?php echo $index ?>"><?php echo _("Delete"); ?></button>
                                                    </div><!-- /.btn-group -->

                                                </div><!-- /.panel-body -->
                                            </div><!-- /.panel-default -->
                                        </div><!-- /.col-md-6 -->

                                        <?php $index += 1; ?>
                                    <?php } ?>

                                </form>
                            </div><!-- ./ Panel body -->
                            <div class="panel-footer"><?php echo _("<strong>Note:</strong> WEP access points appear as 'Open'. RaspAP does not currently support connecting to WEP"); ?></div>
                        </div><!-- ./ tabpanel ssid -->
                        <div class="tab-pane fade" id="interfaces">
                            <ul class="nav nav-tabs">
                                <?php
                                foreach ($interfaces as $interface) {
                                  ?><li role="presentation"><a href="#<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>" aria-controls="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>" role="tab" data-toggle="tab"><?php echo htmlspecialchars($interface, ENT_QUOTES); ?></a></li>
                                <?php 
                                  } ?>
                            </ul>
                        </div> <!-- /.tab-panel -->
                    </div> <!-- /.tab-content -->
                    <div class="tab-content">
                        <?php
                        foreach ($interfaces as $interface) { ?>
                            <div class="tab-pane fade in " id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>">
                                <div class="col-lg-6">
                                    <form id="frm-<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>">
                                        <div class="form-group">
                                            <h4> <?php echo _("IP Address Settings") ?></h4>
                                            <div class="btn-group" data-toggle="buttons">
                                                <label class="btn btn-primary">
                                                    <input type="radio" name="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-addresstype" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dhcp" autocomplete="off"><?php echo _("DHCP"); ?>
                                                </label>
                                                <label class="btn btn-primary">
                                                    <input type="radio" name="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-addresstype" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-static" autocomplete="off"><?php echo _("Static IP"); ?>
                                                </label>
                                            </div><!-- /.btn-group -->
                                            <h4><?php echo _("Enable Fallback to Static Option"); ?></h4>
                                            <div class="btn-group" data-toggle="buttons">
                                                <label class="btn btn-primary">
                                                    <input type="radio" name="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dhcpfailover" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-failover" autocomplete="off"><?php echo _("Enabled"); ?>
                                                </label>
                                                <label class="btn btn-warning">
                                                    <input type="radio" name="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dhcpfailover" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-nofailover" autocomplete="off"><?php echo _("Disabled"); ?>
                                                </label>
                                            </div><!-- /.btn-group -->
                                        </div><!-- /.form-group -->
                                        <hr />
                                        <h4> <?php echo _("Static IP Options"); ?></h4>
                                        <div class="form-group">
                                            <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-ipaddress"><?php echo _("IP Address"); ?></label>
                                            <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-ipaddress" placeholder="0.0.0.0">
                                        </div>
                                        <div class="form-group">
                                            <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-netmask"> <?php echo _("Subnet Mask"); ?></label>
                                            <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-netmask" placeholder="255.255.255.0">
                                        </div>
                                        <div class="form-group">
                                            <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-gateway"> <?php echo _("Default Gateway"); ?></label>
                                            <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-gateway" placeholder="0.0.0.0">
                                        </div>
                                        <div class="form-group">
                                            <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dnssvr"> <?php echo _("DNS Server"); ?> </label>
                                            <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dnssvr" placeholder="0.0.0.0">
                                        </div>
                                        <div class="form-group">
                                            <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dnssvralt"> <?php echo _("Alternate DNS Server"); ?></label>
                                            <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dnssvralt" placeholder="0.0.0.0">
                                        </div>
                                        <a href="#" class="btn btn-outline btn-primary intsave" data-int="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>"> <?php echo _("Save settings"); ?></a>
                                        <a href="#" class="btn btn-warning intapply" data-int="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>"><?php echo _("Apply settings"); ?></a>
                                    </form>
                                </div> <!-- /.col-lg-6 -->
                            </div><!-- /.tab-panel -->
                        <?php } ?>
                    </div> <!-- /.tab-content -->
                    <div>
                        <!-- /.panel body -->
                    </div><!-- /.panel-primary -->
                </div><!-- /.col-lg-12 -->
            </div><!-- /.row -->
        <?php
    }

    ?>

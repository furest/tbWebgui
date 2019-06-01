<?php

/**
 *
 *
 */
function DisplayWPAConfig()
{
    $status = new StatusMessages();
    $networks = array();

    // Find currently configured networks
    exec(' sudo cat ' . RASPI_WPA_SUPPLICANT_CONFIG, $known_return);

    $network = null;
    $ssid = null;


    //Parses the content of /etc/wpa_supplicant/wpa_supplicant.conf
    foreach ($known_return as $line) {
        if (preg_match('/network\s*=/', $line)) {
            $network = array('visible' => false, 'configured' => true, 'connected' => false);
        } elseif ($network !== null) {
            if (preg_match('/^\s*}\s*$/', $line)) {
                $networks[$ssid] = $network;
                $network = null;
                $ssid = null;
            } elseif ($lineArr = preg_split('/\s*=\s*/', trim($line))) {
                switch (strtolower($lineArr[0])) {
                    case 'ssid':
                        $ssid = trim($lineArr[1], '"');
                        break;
                    case 'psk':
                        if (array_key_exists('passphrase', $network)) { //Skips if passphrase already read. Falls through otherwise
                            break;
                        }
                    case '#psk': //Recognises the protocol is WPA-PSK. Falls through.
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

    if (isset($_POST['connect'])) { //If "connect" has been clicked on a SSID
        $result = 0;
        exec('sudo wpa_cli -i ' . RASPI_WPA_CTRL_INTERFACE . ' select_network ' . strval($_POST['connect']));
    } elseif (isset($_POST['client_settings']) && CSRFValidate()) {
        $tmp_networks = $networks;
        if ($wpa_file = fopen('/tmp/wifidata', 'w')) {
            fwrite($wpa_file, 'ctrl_interface=DIR=' . RASPI_WPA_CTRL_INTERFACE . ' GROUP=netdev' . PHP_EOL);
            fwrite($wpa_file, 'update_config=1' . PHP_EOL);

            //Adds the info of the updates/created network to the list of saved/detected networks
            foreach (array_keys($_POST) as $post) {
                if (preg_match('/delete(\d+)/', $post, $post_match)) {
                    unset($tmp_networks[$_POST['ssid' . $post_match[1]]]);
                } elseif (preg_match('/update(\d+)/', $post, $post_match)) {
                    // NB, at the moment, the value of protocol from the form may
                    // contain HTML line breaks
                    if (preg_match('/^(WPA\d?-EAP)/', $_POST['protocol' . $post_match[1]])) {
                        $tmp_networks[$_POST['ssid' . $post_match[1]]] = array(
                            'protocol' =>  "WPA-EAP",
                            'passphrase' => $_POST['passphrase' . $post_match[1]],
                            'username' => $_POST['username' . $post_match[1]],
                            'firstphase' => $_POST['firstphase' . $post_match[1]],
                            'secondphase' => $_POST['secondphase' . $post_match[1]],
                            'configured' => true
                        );
                    } else {
                        $tmp_networks[$_POST['ssid' . $post_match[1]]] = array(
                            'protocol' => ($_POST['protocol' . $post_match[1]] === 'Open' ? 'Open' : 'WPA-PSK'),
                            'passphrase' => $_POST['passphrase' . $post_match[1]],
                            'configured' => true
                        );
                    }

                    if (array_key_exists('priority' . $post_match[1], $_POST)) {
                        $tmp_networks[$_POST['ssid' . $post_match[1]]]['priority'] = $_POST['priority' . $post_match[1]];
                    }
                }
            }

            //Generates the new wpa_supplicant.conf file
            $ok = true;
            foreach ($tmp_networks as $ssid => $network) {
                if ($network['protocol'] === 'Open') {
                    fwrite($wpa_file, "network={" . PHP_EOL);
                    fwrite($wpa_file, "\tssid=\"" . $ssid . "\"" . PHP_EOL);
                    fwrite($wpa_file, "\tkey_mgmt=NONE" . PHP_EOL);
                    if (array_key_exists('priority', $network)) {
                        fwrite($wpa_file, "\tpriority=" . $network['priority'] . PHP_EOL);
                    }
                    fwrite($wpa_file, "}" . PHP_EOL);
                } elseif ($network['protocol'] === 'WPA-EAP') {
                    if (strlen($network['passphrase']) >= 8 && strlen($network['passphrase']) <= 63) {
                        fwrite($wpa_file, "network={" . PHP_EOL);
                        fwrite($wpa_file, "\tssid=\"" . $ssid . "\"" . PHP_EOL);
                        fwrite($wpa_file, "\tkey_mgmt=WPA-EAP" . PHP_EOL);
                        fwrite($wpa_file, "\teap=" . $network['firstphase'] . PHP_EOL);
                        if ($network['secondphase'] !== 'None') {
                            fwrite($wpa_file, "\tphase2=\"auth=" . $network['secondphase'] . "\"" . PHP_EOL);
                        }
                        fwrite($wpa_file, "\tidentity=\"" . $network['username'] . "\"" . PHP_EOL);
                        fwrite($wpa_file, "\tpassword=\"" . $network['passphrase'] . "\"" . PHP_EOL);
                        if (array_key_exists('priority', $network)) {
                            fwrite($wpa_file, "\tpriority=" . $network['priority'] . PHP_EOL);
                        }
                        fwrite($wpa_file, "}" . PHP_EOL);
                    } else {
                        $status->addMessage('WPA passphrase must be between 8 and 63 characters', 'danger');
                        $ok = false;
                    }
                } else {
                    if (strlen($network['passphrase']) >= 8 && strlen($network['passphrase']) <= 63) {
                        unset($wpa_passphrase);
                        unset($line);
                        exec('wpa_passphrase ' . escapeshellarg($ssid) . ' ' . escapeshellarg($network['passphrase']), $wpa_passphrase);
                        foreach ($wpa_passphrase as $line) {
                            if (preg_match('/^\s*}\s*$/', $line)) {
                                if (array_key_exists('priority', $network)) {
                                    fwrite($wpa_file, "\tpriority=" . $network['priority'] . PHP_EOL);
                                }
                                fwrite($wpa_file, "\tkey_mgmt=WPA-PSK" . PHP_EOL);
                                fwrite($wpa_file, $line . PHP_EOL);
                            } else {
                                fwrite($wpa_file, $line . PHP_EOL);
                            }
                        }
                    } else {
                        $status->addMessage('WPA passphrase must be between 8 and 63 characters', 'danger');
                        $ok = false;
                    }
                }
            }

            //Generates the new wpa_supplicant.conf file
            if ($ok) {
                system('sudo cp /tmp/wifidata ' . RASPI_WPA_SUPPLICANT_CONFIG, $returnval);
                if ($returnval == 0) {
                    exec('sudo wpa_cli -i ' . RASPI_WIFI_CLIENT_INTERFACE . ' reconfigure', $reconfigure_out, $reconfigure_return);
                    if ($reconfigure_return == 0) {
                        $status->addMessage('Wifi settings updated successfully', 'success');
                        $networks = $tmp_networks;
                    } else {
                        $status->addMessage('Wifi settings updated but cannot restart (cannot execute "wpa_cli reconfigure")', 'danger');
                    }
                } else {
                    $status->addMessage('Wifi settings failed to be updated', 'danger');
                }
            }
        } else {
            $status->addMessage('Failed to update wifi settings', 'danger');
        }
    }



    exec('sudo wpa_cli -i ' . RASPI_WIFI_CLIENT_INTERFACE . ' scan');
    sleep(3);
    exec('sudo wpa_cli -i ' . RASPI_WIFI_CLIENT_INTERFACE . ' scan_results', $scan_return);

    array_shift($scan_return);

    // display output
    foreach ($scan_return as $network) {
        $arrNetwork = preg_split("/[\t]+/", $network);  // split result into array

        // If network is saved
        if (array_key_exists(4, $arrNetwork) && array_key_exists($arrNetwork[4], $networks)) {
            $networks[$arrNetwork[4]]['visible'] = true;
            $networks[$arrNetwork[4]]['channel'] = ConvertToChannel($arrNetwork[1]);
            // TODO What if the security has changed?
        } else {
            $networks[$arrNetwork[4]] = array(
                'configured' => false,
                'protocol' => ConvertToSecurity($arrNetwork[3]),
                'channel' => ConvertToChannel($arrNetwork[1]),
                'passphrase' => '',
                'visible' => true,
                'connected' => false
            );
        }

        // Save RSSI
        if (array_key_exists(4, $arrNetwork)) {
            $networks[$arrNetwork[4]]['RSSI'] = $arrNetwork[2];
        }
    }

    exec('iwconfig ' . RASPI_WIFI_CLIENT_INTERFACE, $iwconfig_return);
    foreach ($iwconfig_return as $line) {
        if (preg_match('/ESSID:\"([^"]+)\"/i', $line, $iwconfig_ssid)) {
            $networks[$iwconfig_ssid[1]]['connected'] = true;
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

                                <form method="POST" action="?page=wpa_conf" name="wpa_conf_form">
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

                                    <?php $index = 0; ?>
                                    <?php foreach ($networks as $ssid => $network) { ?>

                                        <div class="col-md-6">
                                            <div class="panel panel-default">
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
                                                                <select class="form-control" aria-describedby="" name="firstphase<?php echo $index ?>">
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
                                                                <select class="form-control" aria-describedby="" name="secondphase<?php echo $index ?>">
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
                                                                <input type="text" class="form-control" aria-describedby="username" name="username<?php echo $index ?>" value="<?php echo $network['username'] ?>" style="background-image: url(&quot;data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACIUlEQVQ4EX2TOYhTURSG87IMihDsjGghBhFBmHFDHLWwSqcikk4RRKJgk0KL7C8bMpWpZtIqNkEUl1ZCgs0wOo0SxiLMDApWlgOPrH7/5b2QkYwX7jvn/uc//zl3edZ4PPbNGvF4fC4ajR5VrNvt/mo0Gr1ZPOtfgWw2e9Lv9+chX7cs64CS4Oxg3o9GI7tUKv0Q5o1dAiTfCgQCLwnOkfQOu+oSLyJ2A783HA7vIPLGxX0TgVwud4HKn0nc7Pf7N6vV6oZHkkX8FPG3uMfgXC0Wi2vCg/poUKGGcagQI3k7k8mcp5slcGswGDwpl8tfwGJg3xB6Dvey8vz6oH4C3iXcFYjbwiDeo1KafafkC3NjK7iL5ESFGQEUF7Sg+ifZdDp9GnMF/KGmfBdT2HCwZ7TwtrBPC7rQaav6Iv48rqZwg+F+p8hOMBj0IbxfMdMBrW5pAVGV/ztINByENkU0t5BIJEKRSOQ3Aj+Z57iFs1R5NK3EQS6HQqF1zmQdzpFWq3W42WwOTAf1er1PF2USFlC+qxMvFAr3HcexWX+QX6lUvsKpkTyPSEXJkw6MQ4S38Ljdbi8rmM/nY+CvgNcQqdH6U/xrYK9t244jZv6ByUOSiDdIfgBZ12U6dHEHu9TpdIr8F0OP692CtzaW/a6y3y0Wx5kbFHvGuXzkgf0xhKnPzA4UTyaTB8Ph8AvcHi3fnsrZ7Wore02YViqVOrRXXPhfqP8j6MYlawoAAAAASUVORK5CYII=&quot;); background-repeat: no-repeat; background-attachment: scroll; background-size: 16px 18px; background-position: 98% 50%; cursor: auto;">
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                    <div class="form-group">
                                                        <div class="input-group col-xs-12 col-md-12">
                                                            <span class="input-group-addon" id="passphrase">Passphrase</span>
                                                            <?php if ($network['protocol'] === 'Open') { ?>
                                                                <input type="password" disabled class="form-control" aria-describedby="passphrase" name="passphrase<?php echo $index ?>" value="" />
                                                            <?php } else { ?>
                                                                <input type="password" class="form-control" aria-describedby="passphrase" name="passphrase<?php echo $index ?>" value="<?php echo $network['passphrase'] ?>" onKeyUp="CheckPSK(this, 'update<?php echo $index ?>')">
                                                                <span class="input-group-btn">
                                                                    <button class="btn btn-default" onclick="showPassword(<?php echo $index; ?>)" type="button">Show</button>
                                                                </span>
                                                            <?php } ?>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <div class="input-group col-xs-12 col-md-12">
                                                            <span class="input-group-addon" id="priority">Priority</span>
                                                            <input type="number" min="0" max="255" class="form-control" aria-describedby="priority" name="priority<?php echo $index ?>" value="<?php if (array_key_exists('priority', $network)) {
                                                                                                                                                                                                    echo ($network['passphrase']);
                                                                                                                                                                                                } else {
                                                                                                                                                                                                    echo ("0");
                                                                                                                                                                                                } ?>" />
                                                        </div>
                                                    </div>

                                                    <div class="btn-group btn-block ">
                                                        <?php if ($network['configured']) { ?>
                                                            <input type="submit" class="col-xs-4 col-md-4 btn btn-warning" value="<?php echo _("Update"); ?>" id="update<?php echo $index ?>" name="update<?php echo $index ?>" <?php echo ($network['protocol'] === 'Open' ? ' disabled' : '') ?> />
                                                            <button type="submit" class="col-xs-4 col-md-4 btn btn-info" value="<?php echo $index ?>"><?php echo _("Connect"); ?></button>
                                                        <?php } else { ?>
                                                            <input type="submit" class="col-xs-4 col-md-4 btn btn-info" value="<?php echo _("Add"); ?>" id="update<?php echo $index ?>" name="update<?php echo $index ?>" <?php echo ($network['protocol'] === 'Open' ? '' : ' disabled') ?> />
                                                        <?php } ?>
                                                        <input type="submit" class="col-xs-4 col-md-4 btn btn-danger" value="<?php echo _("Delete"); ?>" name="delete<?php echo $index ?>" <?php echo ($network['configured'] ? '' : ' disabled') ?> />
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
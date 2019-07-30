<?php

include_once('includes/status_messages.php');

/**
 *
 *
 */
function DisplayHostAPDConfig()
{
  $status = new StatusMessages();
  $arrHostapdConf = parse_ini_file('/etc/raspap/hostapd.ini');
  $arrConfig = array();
  $arr80211Standard = array('a', 'b', 'g', 'n');
  $arrSecurity = array(1 => 'WPA', 2 => 'WPA2', 3 => 'WPA+WPA2', 'none' => _("None"));
  $arrEncType = array('TKIP' => 'TKIP', 'CCMP' => 'CCMP', 'TKIP CCMP' => 'TKIP+CCMP');

  if (isset($_POST['SaveHostAPDSettings'])) {
    if (CSRFValidate()) {
      SaveHostAPDConfig($arrSecurity, $arrEncType, $arr80211Standard, $status);
      if(isset($_POST['acs']) && $_POST['acs'] == "on"){
        exec("sudo systemctl enable hostapd_autochannel");
      } else {
        exec("sudo systemctl disable hostapd_autochannel");
      }
    } else {
      error_log('CSRF violation');
    }
  } elseif (isset($_POST['StartHotspot'])) {
    if (CSRFValidate()) {
      $status->addMessage('Attempting to start hotspot', 'info');
      exec('sudo systemctl start hostapd', $return);
      foreach ($return as $line) {
        $status->addMessage($line, 'info');
      }
    } else {
      error_log('CSRF violation');
    }
  } elseif (isset($_POST['StopHotspot'])) {
    if (CSRFValidate()) {
      $status->addMessage('Attempting to stop hotspot', 'info');
      exec('sudo systemctl stop hostapd', $return);
      foreach ($return as $line) {
        $status->addMessage($line, 'info');
      }
    } else {
      error_log('CSRF violation');
    }
  } elseif (isset($_POST['RestartHotspot'])) {
    if (CSRFValidate()) {
      $status->addMessage('Attempting to restart hotspot', 'info');
      exec('sudo systemctl restart hostapd', $return);
      foreach ($return as $line) {
        $status->addMessage($line, 'info');
      }
    } else {
      error_log('CSRF violation');
    }
  }

  exec('cat ' . RASPI_HOSTAPD_CONFIG, $hostapdconfig);
  exec('pidof hostapd | wc -l', $hostapdstatus);

  if ($hostapdstatus[0] == 0) {
    $status->addMessage('HostAPD is not running', 'warning');
  } else {
    $status->addMessage('HostAPD is running', 'success');
  }

  foreach ($hostapdconfig as $hostapdconfigline) {
    if (strlen($hostapdconfigline) === 0) {
      continue;
    }

    if ($hostapdconfigline[0] != "#") {
      $arrLine = explode("=", $hostapdconfigline);
      $arrConfig[$arrLine[0]] = $arrLine[1];
    }
  };

  if (isset($_POST['savedhcpdsettings'])) {
    if (CSRFValidate()) {
      $errors = '';
      define('IFNAMSIZ', 16);

      if (
        !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/', $_POST['RangeStart']) &&
        !empty($_POST['RangeStart'])
      ) {  // allow ''/null ?
        $errors .= _('Invalid DHCP range start.') . '<br />' . PHP_EOL;
      }

      if (
        !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/', $_POST['RangeEnd']) &&
        !empty($_POST['RangeEnd'])
      ) {  // allow ''/null ?
        $errors .= _('Invalid DHCP range end.') . '<br />' . PHP_EOL;
      }

      if (!ctype_digit($_POST['RangeLeaseTime']) && $_POST['RangeLeaseTimeUnits'] !== 'infinite') {
        $errors .= _('Invalid DHCP lease time, not a number.') . '<br />' . PHP_EOL;
      }

      if (!in_array($_POST['RangeLeaseTimeUnits'], array('m', 'h', 'd', 'infinite'))) {
        $errors .= _('Unknown DHCP lease time unit.') . '<br />' . PHP_EOL;
      }

      $return = 1;
      if (empty($errors)) {
        $hotspotConfig = parse_ini_file(RASPI_CONFIG_NETWORKING . '/' . RASPI_WIFI_HOTSPOT_INTERFACE . '.ini');
        $networkSettings = explode('/', $hotspotConfig['ip_address']);
        $ip = $_POST['RangeStart'];
        $cidr = $networkSettings[1];
        $mask = cidr2mask($cidr);

        $intIp = ip2long($ip);
        $intMask = ip2long($mask);
        $firstIp = ($intIp & $intMask) + 1;
        $hotspotConfig['ip_address'] = long2ip($firstIp) . '/' . $cidr;
        write_php_ini($hotspotConfig, RASPI_CONFIG_NETWORKING . '/' . RASPI_WIFI_HOTSPOT_INTERFACE . '.ini');
        $hostname = gethostname();
        $hosts = file_get_contents("/etc/hosts");
        $newHosts = preg_replace('/(.*\s+)(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(\s*' . $hostname . '\s*)/', '${1}' . long2ip($firstIp) . '${3}', $hosts);
        file_put_contents("/tmp/hostsdata", $newHosts);
        exec('sudo /bin/cp /tmp/hostsdata /etc/hosts');
        $config = 'interface=' . RASPI_WIFI_HOTSPOT_INTERFACE . PHP_EOL .
          'dhcp-range=' . long2ip($firstIp + 1) . ',' . $_POST['RangeEnd'] .
          ',255.255.255.0,';
        if ($_POST['RangeLeaseTimeUnits'] !== 'infinite') {
          $config .= $_POST['RangeLeaseTime'];
        }
        $config .= $_POST['RangeLeaseTimeUnits'];
        exec('echo "' . escapeshellarg($config) . '" > /tmp/dnsmasqdata', $temp);
        system('sudo cp /tmp/dnsmasqdata ' . RASPI_DNSMASQ_CONFIG);
        $ret = gen_config();
        $jsonRet = $ret;
        $return = 0;
        if ($ret["return"] != 0) {
          $status->addMessage($ret["output"], "danger");
          $return = 1;
        }
      } else {
        $status->addMessage($errors, 'danger');
      }

      if ($return == 0) {
        $status->addMessage('Dnsmasq configuration updated successfully', 'success');
      } else {
        $status->addMessage('Dnsmasq configuration failed to be updated.', 'danger');
      }
    } else {
      error_log('CSRF violation');
    }
  }

  exec('pidof dnsmasq | wc -l', $dnsmasq);
  $dnsmasq_state = ($dnsmasq[0] > 0);

  $autochannel_enabled = false;
  exec('systemctl is-enabled hostapd_autochannel', $autochannel_ret);
  if($autochannel_ret[0] == "enabled"){
    $autochannel_enabled = true;
  }


  if (isset($_POST['startdhcpd'])) {
    if (CSRFValidate()) {
      if ($dnsmasq_state) {
        $status->addMessage('dnsmasq already running', 'info');
      } else {
        exec('sudo /etc/init.d/dnsmasq start', $dnsmasq, $return);
        if ($return == 0) {
          $status->addMessage('Successfully started dnsmasq', 'success');
          $dnsmasq_state = true;
        } else {
          $status->addMessage('Failed to start dnsmasq', 'danger');
        }
      }
    } else {
      error_log('CSRF violation');
    }
  } elseif (isset($_POST['stopdhcpd'])) {
    if (CSRFValidate()) {
      if ($dnsmasq_state) {
        exec('sudo /etc/init.d/dnsmasq stop', $dnsmasq, $return);
        if ($return == 0) {
          $status->addMessage('Successfully stopped dnsmasq', 'success');
          $dnsmasq_state = false;
        } else {
          $status->addMessage('Failed to stop dnsmasq', 'danger');
        }
      } else {
        $status->addMessage('dnsmasq already stopped', 'info');
      }
    } else {
      error_log('CSRF violation');
    }
  } else {
    if ($dnsmasq_state) {
      $status->addMessage('Dnsmasq is running', 'success');
    } else {
      $status->addMessage('Dnsmasq is not running', 'warning');
    }
  }

  exec('cat ' . RASPI_DNSMASQ_CONFIG, $return);
  $conf = ParseConfig($return);
  $arrRange = explode(",", $conf['dhcp-range']);
  $RangeStart = $arrRange[0];
  $RangeEnd = $arrRange[1];
  $RangeMask = $arrRange[2];
  $leaseTime = $arrRange[3];

  $hselected = '';
  $mselected = '';
  $dselected = '';
  $infiniteselected = '';
  preg_match('/([0-9]*)([a-z])/i', $leaseTime, $arrRangeLeaseTime);
  if ($leaseTime === 'infinite') {
    $infiniteselected = ' selected="selected"';
  } else {
    switch ($arrRangeLeaseTime[2]) {
      case 'h':
        $hselected = ' selected="selected"';
        break;
      case 'm':
        $mselected = ' selected="selected"';
        break;
      case 'd':
        $dselected = ' selected="selected"';
        break;
    }
  }
  ?>
  <div class="row">
    <div class="col-lg-12">
      <div class="panel panel-primary">
        <div class="panel-heading"><i class="fa fa-dot-circle-o fa-fw"></i> <?php echo _("Configure hotspot"); ?></div>
        <!-- /.panel-heading -->
        <div class="panel-body">
          <p><?php $status->showMessages(); ?></p>
          <form role="form" action="?page=hotspot_conf" method="POST">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs">
              <li class="active"><a href="#basic" data-toggle="tab"><?php echo _("Basic"); ?></a></li>
              <li><a href="#security" data-toggle="tab"><?php echo _("Security"); ?></a></li>
              <li><a href="#dhcp-settings" data-toggle="tab"><?php echo _("DHCP Settings"); ?></a></li>
              <li><a href="#dhcp-clients" data-toggle="tab"><?php echo _("DHCP Clients"); ?></a></li>
            </ul>

            <!-- Tab panes -->
            <div class="tab-content">
              <div class="tab-pane fade in active" id="basic">

                <h4><?php echo _("Basic settings"); ?></h4>
                <?php CSRFToken() ?>
                <div class="row">
                  <div class="form-group col-md-4">
                    <label for="txtssid"><?php echo _("SSID"); ?></label>
                    <input type="text" id="txtssid" class="form-control" name="ssid" value="<?php echo htmlspecialchars($arrConfig['ssid'], ENT_QUOTES); ?>" />
                  </div>
                </div>
                <div class="row">
                  <div class="form-group col-md-4">
                    <label for="cbxhwmode"><?php echo _("Wireless Mode"); ?></label>
                    <?php
                    $selectedHwMode = $arrConfig['hw_mode'];
                    if (isset($arrConfig['ieee80211n'])) {
                      if (strval($arrConfig['ieee80211n']) === '1') {
                        $selectedHwMode = 'n';
                      }
                    }

                    SelectorOptions('hw_mode', $arr80211Standard, $selectedHwMode, 'cbxhwmode'); ?>
                  </div>
                </div>
                <div class="row" style="display: flex; display: -webkit-flex; flex-wrap: wrap; align-items: center;">
                  <div class="form-group col-md-4">
                    <label for="cbxchannel"><?php echo _("Channel"); ?></label>
                    <?php
                    $selectablechannels = range(1, 13);
                    $countries_2_4Ghz_max11ch = array(
                      'AG', 'BS', 'BB', 'BZ', 'CR', 'CU', 'DM', 'DO', 'SV', 'GD', 'GT',
                      'HT', 'HN', 'JM', 'MX', 'NI', 'PA', 'KN', 'LC', 'VC', 'TT',
                      'US', 'CA', 'UZ', 'CO'
                    );
                    $countries_2_4Ghz_max14ch = array('JA');
                    if (in_array($arrConfig['country_code'], $countries_2_4Ghz_max11ch)) {
                      // In North America till channel 11 is the maximum allowed wi-fi 2.4Ghz channel.
                      // Except for the US that allows channel 12 & 13 in low power mode with additional restrictions.
                      // Canada that allows channel 12 in low power mode. Because it's unsure if low powered mode
                      // can be supported the channels are not selectable for those countries.
                      // source: https://en.wikipedia.org/wiki/List_of_WLAN_channels#Interference_concerns
                      // Also Uzbekistan and Colombia allow to select till channel 11 as maximum channel on the 2.4Ghz wi-fi band.
                      $selectablechannels = range(1, 11);
                    } elseif (in_array($arrConfig['country_code'], $countries_2_4Ghz_max14ch)) {
                      if ($arrConfig['hw_mode'] === 'b') {
                        $selectablechannels = range(1, 14);
                      }
                    }
                    SelectorOptions('channel', $selectablechannels, intval($arrConfig['channel']), 'cbxchannel'); ?>
                  </div>
                  <div class="col-md-4">
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" name="acs" id="acs" <?php if($autochannel_enabled){echo('checked="true"');} ?>>
                      <label class="form-check-label" for="acs">Enable Automatic channel selection</label>
                    </div>
                    <script type="text/javascript">
                      function onAcsClick(){
                        var acsEnabled = document.getElementById("acs").checked
                        if(acsEnabled){
                          document.getElementById("cbxchannel").setAttribute("readonly", true)
                        } else {
                          document.getElementById("cbxchannel").setAttribute("readonly", false)
                        }
                      }
                      document.getElementById("acs").onchange = onAcsClick
                      onAcsClick();
                    </script>
                  </div>
                </div>
                <div class="row">
                  <div class="form-group col-md-4">
                    <label for="cbxcountries"><?php echo _("Country Code"); ?></label>
                    <input type="hidden" id="selected_country" value="<?php echo htmlspecialchars($arrConfig['country_code'], ENT_QUOTES); ?>">
                    <select class="form-control" id="cbxcountries" name="country_code">
                      <option value="AF">Afghanistan</option>
                      <option value="AX">Åland Islands</option>
                      <option value="AL">Albania</option>
                      <option value="DZ">Algeria</option>
                      <option value="AS">American Samoa</option>
                      <option value="AD">Andorra</option>
                      <option value="AO">Angola</option>
                      <option value="AI">Anguilla</option>
                      <option value="AQ">Antarctica</option>
                      <option value="AG">Antigua and Barbuda</option>
                      <option value="AR">Argentina</option>
                      <option value="AM">Armenia</option>
                      <option value="AW">Aruba</option>
                      <option value="AU">Australia</option>
                      <option value="AT">Austria</option>
                      <option value="AZ">Azerbaijan</option>
                      <option value="BS">Bahamas</option>
                      <option value="BH">Bahrain</option>
                      <option value="BD">Bangladesh</option>
                      <option value="BB">Barbados</option>
                      <option value="BY">Belarus</option>
                      <option value="BE">Belgium</option>
                      <option value="BZ">Belize</option>
                      <option value="BJ">Benin</option>
                      <option value="BM">Bermuda</option>
                      <option value="BT">Bhutan</option>
                      <option value="BO">Bolivia, Plurinational State of</option>
                      <option value="BQ">Bonaire, Sint Eustatius and Saba</option>
                      <option value="BA">Bosnia and Herzegovina</option>
                      <option value="BW">Botswana</option>
                      <option value="BV">Bouvet Island</option>
                      <option value="BR">Brazil</option>
                      <option value="IO">British Indian Ocean Territory</option>
                      <option value="BN">Brunei Darussalam</option>
                      <option value="BG">Bulgaria</option>
                      <option value="BF">Burkina Faso</option>
                      <option value="BI">Burundi</option>
                      <option value="KH">Cambodia</option>
                      <option value="CM">Cameroon</option>
                      <option value="CA">Canada</option>
                      <option value="CV">Cape Verde</option>
                      <option value="KY">Cayman Islands</option>
                      <option value="CF">Central African Republic</option>
                      <option value="TD">Chad</option>
                      <option value="CL">Chile</option>
                      <option value="CN">China</option>
                      <option value="CX">Christmas Island</option>
                      <option value="CC">Cocos (Keeling) Islands</option>
                      <option value="CO">Colombia</option>
                      <option value="KM">Comoros</option>
                      <option value="CG">Congo</option>
                      <option value="CD">Congo, the Democratic Republic of the</option>
                      <option value="CK">Cook Islands</option>
                      <option value="CR">Costa Rica</option>
                      <option value="CI">Côte d'Ivoire</option>
                      <option value="HR">Croatia</option>
                      <option value="CU">Cuba</option>
                      <option value="CW">Curaçao</option>
                      <option value="CY">Cyprus</option>
                      <option value="CZ">Czech Republic</option>
                      <option value="DK">Denmark</option>
                      <option value="DJ">Djibouti</option>
                      <option value="DM">Dominica</option>
                      <option value="DO">Dominican Republic</option>
                      <option value="EC">Ecuador</option>
                      <option value="EG">Egypt</option>
                      <option value="SV">El Salvador</option>
                      <option value="GQ">Equatorial Guinea</option>
                      <option value="ER">Eritrea</option>
                      <option value="EE">Estonia</option>
                      <option value="ET">Ethiopia</option>
                      <option value="FK">Falkland Islands (Malvinas)</option>
                      <option value="FO">Faroe Islands</option>
                      <option value="FJ">Fiji</option>
                      <option value="FI">Finland</option>
                      <option value="FR">France</option>
                      <option value="GF">French Guiana</option>
                      <option value="PF">French Polynesia</option>
                      <option value="TF">French Southern Territories</option>
                      <option value="GA">Gabon</option>
                      <option value="GM">Gambia</option>
                      <option value="GE">Georgia</option>
                      <option value="DE">Germany</option>
                      <option value="GH">Ghana</option>
                      <option value="GI">Gibraltar</option>
                      <option value="GR">Greece</option>
                      <option value="GL">Greenland</option>
                      <option value="GD">Grenada</option>
                      <option value="GP">Guadeloupe</option>
                      <option value="GU">Guam</option>
                      <option value="GT">Guatemala</option>
                      <option value="GG">Guernsey</option>
                      <option value="GN">Guinea</option>
                      <option value="GW">Guinea-Bissau</option>
                      <option value="GY">Guyana</option>
                      <option value="HT">Haiti</option>
                      <option value="HM">Heard Island and McDonald Islands</option>
                      <option value="VA">Holy See (Vatican City State)</option>
                      <option value="HN">Honduras</option>
                      <option value="HK">Hong Kong</option>
                      <option value="HU">Hungary</option>
                      <option value="IS">Iceland</option>
                      <option value="IN">India</option>
                      <option value="ID">Indonesia</option>
                      <option value="IR">Iran, Islamic Republic of</option>
                      <option value="IQ">Iraq</option>
                      <option value="IE">Ireland</option>
                      <option value="IM">Isle of Man</option>
                      <option value="IL">Israel</option>
                      <option value="IT">Italy</option>
                      <option value="JM">Jamaica</option>
                      <option value="JP">Japan</option>
                      <option value="JE">Jersey</option>
                      <option value="JO">Jordan</option>
                      <option value="KZ">Kazakhstan</option>
                      <option value="KE">Kenya</option>
                      <option value="KI">Kiribati</option>
                      <option value="KP">Korea, Democratic People's Republic of</option>
                      <option value="KR">Korea, Republic of</option>
                      <option value="KW">Kuwait</option>
                      <option value="KG">Kyrgyzstan</option>
                      <option value="LA">Lao People's Democratic Republic</option>
                      <option value="LV">Latvia</option>
                      <option value="LB">Lebanon</option>
                      <option value="LS">Lesotho</option>
                      <option value="LR">Liberia</option>
                      <option value="LY">Libya</option>
                      <option value="LI">Liechtenstein</option>
                      <option value="LT">Lithuania</option>
                      <option value="LU">Luxembourg</option>
                      <option value="MO">Macao</option>
                      <option value="MK">Macedonia, the former Yugoslav Republic of</option>
                      <option value="MG">Madagascar</option>
                      <option value="MW">Malawi</option>
                      <option value="MY">Malaysia</option>
                      <option value="MV">Maldives</option>
                      <option value="ML">Mali</option>
                      <option value="MT">Malta</option>
                      <option value="MH">Marshall Islands</option>
                      <option value="MQ">Martinique</option>
                      <option value="MR">Mauritania</option>
                      <option value="MU">Mauritius</option>
                      <option value="YT">Mayotte</option>
                      <option value="MX">Mexico</option>
                      <option value="FM">Micronesia, Federated States of</option>
                      <option value="MD">Moldova, Republic of</option>
                      <option value="MC">Monaco</option>
                      <option value="MN">Mongolia</option>
                      <option value="ME">Montenegro</option>
                      <option value="MS">Montserrat</option>
                      <option value="MA">Morocco</option>
                      <option value="MZ">Mozambique</option>
                      <option value="MM">Myanmar</option>
                      <option value="NA">Namibia</option>
                      <option value="NR">Nauru</option>
                      <option valu      var_dump($return);e="NP">Nepal</option>
                      <option value="NL">Netherlands</option>
                      <option value="NC">New Caledonia</option>
                      <option value="NZ">New Zealand</option>
                      <option value="NI">Nicaragua</option>
                      <option value="NE">Niger</option>
                      <option value="NG">Nigeria</option>
                      <option value="NU">Niue</option>
                      <option value="NF">Norfolk Island</option>
                      <option value="MP">Northern Mariana Islands</option>
                      <option value="NO">Norway</option>
                      <option value="OM">Oman</option>
                      <option value="PK">Pakistan</option>
                      <option value="PW">Palau</option>
                      <option value="PS">Palestinian Territory, Occupied</option>
                      <option value="PA">Panama</option>
                      <option value="PG">Papua New Guinea</option>
                      <option value="PY">Paraguay</option>
                      <option value="PE">Peru</option>
                      <option value="PH">Philippines</option>
                      <option value="PN">Pitcairn</option>
                      <option value="PL">Poland</option>
                      <option value="PT">Portugal</option>
                      <option value="PR">Puerto Rico</option>
                      <option value="QA">Qatar</option>
                      <option value="RE">Réunion</option>
                      <option value="RO">Romania</option>
                      <option value="RU">Russian Federation</option>
                      <option value="RW">Rwanda</option>
                      <option value="BL">Saint Barthélemy</option>
                      <option value="SH">Saint Helena, Ascension and Tristan da Cunha</option>
                      <option value="KN">Saint Kitts and Nevis</option>
                      <option value="LC">Saint Lucia</option>
                      <option value="MF">Saint Martin (French part)</option>
                      <option value="PM">Saint Pierre and Miquelon</option>
                      <option value="VC">Saint Vincent and the Grenadines</option>
                      <option value="WS">Samoa</option>
                      <option value="SM">San Marino</option>
                      <option value="ST">Sao Tome and Principe</option>
                      <option value="SA">Saudi Arabia</option>
                      <option value="SN">Senegal</option>
                      <option value="RS">Serbia</option>
                      <option value="SC">Seychelles</option>
                      <option value="SL">Sierra Leone</option>
                      <option value="SG">Singapore</option>
                      <option value="SX">Sint Maarten (Dutch part)</option>
                      <option value="SK">Slovakia</option>
                      <option value="SI">Slovenia</option>
                      <option value="SB">Solomon Islands</option>
                      <option value="SO">Somalia</option>
                      <option value="ZA">South Africa</option>
                      <option value="GS">South Georgia and the South Sandwich Islands</option>
                      <option value="SS">South Sudan</option>
                      <option value="ES">Spain</option>
                      <option value="LK">Sri Lanka</option>
                      <option value="SD">Sudan</option>
                      <option value="SR">Suriname</option>
                      <option value="SJ">Svalbard and Jan Mayen</option>
                      <option value="SZ">Swaziland</option>
                      <option value="SE">Sweden</option>
                      <option value="CH">Switzerland</option>
                      <option value="SY">Syrian Arab Republic</option>
                      <option value="TW">Taiwan, Province of China</option>
                      <option value="TJ">Tajikistan</option>
                      <option value="TZ">Tanzania, United Republic of</option>
                      <option value="TH">Thailand</option>
                      <option value="TL">Timor-Leste</option>
                      <option value="TG">Togo</option>
                      <option value="TK">Tokelau</option>
                      <option value="TO">Tonga</option>
                      <option value="TT">Trinidad and Tobago</option>
                      <option value="TN">Tunisia</option>
                      <option value="TR">Turkey</option>
                      <option value="TM">Turkmenistan</option>
                      <option value="TC">Turks and Caicos Islands</option>
                      <option value="TV">Tuvalu</option>
                      <option value="UG">Uganda</option>
                      <option value="UA">Ukraine</option>
                      <option value="AE">United Arab Emirates</option>
                      <option value="GB">United Kingdom</option>
                      <option value="US">United States</option>
                      <option value="UM">United States Minor Outlying Islands</option>
                      <option value="UY">Uruguay</option>
                      <option value="UZ">Uzbekistan</option>
                      <option value="VU">Vanuatu</option>
                      <option value="VE">Venezuela, Bolivarian Republic of</option>
                      <option value="VN">Viet Nam</option>
                      <option value="VG">Virgin Islands, British</option>
                      <option value="VI">Virgin Islands, U.S.</option>
                      <option value="WF">Wallis and Futuna</option>
                      <option value="EH">Western Sahara</option>
                      <option value="YE">Yemen</option>
                      <option value="ZM">Zambia</option>
                      <option value="ZW">Zimbabwe</option>
                    </select>
                    <script type="text/javascript">
                      var country = document.getElementById("selected_country").value;
                      var countries = document.getElementById("cbxcountries");
                      var ops = countries.getElementsByTagName("option");
                      for (var i = 0; i < ops.length; ++i) {
                        if (ops[i].value == country) {
                          ops[i].selected = true;
                          break;
                        }
                      }
                    </script>
                  </div>
                </div><!-- /.panel-body -->
              </div>
              <div class="tab-pane fade" id="security">
                <h4><?php echo _("Security settings"); ?></h4>
                <div class="row">
                  <div class="form-group col-md-4">
                    <label for="cbxwpa"><?php echo _("Security type"); ?></label>
                    <?php SelectorOptions('wpa', $arrSecurity, $arrConfig['wpa'], 'cbxwpa'); ?>
                  </div>
                </div>
                <div class="row">
                  <div class="form-group col-md-4">
                    <label for="cbxwpapairwise"><?php echo _("Encryption Type"); ?></label>
                    <?php SelectorOptions('wpa_pairwise', $arrEncType, $arrConfig['wpa_pairwise'], 'cbxwpapairwise'); ?>
                  </div>
                </div>
                <div class="row">
                  <div class="form-group col-md-4">
                    <label for="txtwpapassphrase"><?php echo _("PSK"); ?></label>
                    <input type="text" class="form-control" id="txtwpapassphrase" name="wpa_passphrase" value="<?php echo htmlspecialchars($arrConfig['wpa_passphrase'], ENT_QUOTES); ?>" />
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="dhcp-settings">
                <h4>DHCP server settings</h4>
                <?php CSRFToken() ?>

                <div class="row">
                  <div class="form-group col-md-4">
                    <label for="code"><?php echo _("Starting IP Address"); ?></label>
                    <input type="text" class="form-control" name="RangeStart" value="<?php echo htmlspecialchars($RangeStart, ENT_QUOTES); ?>" />
                  </div>
                </div>

                <div class="row">
                  <div class="form-group col-md-4">
                    <label for="code"><?php echo _("Ending IP Address"); ?></label>
                    <input type="text" class="form-control" name="RangeEnd" value="<?php echo htmlspecialchars($RangeEnd, ENT_QUOTES); ?>" />
                  </div>
                </div>

                <div class="row">
                  <div class="form-group col-xs-2 col-sm-3">
                    <label for="code"><?php echo _("Lease Time"); ?></label>
                    <input type="text" class="form-control" name="RangeLeaseTime" value="<?php echo htmlspecialchars($arrRangeLeaseTime[1], ENT_QUOTES); ?>" />
                  </div>
                  <div class="form-group col-xs-2 col-sm-3">
                    <label for="code"><?php echo _("Interval"); ?></label>
                    <select name="RangeLeaseTimeUnits" class="form-control">
                      <option value="m" <?php echo $mselected; ?>><?php echo _("Minute(s)"); ?></option>
                      <option value="h" <?php echo $hselected; ?>><?php echo _("Hour(s)"); ?></option>
                      <option value="d" <?php echo $dselected; ?>><?php echo _("Day(s)"); ?></option>
                      <option value="infinite" <?php echo $infiniteselected; ?>><?php echo _("Infinite"); ?></option>
                    </select>
                  </div>
                </div>

                <input type="submit" class="btn btn-outline btn-primary" value="<?php echo _("Save DHCP settings"); ?>" name="savedhcpdsettings" />
                <?php

                if ($dnsmasq_state) {
                  echo '<input type="submit" class="btn btn-warning" value="' . _("Stop dnsmasq") . '" name="stopdhcpd" />';
                } else {
                  echo '<input type="submit" class="btn btn-success" value="' .  _("Start dnsmasq") . '" name="startdhcpd" />';
                }
                ?>
                <div class="row">
                  <br/>
                  <br/>
                </div>
              </div><!-- /.tab-pane -->

              <div class="tab-pane fade in" id="dhcp-clients">
                <h4>Client list</h4>
                <div class="col-lg-12">
                  <div class="panel panel-default">
                    <div class="panel-heading"><?php echo _("Active DHCP leases"); ?></div>
                    <!-- /.panel-heading -->
                    <div class="panel-body">
                      <div class="table-responsive">
                        <table class="table table-hover">
                          <thead>
                            <tr>
                              <th><?php echo _("Expire time"); ?></th>
                              <th><?php echo _("MAC Address"); ?></th>
                              <th><?php echo _("IP Address"); ?></th>
                              <th><?php echo _("Host name"); ?></th>
                              <th><?php echo _("Client ID"); ?></th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php
                            exec('cat ' . RASPI_DNSMASQ_LEASES, $leases);
                            foreach ($leases as $lease) {
                              echo '              <tr>' . PHP_EOL;
                              $lease_items = explode(' ', $lease);
                              foreach ($lease_items as $lease_item) {
                                echo '                <td>' . htmlspecialchars($lease_item, ENT_QUOTES) . '</td>' . PHP_EOL;
                              }
                              echo '              </tr>' . PHP_EOL;
                            };
                            ?>
                          </tbody>
                        </table>
                      </div><!-- /.table-responsive -->
                    </div><!-- /.panel-body -->
                  </div><!-- /.panel -->
                </div><!-- /.col-lg-6 -->
              </div><!-- /.tab-pane -->


              <input type="submit" class="btn btn-outline btn-primary" name="SaveHostAPDSettings" value="<?php echo _("Save settings"); ?>" />
              <?php
              if ($hostapdstatus[0] == 0) {
                echo '<input type="submit" class="btn btn-success" name="StartHotspot" value="' . _("Start hotspot") . '"/>', PHP_EOL;
              } else {
                echo '<input type="submit" class="btn btn-warning" name="StopHotspot" value="' . _("Stop hotspot") . '"/>', PHP_EOL;
                echo '<input type="submit" class="btn btn-danger" name="RestartHotspot" value="' . _("Restart hotspot") . '"/>', PHP_EOL;
              };
              ?>
          </form>
        </div>
      </div><!-- /.panel-primary -->
      <div class="panel-footer"> <?php echo _("Information provided by hostapd"); ?></div>
    </div><!-- /.col-lg-12 -->
  </div><!-- /.row -->
<?php
}

function SaveHostAPDConfig($wpa_array, $enc_types, $modes, $status)
{
  // It should not be possible to send bad data for these fields so clearly
  // someone is up to something if they fail. Fail silently.
  if (!(array_key_exists($_POST['wpa'], $wpa_array) &&
    array_key_exists($_POST['wpa_pairwise'], $enc_types) &&
    in_array($_POST['hw_mode'], $modes))) {
    error_log("Attempting to set hostapd config with wpa='" . $_POST['wpa'] . "', wpa_pairwise='" . $_POST['wpa_pairwise'] . "' and hw_mode='" . $_POST['hw_mode'] . "'");  // FIXME: log injection
    return false;
  }

  if (!filter_var($_POST['channel'], FILTER_VALIDATE_INT)) {
    error_log("Attempting to set channel to invalid number.");
    return false;
  }

  if (intval($_POST['channel']) < 1 || intval($_POST['channel']) > 14) {
    error_log("Attempting to set channel to '" . $_POST['channel'] . "'");
    return false;
  }

  $good_input = true;

  // Verify input
  if (empty($_POST['ssid']) || strlen($_POST['ssid']) > 32) {
    // Not sure of all the restrictions of SSID
    $status->addMessage('SSID must be between 1 and 32 characters', 'danger');
    $good_input = false;
  }

  if (
    $_POST['wpa'] !== 'none' && (strlen($_POST['wpa_passphrase']) < 8 || strlen($_POST['wpa_passphrase']) > 63)
  ) {
    $status->addMessage('WPA passphrase must be between 8 and 63 characters', 'danger');
    $good_input = false;
  }


  $ignore_broadcast_ssid = '0';


  if (strlen($_POST['country_code']) !== 0 && strlen($_POST['country_code']) != 2) {
    $status->addMessage('Country code must be blank or two characters', 'danger');
    $good_input = false;
  }

  if ($good_input) {
    // Fixed values
    $config = 'driver=nl80211' . PHP_EOL;
    $config .= 'ctrl_interface=' . RASPI_HOSTAPD_CTRL_INTERFACE . PHP_EOL;
    $config .= 'ctrl_interface_group=0' . PHP_EOL;
    $config .= 'auth_algs=1' . PHP_EOL;
    $config .= 'wpa_key_mgmt=WPA-PSK' . PHP_EOL;
    $config .= 'beacon_int=100' . PHP_EOL;
    $config .= 'ssid=' . $_POST['ssid'] . PHP_EOL;
    $config .= 'channel=' . $_POST['channel'] . PHP_EOL;
    if ($_POST['hw_mode'] === 'n') {
      $config .= 'hw_mode=g' . PHP_EOL;
      $config .= 'ieee80211n=1' . PHP_EOL;
      // Enable basic Quality of service
      $config .= 'wme_enabled=1' . PHP_EOL;
    } else {
      $config .= 'hw_mode=' . $_POST['hw_mode'] . PHP_EOL;
      $config .= 'ieee80211n=0' . PHP_EOL;
    }
    $config .= 'wpa_passphrase=' . $_POST['wpa_passphrase'] . PHP_EOL;
    $config .= 'interface=' . RASPI_WIFI_HOTSPOT_INTERFACE . PHP_EOL;
    $config .= 'wpa=' . $_POST['wpa'] . PHP_EOL;
    $config .= 'wpa_pairwise=' . $_POST['wpa_pairwise'] . PHP_EOL;
    $config .= 'country_code=' . $_POST['country_code'] . PHP_EOL;
    $config .= 'ignore_broadcast_ssid=' . $ignore_broadcast_ssid . PHP_EOL;

    exec('echo "' . escapeshellarg($config) . '" > /tmp/hostapddata', $temp);
    system("sudo cp /tmp/hostapddata " . RASPI_HOSTAPD_CONFIG, $return);

    if ($return == 0) {
      $status->addMessage('Wifi Hotspot settings saved', 'success');
    } else {
      $status->addMessage('Unable to save wifi hotspot settings', 'danger');
    }
  } else {
    $status->addMessage('Unable to save wifi hotspot settings due to bad input', 'danger');
    return false;
  }

  return true;
}

<?php

include_once('includes/status_messages.php');
include_once('includes/hilink_connector.php');

define('BEST_RSRQ',-3);
define('WORST_RSRQ',-19.3);
$connector = NULL;
$SimStatuscode = array(255 => "No SIM card", 257 => "Ready", 260 => "PIN required", 261 => "PUK required");
// $SimStatuscode = array(1 => "Un");
$NetworkTypeCode = array(0 => "no service", 1 => "GSM", 2 => "GPRS", 3 => "EDGE", 4 => "WCDMA", 5 => "HSDPA", 6 => "HSUPA", 7 => "HSPA", 8 => "TDSCDMA", 9 => "HSPA +", 10 => "EVDO rev. 0", 11 => "EVDO rev. AND", 12 => "EVDO rev. B", 13 => "1xRTT", 14 => "UMB", 15 => "1xEVDV", 16 => "3xRTT", 17 => "HSPA + 64QAM", 18 => "HSPA + MIMO", 19 => "LTE", 21 => "IS95A", 22 => "IS95B", 23 => "CDMA1x", 24 => "EVDO rev. 0", 25 => "EVDO rev. AND", 26 => "EVDO rev. B", 27 => "Hybrid CDMA1x", 28 => "Hybrid EVDO rev. 0", 29 => "Hybrid EVDO rev. AND", 30 => "Hybrid EVDO rev. B", 31 => "EHRPD rev. 0", 32 => "EHRPD rev. AND", 33 => "EHRPD rev. B", 34 => "Hybrid EHRPD rev. 0", 35 => "Hybrid EHRPD rev. AND", 36 => "Hybrid EHRPD rev. B", 41 => "WCDMA", 42 => "HSDPA", 43 => "HSUPA", 44 => "HSPA", 45 => "HSPA +", 46 => "DC HSPA +", 61 => "TD SCDMA", 62 => "TD HSDPA", 63 => "TD HSUPA", 64 => "TD HSPA", 65 => "TD HSPA +", 81 => "802.16E", 101 => "LTE");
// $NetworkTypeCode = array(1 => "Un");
$ConnectionStatusCode = array(905 => "No signal", 902 => "Disconnected", 901 => "Connected");
// $ConnectionStatusCode = array(1 => "Un");
function getParsedEndpoint($endpoint){
    global $connector;
    if(!isset($connector) || $connector == NULL){
        return NULL;
    }
    $xmlData = $connector->get(HilinkConnector::HILINK_BASEURL.$endpoint);
    $data = new SimpleXMLElement($xmlData);
    return $data;
}
function DisplayMobileConfig()
{
    global $connector;
    global $SimStatuscode;
    global $NetworkTypeCode;
    global $ConnectionStatusCode;
   
    $status = new StatusMessages();
    $displayInfos = array();
    try{
        $connector = new HilinkConnector();
        foreach($connector->warnings as $warning){
            $status->addMessage($warning, "warning");
        }

        if(isset($_POST['validatePIN'])){
            if (CSRFValidate()) {
                if(is_numeric($_POST['pin'])){ //If pin is not numeric then crash silently
                    $xmlret = $connector->validate_pin($_POST['pin']);
                    $ret = new SimpleXMLElement($xmlret);
                    switch($ret->getName()){
                        case "error":
                            if($ret->code == "103002"){
                                $pinStatus = getParsedEndpoint(HilinkConnector::HILINK_URL_PINSTATUS);
                                if( $pinStatus->SimState == 260){
                                    $status->addMessage("Invalid PIN code", "danger");
                                }
                            } else {
                                $status->addMessage("Error " . $ret->code, "danger");
                            }
                        case "response":
                            if($ret[0] == "OK"){
                                $status->addMessage("Authentication successful", "success");
                            }
                    }
                }
            } else {
                error_log('CSRF violation');
                $status->addMessage("CSRF Violation", "danger");
            }
        }

        $information = getParsedEndpoint(HilinkConnector::HILINK_URL_INFOS);
        $displayInfos['DeviceName'] = $information->DeviceName;
        $pinStatus = getParsedEndpoint(HilinkConnector::HILINK_URL_PINSTATUS);
        $displayInfos['SimStatus'] = (int)$pinStatus->SimState;
        $displayInfos['SimPinTimes'] = $pinStatus->SimPinTimes;
        $monitoringStatus = getParsedEndpoint(HilinkConnector::HILINK_URL_MONITORINGSTATUS);
        $displayInfos['ConnectionStatus'] = (int)$monitoringStatus->ConnectionStatus;
        switch($displayInfos['ConnectionStatus']){
            case 902:
                $status->addMessage('The adapter seems to be disconnected. If the problem persists after a few minutes please manually check the profiles on the dongle at <a href="http://192.168.8.1/html/profilesmgr.html" class="alert-link">192.168.8.1</a>', "warning");
                break;
            case 905:
                $status->addMessage('The mobile signal is too weak to establish connection', "error");
        }
        $displayInfos['NetworkType'] = (int)$monitoringStatus->CurrentNetworkType;
        $signal = getParsedEndpoint(HilinkConnector::HILINK_URL_SIGNAL);
        if($signal != NULL && $rsrq > -3){
            $rsrq = abs($signal->rsrq);
            $percent = 100 * (1 - ($rsrq - abs(BEST_RSRQ) / (abs(WORST_RSRQ) - abs(BEST_RSRQ))));
            $displayInfos['quality'] = $percent.'%';
        }
        else{
            $displayInfos['quality'] = 'Unknown';
        }

    }catch(RuntimeException $ex){
        $status->addMessage($ex->getMessage(), "danger");
    }
    ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <i class="fa fa-mobile fa-fw"></i><?php echo _("Configure Mobile Network"); ?>
                </div>
                <div class="panel-body">
                    <p><?php $status->showMessages(); ?></p>
                    <div id="mobileContent">
                        <div class="row">
                            <div class="col-lg-6">
                                <h4>Device information</h4>
                                <div class="info-item">Device model</div>
                                <i><?=$displayInfos['DeviceName']??"Unknown" ?></i>
                                <br/>
                                <div class="info-item">Connection Status</div>
                                <i><?=$ConnectionStatusCode[$displayInfos['ConnectionStatus']]??"Unknown (error)" ?></i>
                                <br/>
                                <div class="info-item">Network Type</div>
                                <i><?=$NetworkTypeCode[$displayInfos['NetworkType']]??"Unknown (error)" ?></i>
                                <br/>
                                <div class="info-item">Sim status</div>
                                <i><?=$SimStatuscode[$displayInfos['SimStatus']]??"Unknown (error)" ?></i>
                                <br/>
                                <div class="info-item">Signal quality</div>
                                <i><?=$displayInfos['quality']?></i>
                                <br/>
                                <br/>
                                <?php
                                if(isset($connector) && $connector != NULL)
                                {
                                    switch($displayInfos['SimStatus']){
                                        case 255: //no sim
                                            ?>
                                            <div class="alert alert-danger">
                                                Please insert a SIM card into the adapter.
                                            </div>
                                            <?php
                                            break;
                                        case 257: //Ready
                                            ?>
                                            <div class="alert alert-success">
                                                Your PIN code is valid
                                            </div>
                                            <?php
                                            break;
                                        case 260: //pin required
                                            ?>
                                            <br/>
                                            <div class="alert alert-warning">
                                                Please enter your PIN code. (<?=$displayInfos['SimPinTimes']?> tries remaining)
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <form id="pinForm" action="?page=mobile_network", method="POST">
                                                        <?php CSRFToken() ?>
                                                        <div class="form-group">
                                                            <label for="pin"><?php echo _("PIN"); ?></label>
                                                            <input type="number" class="form-control" name="pin" value=""/>
                                                        </div>
                                                        <div class="form-group">
                                                            <input type="submit" class="btn btn-outline btn-primary" name="validatePIN" value="Validate PIN">
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php
                                            break;
                                        case 261: //puk required
                                            ?>
                                            <div class="alert alert-danger">
                                                You have entered too many wrong PIN codes. 
                                                Please use the provided GUI of the adapter to unlock your SIM card.
                                                (Fatal error)
                                            </div>
                                            <?php
                                            break; 
                                        default:
                                            ?>
                                            <div class="alert alert-danger">
                                                An unknown error has occurred. Your SIM card is either incompatible or faulty.
                                            </div>
                                            <?php
                                        break;
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- panel-primary -->
        </div> <!-- col-lg-12 -->
    </div> <!-- row -->
    <?php
}



<?php
include('../../../includes/config.php');
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

set_time_limit(0);
ob_implicit_flush(1);
ob_end_flush();

$retry = 1;
if(isset($_GET['retry']) && is_numeric($_GET['retry'])){
    $retry = $_GET['retry'];
}

$timeout = 1;
if(isset($_GET['timeout']) && is_numeric($_GET['timeout'])){
    $timeout = $_GET['timeout'];
}

$ports = '{"ports":["22/udp","22/tcp","443/udp","443/tcp", "1194/udp", "1194/tcp", "8080/udp", "8080/tcp"], "connect_retry":' . $retry. ', "connect_timeout":'.$timeout.'}';
$cmd = TWINBRIDGE_DIR.'/bin/phpDetectPorts.py '.escapeshellarg($ports);
$py = popen($cmd, 'r');

while(!feof($py)){
    $line = fgets($py);
    $line = trim($line);
    echo 'data: '.$line.PHP_EOL;
    echo PHP_EOL;
    @ flush();
}

exec("sudo /bin/cp /tmp/ovpndata ". TWINBRIDGE_DIR.'/client.ovpn');
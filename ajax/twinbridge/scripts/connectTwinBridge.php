<?php
    session_start();
    include_once('../../../includes/config.php');
    include_once('../../../includes/functions.php');
    if(isset($_POST['username']) && isset($_POST['password']) && CSRFValidate()){
        session_write_close();    
        $username = $_POST['username'];
        $password = $_POST['password'];
        $output = null;
        $command = 'LOGIN='.escapeshellarg($username).' PASSWORD='.escapeshellarg($password).' sudo -E '.TWINBRIDGE_DIR.'/bin/phpConnect.py 2>&1';
	$output = shell_exec($command);
	$jsonoutput = json_decode($output, true);
	if($jsonoutput['error'] == false && isset($_POST['packetbridge']) && $_POST['packetbridge'] == true){
		$clientIP = $_SERVER['REMOTE_ADDR'];
		exec('sudo /bin/bash /etc/tbClient/bin/PacketBridge.sh vxlan0 '.$clientIP.':38000 cisco', $pid);
		if($pid[0] == "firewallerror"){
			$command = "sudo ".TWINBRIDGE_DIR."/bin/flush.sh";
			shell_exec($command);
			echo '{"error":true, "reason":"Bad firewall rule"}';
			die();
		}
		$pidfile = fopen('/tmp/packetbridge.pid', "w");
		fwrite($pidfile, $pid[0]);
		fclose($pidfile);
        }
        echo($output);
    } else {
            echo '"error":true, "reason":"Incorrect parameters"';
    }
    


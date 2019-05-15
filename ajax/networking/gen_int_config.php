<?php
session_start();
include_once('../../includes/config.php');
include_once('../../includes/functions.php');

if(isset($_POST['generate']) && isset($_POST['csrf_token']) && CSRFValidate()) {
	$output = gen_config();
	echo json_encode($output);
}

?>

<?php
    include("../../includes/config.php");
    exec("ls /sys/class/net | grep -v lo", $interfaces);
    $interfaces = array_diff($interfaces, RASPI_HIDDEN_INTERFACES);
    echo json_encode($interfaces);
?>

<?php
    session_start();
    include_once('../../includes/config.php');
    include_once('../../includes/functions.php');
    
    ?>
    <h4><?php echo _("Connection in progress");?></h4>
    <div class="btn-group btn-block">
      <a href="#" style="padding:10px;float: right;display: block;position: relative;margin-top: -55px;" class="col-md-2 btn btn-danger" id="kill" onclick="killVPN()" csrf="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);?>">Disconnect</a>
    </div>
    <div align="center">
        <img src="/img/loading.svg"> 
    </div>

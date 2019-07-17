<?php
    session_start();
    include("../../../includes/config.php");
    include("../../../includes/functions.php");
?>
    <p></p>
    <h4> <?php echo _("Create or join lab");?> </h4>
    <div class="btn-group btn-block">
      <a href="#" style="padding:10px;float: right;display: block;position: relative;margin-top: -55px;" class="col-md-2 btn btn-danger" id="kill" onclick="killVPN()" csrf="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);?>">Disconnect</a>
    </div>
    <form id="actionForm">
        <?php CSRFToken() ?>
        <a href="#" class="btn btn-outline btn-primary btn-lg btn-block actionBtn" id="createBtn"> <?php echo _("Create lab");?></a>
        <a href="#" class="btn btn-outline btn-primary btn-lg btn-block actionBtn" id="joinBtn"> <?php echo _("Join lab");?></a>
        <style>
            .actionBtn{
                padding: 1.5% 0;
            }
        </style> 
    </form>

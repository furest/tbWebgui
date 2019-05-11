<?php
    session_start();
    include("../../includes/config.php");
    include("../../includes/functions.php");
?>
</p>
<h4> <?php echo _("Connection");?></h4>
<form id="connectionForm">
    <?php CSRFToken() ?>
    <div class="row">
    <div class="form-group col-md-4">
        <label for="username"><?php echo _("Username"); ?></label>
        <input type="text" class="form-control" name="username"/>
    </div>
    </div>
    <div class="row">
    <div class="form-group col-md-4">
        <label for="password"><?php echo _("Password"); ?></label>
        <input type="password" class="form-control" name="password"/>
    </div>
    </div>
    <a href="#" class="btn btn-outline btn-primary" id="connectTB" name="ConnectTB"> <?php echo _("Connect"); ?></a>
</form> 


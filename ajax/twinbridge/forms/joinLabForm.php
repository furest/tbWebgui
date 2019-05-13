<?php
    session_start();
    include("../../../includes/config.php");
    include("../../../includes/functions.php");
?>
    <p></p>
    <h4>Create or join lab</h4>
    <div class="btn-group btn-block">
      <a href="#" style="padding:10px;float: right;display: block;position: relative;margin-top: -55px;" class="col-md-2 btn btn-danger" id="kill" onclick="killVPN()" csrf="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);?>">Disconnect</a>
    </div>
    <form id="joinForm">
        <?php CSRFToken();?>
        <div class="form-group">
            <div class="input-group col-xs-12 col-md-12">
              <span class="input-group-addon" id="pin">PIN code</span>
                  <input type="number" class="form-control" id="pin" name="pin">
                  <span class="input-group-btn">
                    	<button class="btn btn-default" id="join" type="button">Join</button>
                  </span>
            	</div>
        </div>  
    </form>
    <style>
 		.form-group{
            width: 30%;
        }
    </style>

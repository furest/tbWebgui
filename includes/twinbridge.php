<?php

include_once('includes/status_messages.php');

function DisplayTwinBridge($username, $password)
{
    $status = new StatusMessages();
?>
  <div class="row">
    <div class="col-lg-12">
      <div class="panel panel-primary">
        <div class="panel-heading"><i class="fa fa-plug fa-fw"></i><?php echo _("TwinBridge"); ?></div>
        <div class="panel-body" >
          <p><?php $status->showMessages(); ?></p>
            <div id="twinBridgeContent">
            </div>
        </div><!-- /.panel-body -->
      </div><!-- /.panel-primary -->
    </div><!-- /.col-lg-12 -->
  </div><!-- /.row -->
<?php
}


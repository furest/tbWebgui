<?php

include_once('includes/status_messages.php');

/**
 *
 *
 */
function DisplayNetworkingConfig()
{

  $status = new StatusMessages();

  exec("ls /sys/class/net | grep eth", $interfaces);
  $interfaces = array_diff($interfaces, RASPI_HIDDEN_INTERFACES);

  foreach ($interfaces as $interface) {
    exec("ip a show $interface", $$interface);
  }

  CSRFToken();
  ?>
  <div class="row">
    <div class="col-lg-12">
      <div class="panel panel-primary">
        <div class="panel panel-heading">
          <i class="fa fa-sitemap fa-fw"></i> <?php echo _("Configure networking"); ?></div>
        <div class="panel-body">
          <div id="msgNetworking"></div>
          <ul class="nav nav-tabs">
            <?php
            $indx = 0;
            foreach ($interfaces as $interface) {
              ?><li role="presentation" <?php if($indx==0){echo('class="active"');}?>><a href="#<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>" aria-controls="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>" role="tab" data-toggle="tab"><?php echo htmlspecialchars($interface, ENT_QUOTES); ?></a></li>
            <?php 
              $indx++;
              } ?>
          </ul>
          <div class="tab-content">
            <?php
            $indx = 0;
            foreach ($interfaces as $interface) { ?>
              <div role="tabpanel" class="tab-pane fade in <?php if($indx==0){echo('active');}?>" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>">
                <div class="row">
                  <div class="col-lg-6">
                    <form id="frm-<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>">
                      <!-- <form id="frm-<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>" method="POST" action="?page=network_conf"> -->
                      <div class="form-group">
                        <h4> <?php echo _("Adapter IP Address Settings") ?></h4>
                        <div class="btn-group" data-toggle="buttons">
                          <label class="btn btn-primary">
                            <input type="radio" name="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-addresstype" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dhcp" autocomplete="off"><?php echo _("DHCP"); ?>
                          </label>
                          <label class="btn btn-primary">
                            <input type="radio" name="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-addresstype" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-static" autocomplete="off"><?php echo _("Static IP"); ?>
                          </label>
                        </div><!-- /.btn-group -->
                        <h4><?php echo _("Enable Fallback to Static Option"); ?></h4>
                        <div class="btn-group" data-toggle="buttons">
                          <label class="btn btn-primary">
                            <input type="radio" name="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dhcpfailover" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-failover" autocomplete="off"><?php echo _("Enabled"); ?>
                          </label>
                          <label class="btn btn-warning">
                            <input type="radio" name="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dhcpfailover" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-nofailover" autocomplete="off"><?php echo _("Disabled"); ?>
                          </label>
                        </div><!-- /.btn-group -->
                      </div><!-- /.form-group -->
                      <hr />
                      <h4> <?php echo _("Static IP Options"); ?></h4>
                      <div class="form-group">
                        <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-ipaddress"><?php echo _("IP Address"); ?></label>
                        <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-ipaddress" placeholder="0.0.0.0">
                      </div>
                      <div class="form-group">
                        <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-netmask"> <?php echo _("Subnet Mask"); ?></label>
                        <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-netmask" placeholder="255.255.255.0">
                      </div>
                      <div class="form-group">
                        <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-gateway"> <?php echo _("Default Gateway"); ?></label>
                        <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-gateway" placeholder="0.0.0.0">
                      </div>
                      <div class="form-group">
                        <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dnssvr"> <?php echo _("DNS Server"); ?> </label>
                        <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dnssvr" placeholder="0.0.0.0">
                      </div>
                      <div class="form-group">
                        <label for="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dnssvralt"> <?php echo _("Alternate DNS Server"); ?></label>
                        <input type="text" class="form-control" id="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>-dnssvralt" placeholder="0.0.0.0">
                      </div>
                      <a href="#" class="btn btn-outline btn-primary intsave" data-int="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>"> <?php echo _("Save settings"); ?></a>
                      <a href="#" class="btn btn-warning intapply" data-int="<?php echo htmlspecialchars($interface, ENT_QUOTES); ?>"><?php echo _("Apply settings"); ?></a>
                    </form>
                  </div>
                </div><!-- /.tab-panel -->
              </div>
            <?php 
            $indx++;
          } ?>
          </div><!-- /.tab-content -->
        </div><!-- /.panel-body -->
        <div class="panel-footer"><?php echo _("Information provided by /sys/class/net"); ?></div>
      </div><!-- /.panel-primary -->
    </div><!-- /.col-lg-12 -->
  </div>
<?php } ?>
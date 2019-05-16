<?php

include_once('includes/status_messages.php');

function DisplayTwinBridge($username, $password)
{
	$status = new StatusMessages();
	if(isset($_POST['save'])){
		$savedRemotes = array();
		$error = false;
		foreach($_POST as $key=>$value){
			if(preg_match('/remote(\d+)-port/', $key, $match)){
				$index = $match[1];
				$port = $value;
				$protocol = strtolower($_POST['remote'.$index.'-protocol']);
				if(!is_numeric($port) || !in_array($protocol, ['tcp', 'udp'])){
					$error = true;
					break;
				}
				$savedRemotes[$index] = array(
					port => $port,
					protocol => $protocol
				);
			}
		}
		if($error){
			$status->addMessage('One of the remotes contains invalid data', 'danger');
		} else{
			$newConnect_timeout = 30;
			$newConnect_retry = 1;
			if(isset($_POST['connect-retry'])){
				$newConnect_retry = $_POST['connect-retry'];
			}
			if(isset($_POST['connect-timeout'])){
				$newConnect_timeout = $_POST['connect-timeout'];
			}
			ksort($savedRemotes);
			$remoteString = "";
			foreach($savedRemotes as $savedRemote){
				$remoteString .= "remote " . TWINBRIDGE_SERVER_HOSTNAME . " " . $savedRemote['port'] . " " . $savedRemote['protocol'] . PHP_EOL;
			}
			$template = file_get_contents(TWINBRIDGE_DIR. '/template.ovpn');
			
			$replaces = array(
				'${REMOTE_LIST}' => $remoteString,
				'${CONNECT_TIMEOUT}' => $newConnect_timeout,
				'${CONNECT_RETRY}' => $newConnect_retry
			);
			$newOvpn = strtr($template, $replaces);
			file_put_contents('/tmp/ovpndata', $newOvpn);
			exec("sudo cp /tmp/ovpndata " . TWINBRIDGE_DIR.'/client.ovpn');
			$status->addMessage('Openvpn file successfuly saved', 'success');
		}
	}

	$remotes = [];
	$content = file_get_contents(TWINBRIDGE_DIR. '/client.ovpn');
	if(preg_match_all('/\s*remote\s+([a-zA-Z0-0\.\-]+)\s+(\d+)\s+(tcp|udp)\s*/', $content, $foundRemotes, PREG_SET_ORDER)){
		foreach($foundRemotes as $remote){
			$newRemote = array(
							host => $remote[1],
							port => $remote[2],
							protocol => $remote[3]
						);
			$remotes[] = $newRemote;
		}
	}
	$connect_retry = 1;
	if(preg_match('/\s*connect-retry\s+(\d+)\s*/', $content, $found)){
		$connect_retry = $found[1];
	}
	$connect_timeout = 30;
	if(preg_match('/\s*connect-timeout\s+(\d+)\s*/', $content, $found)){
		$connect_timeout = $found[1];
	}

	?>
		<div class="row">
			<div class="col-lg-12">
				<div class="panel panel-primary">
					<div class="panel-heading"><i class="fa fa-plug fa-fw"></i><?php echo _("TwinBridge"); ?></div>
					<div class="panel-body" >
						<ul class="nav nav-tabs">
							<li class="<?php if(!isset($_POST['save'])){echo("active");}?>"><a href="#basic" data-toggle="tab" aria-expanded="<?php if(!isset($_POST['save'])){echo("true");}else{echo("false");}?>">Basic</a></li>
							<li class="<?php if(isset($_POST['save'])){echo("active");}?>"><a href="#settings" data-toggle="tab" aria-expanded="<?php if(isset($_POST['save'])){echo("true");}else{echo("false");}?>">Settings</a></li>
						</ul>
						<div class="tab-content">
							<div class="tab-pane fade <?php if(!isset($_POST['save'])){echo("active in");}?>" id="basic">
								<div id="twinBridgeContent">
								</div>
							</div>
							<div class="tab-pane fade <?php if(isset($_POST['save'])){echo("active in");}?>" id="settings">
								<p><?php $status->showMessages(); ?></p>
								<div class="row">	
									<div class="col-md-10" id="colProgress" hidden>
										<p></p>
										<div class="progress">
											<div id="detectProgress" class="progress-bar progress-bar-success progress-bar-striped" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%" role="progressbar">
												<span class="sr-only">0% Complete (success)</span>
											</div>
										</div>
									</div>
									<div class="col-md-2 pull-right">
										<a class="btn btn-block btn-info" id="autoDetectBtn" style="padding:10px;float: right;display: block;position: relative;"><?php echo _("Detect open ports");?></a>
									</div>
								</div>
								<form method="POST" action="?page=twinbridge">
								<?php CSRFToken();?>
									<div class="row">
										<div class="form-group col-md-3">
											<label for="connect-retry">Connect-retry</label>
											<input type="text" class="form-control" name="connect-retry" id="connect-retry" value="<?php echo $connect_retry;?>">
										</div>
									</div>
									<div class="row">
										<div class="form-group col-md-3">
											<label for="connect-timeout">Connect-timeout</label>
											<input type="text" class="form-control" name="connect-timeout" id="connect-timeout" value="<?php echo $connect_timeout;?>"">
										</div>
									</div>
									<div class="row">
										<div class="form-group col-md-3">
											<a class="btn btn-outline btn-outline btn-warning toggle-text" data-toggle="collapse" href="#remotes" id="remotespoiler">
												Show advanced settings
											</a>
										</div>
									</div>
									
									<div class="collapse" id="remotes">
										<?php foreach($remotes as $index=>$remote) { ?>
											<div class="row">
												<div class="col-md-3 form-group" >
													<div class="input-group remote" id="<?php echo $index; ?>">
														<span class="input-group-addon">remote</span>
														<input type="number" name="remote<?php echo $index; ?>-port" id="remote<?php echo $index; ?>-port"  class="form-control" style="display: inline-block;width: 50%; text-align:right;" placeholder="port" value="<?php echo $remote['port'];?>"/>
														<select class="form-control" name="remote<?php echo $index; ?>-protocol" id="remote<?php echo $index; ?>-protocol" style="display: inline-block;width: 35%;" >
															<option value="udp" <?php if(strtoupper($remote['protocol']) == "UDP"){echo "selected";} ?>>UDP</option>
															<option value="tcp" <?php if(strtoupper($remote['protocol']) == "TCP"){echo "selected";} ?>>TCP</option>
														</select> 
														<a class="btn btn-danger form-control" onclick="deleteRemote(<?php echo $index;?>)" style="display: inline-block;width: 15%;">
															<i class="fa fa-trash" aria-hidden="true"></i>
														</a>
													</div>
												</div>
											</div>
										<?php } ?>
										<div class="row">
											<div class="col-md-3 form-group" >
												<a class="btn btn-outline " id="addremote">
													+ Add remote
												</a>
											</div>
										</div>		
									</div>
									<div class="row">
										<div class="form-group col-md-3">
											<input type="submit" class="btn btn-outline btn-primary" name="save" value="Save Settings">
										</div>
									</div>			
								</form>
							</div>
						</div>
					</div><!-- /.panel-body -->
				</div><!-- /.panel-primary -->
			</div><!-- /.col-lg-12 -->
		</div><!-- /.row -->
	<?php
}


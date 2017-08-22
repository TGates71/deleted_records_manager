<?php
/**
 * Deleted Records Manager Module for Sentora
 * Version : 1.1.5
 * Author :  TGates
 * Email :  tgates@mach-hosting.com
 * Info : http://sentora.org
 */

function TruncateLogHook() {
	global $zdbh;
	// remove rows more than 2 hours old else log gets too large to load within a decent time frame.
	$sql = $zdbh->prepare("DELETE FROM x_logs WHERE lg_when_ts < (NOW() - INTERVAL 2 HOUR)");
	$sql->execute();
}

echo fs_filehandler::NewLine() . "Start Truncate DB Log Hook." . fs_filehandler::NewLine();
	if (ui_module::CheckModuleEnabled('Deleted Records Manager')){
		echo "Deleted Records Manager ENABLED..." . fs_filehandler::NewLine();
		echo "Truncating Database Server Log Started..." . fs_filehandler::NewLine();
		TruncateLogHook();
		echo "Truncating Database Server Log Finished..." . fs_filehandler::NewLine();
	} else {
		echo "Deleted Records Manager Module DISABLED." . fs_filehandler::NewLine();
	}
echo "END Truncate DB Log Hook." . fs_filehandler::NewLine();
?>
<?php
/**
 * Deleted Records Manager Module for Sentora
 * Version : 1.1.2
 * Author :  TGates
 * Email :  tgates@mach-hosting.com
 * Info : http://sentora.org
 */

// Regular functions
// Function to retrieve remote XML for update check
function check_remote_xml($xmlurl,$destfile){
	$feed = simplexml_load_file($xmlurl);
	if ($feed)
	{
		// $feed is valid, save it
		$feed->asXML($destfile);
	} elseif (file_exists($destfile)) {
		// $feed is not valid, grab the last backup
		$feed = simplexml_load_file($destfile);
	} else {
		die('Unable to retrieve XML file');
	}
}

// Deleted records exist notifier
	$records_exist = "&#42;";

class module_controller {

    static $complete;
    static $error;
    static $ok;
    static $edit;
    static $clientid;
    static $resetform;

/*START - Check for updates added by TGates*/
// Module update check functions
    static function getModuleVersion() {
        global $controller;

        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_version = $mod_config->document->version[0]->tagData;
        return "v".$module_version."";
    }
    
    static function getCheckUpdate() {
        global $controller;
        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_updateurl = $mod_config->document->updateurl[0]->tagData;
        $module_version = $mod_config->document->version[0]->tagData;

        // Download XML in Update URL and get Download URL and Version
        $myfile = self::getCheckRemoteXml($module_updateurl, $module_path."/" . $controller->GetControllerRequest('URL', 'module') . ".xml");
        $update_config = new xml_reader(fs_filehandler::ReadFileContents($module_path."/" . $controller->GetControllerRequest('URL', 'module') . ".xml"));
        $update_config->Parse();
        $update_url = $update_config->document->downloadurl[0]->tagData;
        $update_version = $update_config->document->latestversion[0]->tagData;

        if($update_version > $module_version)
            return true;
        return false;
    }

// Function to retrieve remote XML for update check
    static function getCheckRemoteXml($xmlurl,$destfile){
        $feed = simplexml_load_file($xmlurl);
        if ($feed)
        {
            // $feed is valid, save it
            $feed->asXML($destfile);
        } elseif (file_exists($destfile)) {
            // $feed is not valid, grab the last backup
            $feed = simplexml_load_file($destfile);
        } else {
            //die('Unable to retrieve XML file');
            echo('<div class="alert alert-danger">Unable to check for updates, your version may be outdated!.</div>');
        }
    }
/*END - Check for updates added by TGates*/

    static function ListClients($uid = 0) {
        global $zdbh;
            $sql = "SELECT * FROM x_accounts WHERE ac_deleted_ts IS NOT NULL";
            $numrows = $zdbh->prepare($sql);
            $numrows->bindParam(':uid', $uid);
            $numrows->execute();

        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            if ($uid == 0) {
                //do not bind as there is no need
            } else {
                //else we bind the pram to the sql statment
                $sql->bindParam(':uid', $uid);
            }
            $res = array();
            $sql->execute();
            while ($rowclients = $sql->fetch()) {
                if ($rowclients['ac_user_vc'] != "zadmin") {
;
                    $numrows = $zdbh->prepare("SELECT COUNT(*) FROM x_accounts WHERE ac_reseller_fk=:ac_id_pk AND ac_deleted_ts IS NULL");
                    $numrows->bindParam(':ac_id_pk', $rowclients['ac_id_pk']);
                    $numrows->execute();
                    $numrowclients = $numrows->fetch();

                    $currentuser = ctrl_users::GetUserDetail($rowclients['ac_id_pk']);
                    $currentuser['diskspacereadable'] = fs_director::ShowHumanFileSize(ctrl_users::GetQuotaUsages('diskspace', $currentuser['userid']));
                    $currentuser['diskspacequotareadable'] = fs_director::ShowHumanFileSize($currentuser['diskquota']);
                    $currentuser['bandwidthreadable'] = fs_director::ShowHumanFileSize(ctrl_users::GetQuotaUsages('bandwidth', $currentuser['userid']));
                    $currentuser['bandwidthquotareadable'] = fs_director::ShowHumanFileSize($currentuser['bandwidthquota']);
                    $currentuser['numclients'] = $numrowclients[0];
                    array_push($res, $currentuser);
                }
            }
            return $res;
        } else {
            return false;
        }
    }

    static function ListAllClients($moveid, $uid)
    {
        global $zdbh;
        $sql = "SELECT * FROM x_accounts WHERE ac_reseller_fk=:uid AND ac_deleted_ts IS NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':uid', $uid);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $sql->bindParam(':uid', $uid);
            $res = array();
            $skipclients = array();
            $sql->execute();
            while ($rowclients = $sql->fetch()) {
                $numrows = $zdbh->prepare("SELECT * FROM x_groups WHERE ug_id_pk=:ac_group_fk");
                $numrows->bindParam(':ac_group_fk', $rowclients['ac_group_fk']);
                $numrows->execute();
                $getgroup = $numrows->fetch();
                if ($rowclients['ac_id_pk'] != $moveid && $getgroup['ug_name_vc'] == "Administrators" ||
                        $rowclients['ac_id_pk'] != $moveid && $getgroup['ug_name_vc'] == "Resellers") {
                    array_push($res, array('moveclientid' => $rowclients['ac_id_pk'],
                        'moveclientname' => $rowclients['ac_user_vc']));
                }
            }
            return $res;
        } else {
            return false;
        }
    }

// get deleted records functions
    static function getListDeletedClients() {
        global $zdbh;
        $sql = "SELECT * FROM x_accounts WHERE ac_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_accounts WHERE ac_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'acid' => $rowmysql['ac_id_pk'],
					'acuser' => $rowmysql['ac_acc_fk'],
					'acname' => $rowmysql['ac_name_vc'],
					'acspace' => $rowmysql['ac_usedspace_bi'],
					'accreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['ac_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }
	
    static function getListDeletedDBs() {
        global $zdbh;
        $sql = "SELECT * FROM x_mysql_databases WHERE my_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_mysql_databases WHERE my_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'dbid' => $rowmysql['my_id_pk'],
					'dbuser' => $rowmysql['my_acc_fk'],
					'dbname' => $rowmysql['my_name_vc'],
					'dbspace' => $rowmysql['my_usedspace_bi'],
					'dbcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['my_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedDBusers() {
        global $zdbh;
        $sql = "SELECT * FROM x_mysql_users WHERE mu_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_mysql_users WHERE mu_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'dbuserid' => $rowmysql['mu_id_pk'],
					'dbusername' => $rowmysql['mu_name_vc'],
					'dbusercreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['mu_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedDNS() {
        global $zdbh;
        $sql = "SELECT * FROM x_dns WHERE dn_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_dns WHERE dn_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'dnid' => $rowmysql['dn_id_pk'],
					'dnuser' => $rowmysql['dn_acc_fk'],
					'dnname' => $rowmysql['dn_name_vc'],
					'dntype' => $rowmysql['dn_type_vc'],
					'dncreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['dn_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedVhosts() {
        global $zdbh;
        $sql = "SELECT * FROM x_vhosts WHERE vh_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_vhosts WHERE vh_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'vhid' => $rowmysql['vh_id_pk'],
					'vhuser' => $rowmysql['vh_acc_fk'],
					'vhname' => $rowmysql['vh_name_vc'],
					'vhcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['vh_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedFTPs() {
        global $zdbh;
        $sql = "SELECT * FROM x_ftpaccounts WHERE ft_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_ftpaccounts WHERE ft_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'ftpid' => $rowmysql['ft_id_pk'],
					'ftpaccname' => $rowmysql['ft_user_vc'],
					'ftpfolder' => $rowmysql['ft_directory_vc'],
					'ftpcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['ft_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedMboxes() {
        global $zdbh;
        $sql = "SELECT * FROM x_mailboxes WHERE mb_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_mailboxes WHERE mb_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'mbid' => $rowmysql['mb_id_pk'],
					'mbaddress' => $rowmysql['mb_address_vc'],
					'mbcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['mb_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedMalias() {
        global $zdbh;
        $sql = "SELECT * FROM x_aliases WHERE al_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_aliases WHERE al_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'alid' => $rowmysql['al_id_pk'],
					'aladdress' => $rowmysql['al_address_vc'],
					'aldestination' => $rowmysql['al_destination_vc'],
					'alcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['al_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedMfwd() {
        global $zdbh;
        $sql = "SELECT * FROM x_forwarders WHERE fw_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_forwarders WHERE fw_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'fwid' => $rowmysql['fw_id_pk'],
					'fwaddress' => $rowmysql['fw_address_vc'],
					'fwdestination' => $rowmysql['fw_destination_vc'],
					'fwcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['fw_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedDlist() {
        global $zdbh;
        $sql = "SELECT * FROM x_distlists WHERE dl_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_distlist WHERE dl_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'dlid' => $rowmysql['dl_id_pk'],
					'dladdress' => $rowmysql['dl_address_vc'],
					'dlcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['dl_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedDusers() {
        global $zdbh;
        $sql = "SELECT * FROM x_distlistusers WHERE du_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_distlistusers WHERE du_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'duid' => $rowmysql['du_id_pk'],
					'duaddress' => $rowmysql['du_address_vc'],
					'ducreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['du_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedCrons() {
        global $zdbh;
        $sql = "SELECT * FROM x_cronjobs WHERE ct_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_cronjobs WHERE ct_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'ctid' => $rowmysql['ct_id_pk'],
					'ctscript' => $rowmysql['ct_script_vc'],
					'ctpath' => $rowmysql['ct_fullpath_vc'],
					'ctdesc' => $rowmysql['ct_description_tx'],
					'ctcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['ct_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedPkgs() {
        global $zdbh;
        $sql = "SELECT * FROM x_packages WHERE pk_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_packages WHERE pk_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'pkid' => $rowmysql['pk_id_pk'],
					'pktitle' => $rowmysql['pk_name_vc'],
					'pkcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['pk_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedhtaccess() {
        global $zdbh;
        $sql = "SELECT * FROM x_htaccess WHERE ht_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_htaccess WHERE ht_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'htid' => $rowmysql['ht_id_pk'],
					'htacc' => $rowmysql['ht_acc_vc'],
					'htuser' => $rowmysql['ht_user_vc'],
					'htdir' => $rowmysql['ht_dir_vc'],
					'htcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['ht_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDeletedFAQs() {
        global $zdbh;
        $sql = "SELECT * FROM x_faqs WHERE fq_deleted_ts IS NOT NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_faqs WHERE fq_deleted_ts IS NOT NULL")->fetch();
                $res[] = array(
					'fqid' => $rowmysql['fq_id_pk'],
					'fqacc' => $rowmysql['fq_acc_fk'],
					'fqquestion' => $rowmysql['fq_question_tx'],
					'fqanswer' => $rowmysql['fq_answer_tx'],
					'fqglobal' => $rowmysql['fq_global_in'],
					'fqcreated' => $timestamp = gmdate("Y-m-d \T-H:i:s", $rowmysql['fq_created_ts'])
					);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getListDBLogs() {
        global $zdbh;
        $sql = "SELECT * FROM x_logs";
        $numrows = $zdbh->prepare($sql);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowmysql = $sql->fetch()) {
                $numrowdb = $zdbh->query("SELECT * FROM x_logs")->fetch();
                $res[] = array(
					'lgid' => $rowmysql['lg_id_pk'],
					'lguser' => $rowmysql['lg_user_fk'],
					'lgcode' => $rowmysql['lg_code_vc'],
					'lgmodule' => $rowmysql['lg_module_vc'],
					'lgdetail' => $rowmysql['lg_detail_tx'],
					'lgstack' => $rowmysql['lg_stack_tx'],
					'lgcreated' => $rowmysql['lg_when_ts']
					);
            }
            return $res;
        } else {
            return false;
        }
    }

// Delete records functions
    static function doDelete() {
        global $controller;
        runtime_csfr::Protect();
        $formvars = $controller->GetAllControllerRequests('FORM');
        if (self::ExecuteDelete($formvars['inDelete'], $formvars['inTable'], $formvars['inColumn'], $formvars['inNullCol']))
            return true;
        return false;
    }

    static function ExecuteDelete($delid, $table, $column, $delNullCol) {
        global $zdbh;
        $sql = $zdbh->prepare("
			DELETE FROM ".$table."
			WHERE ".$column." = :delid AND ".$delNullCol." IS NOT NULL");
        $sql->bindParam(':delid', $delid);
        $sql->execute();
		// Remove user profile if deleting client account
		if ($table == "ac_id_pk") {
			$sql = $zdbh->prepare("
			DELETE FROM x_profiles
			WHERE ud_user_fk = :delid LIMIT 1");
			$sql->bindParam(':delid', $delid);
			$sql->execute();
		}
		self::$ok = true;
        return true;
    }

// Truncate Logs functions
    static function doTruncate() {
        global $controller;
        runtime_csfr::Protect();
        $formvars = $controller->GetAllControllerRequests('FORM');
        if (self::ExecuteTruncate())
            return true;
        return false;
    }

    static function ExecuteTruncate() {
        global $zdbh;
        $sql = $zdbh->prepare("TRUNCATE TABLE x_logs");
        $sql->execute();
        self::$ok = true;
        return true;
    }

// set ID and table/column variables
    static function getDelID() {
        global $controller;
        if ($controller->GetControllerRequest('URL', 'other')) {
            $current = $controller->GetControllerRequest('URL', 'other');
            return $current['delid'];
        } else {
            return "";
        }
    }
	
    static function getTable() {
        global $controller;
		$formvars = $controller->GetAllControllerRequests('FORM');
        if ($formvars['inTable']) {
            $current = $formvars['inTable'];
            return $current['delTable'];
        } else {
            return "";
        }
    }

    static function getColumn() {
        global $controller;
		$formvars = $controller->GetAllControllerRequests('FORM');
        if ($formvars['inColumn']) {
            $current = $formvars['inColumn'];
            return $current['delColumn'];
        } else {
            return "";
        }
    }

    static function getNullCol() {
        global $controller;
		$formvars = $controller->GetAllControllerRequests('FORM');
        if ($formvars['inNullCol']) {
            $current = $formvars['inNullCol'];
            return $current['delNullCol'];
        } else {
            return "";
        }
    }

    static function getTabRef() {
        global $controller;
		$formvars = $controller->GetAllControllerRequests('FORM');
        if ($formvars['inTabRef']) {
            $current = $formvars['inTabRef'];
            return $current['TabRef'];
        } else {
            return "";
        }
    }

// Client list functions
    static function getClientList() {
        $currentuser = ctrl_users::GetUserDetail();
        $clientlist = self::ListClients($currentuser['userid']);
        if (!fs_director::CheckForEmptyValue($clientlist)) {
            return $clientlist;
        } else {
            return false;
        }
    }

    static function getAllClientList() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $urlvars = $controller->GetAllControllerRequests('URL');
        $clientlist = self::ListAllClients($urlvars['other'], $currentuser['userid']);
        if (!fs_director::CheckForEmptyValue($clientlist)) {
            return $clientlist;
        } else {
            return false;
        }
    }

    static function getCurrentClient() {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        $client = self::ListCurrentClient($urlvars['other']);
        if (!fs_director::CheckForEmptyValue($client)) {
            return $client;
        } else {
            return false;
        }
    }

// core static functions
    static function getModuleName() {
        $module_name = ui_module::GetModuleName();
        return $module_name;
    }

    static function getModuleIcon() {
        global $controller;
        $module_icon = "modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/icon.png";
        return $module_icon;
    }

    static function getModuleDesc() {
        $message = ui_language::translate(ui_module::GetModuleDescription());
        return $message;
    }

    static function getResult() {
        if (!fs_director::CheckForEmptyValue(self::$ok)) {
            return ui_sysmessage::shout(ui_language::translate("Record removed successfully!"), "zannounceok");
        }
        return;
    }

    static function getCSFR_Tag() {
        return runtime_csfr::Token();
    }

    static function getCopyright() {
		/* THIS COPYRIGHT NOTICE MAY NOT BE ALTERED IN ANY WAY OR REMOVED FOR ANY REASON WITHOUT WRITTEN PERMISSION OF THE AUTHOR. */
        $message = '<font face="ariel" size="2">'.ui_module::GetModuleName().' &copy; 2014-'.date("Y").' by <a target="_blank" href="http://forums.sentora.org/member.php?action=profile&uid=2">TGates</a> for <a target="_blank" href="http://sentora.org">Sentora Control Panel</a>&nbsp;&#8212;&nbsp;Help support future development of this module and donate today!</font>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="DW8QTHWW4FMBY">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" width="70" height="21" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>';
        return $message;
    }

}
?>
<?php
/**
 * Deleted Records Manager Module for Sentora
 * Version : 001
 * Author :  TGates
 * Email :  tgates@mach-hosting.com
 * Info : http://sentora.org
 */
class module_controller {

    static $complete;
    static $error;
    static $ok;
    static $edit;
    static $clientid;
    static $resetform;


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
					'duscript' => $rowmysql['du_address_vc'],
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

// Confirm functions
	// need to figure out how to pass the variables from the Delete form to the Confirm form
    static function getConfirm() {
        if (isset($_POST['inDelete'])) {
			return true;
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
		// add delete code to remove user's profile also (if is-user-account, del profile too)
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
}
?>
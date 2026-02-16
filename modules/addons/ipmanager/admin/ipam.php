<?php

/**
 * IPAM integration - NetBox: config and sync (pull/push).
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . "/../lib/ipam/NetBoxClient.php";
require_once __DIR__ . "/../lib/ipam/NetBoxSync.php";

$modulelink = $vars["modulelink"];
$LANG       = $vars["_lang"] ?? [];
include __DIR__ . "/_menu.php";

if (!Capsule::schema()->hasTable(ipmanager_table("ipam_mapping"))) {
    try {
        Capsule::schema()->create(ipmanager_table("ipam_mapping"), function ($table) {
            $table->increments("id");
            $table->string("entity_type", 32);
            $table->unsignedInteger("entity_id");
            $table->string("ipam_source", 32);
            $table->string("ipam_id", 64);
            $table->timestamps();
            $table->unique(["entity_type", "entity_id", "ipam_source"], "ipmanager_ipam_mapping_unique");
            $table->index(["ipam_source", "ipam_id"]);
        });
    } catch (Exception $e) {
        // table may already exist
    }
}

const IPAM_NETBOX_KEY = "netbox_ipam";

$saved = Capsule::table(ipmanager_table("integration_config"))->where("integration", IPAM_NETBOX_KEY)->first();
$config = $saved ? (array) json_decode($saved->config ?? "{}", true) : [];
$enabled = $saved && $saved->enabled;

$ipamMessage = null;
$ipamError = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["save_netbox"])) {
        $url = trim((string) ($_POST["netbox_url"] ?? ""));
        $token = trim((string) ($_POST["netbox_token"] ?? ""));
        $existingConfig = $saved ? (array) json_decode($saved->config ?? "{}", true) : [];
        if ($token === "" && isset($existingConfig["token"])) {
            $token = (string) $existingConfig["token"];
        }
        $siteId = trim((string) ($_POST["netbox_site_id"] ?? ""));
        $tenantId = trim((string) ($_POST["netbox_tenant_id"] ?? ""));
        $pushOnAssign = !empty($_POST["netbox_push_on_assign"]);
        $config = [
            "url"              => $url,
            "token"            => $token,
            "site_id"          => $siteId !== "" ? (int) $siteId : null,
            "tenant_id"        => $tenantId !== "" ? (int) $tenantId : null,
            "push_on_assign"   => $pushOnAssign,
        ];
        $enabled = !empty($_POST["netbox_enabled"]);
        $now = date("Y-m-d H:i:s");
        if ($saved) {
            Capsule::table(ipmanager_table("integration_config"))->where("integration", IPAM_NETBOX_KEY)->update([
                "config"     => json_encode($config),
                "enabled"    => $enabled,
                "updated_at" => $now,
            ]);
        } else {
            Capsule::table(ipmanager_table("integration_config"))->insert([
                "integration" => IPAM_NETBOX_KEY,
                "config"      => json_encode($config),
                "enabled"     => $enabled,
                "created_at"  => $now,
                "updated_at"  => $now,
            ]);
        }
        $ipamMessage = $LANG["ipam_saved"] ?? "NetBox configuration saved.";
    } elseif (isset($_POST["sync_from_netbox"])) {
        $saved = Capsule::table(ipmanager_table("integration_config"))->where("integration", IPAM_NETBOX_KEY)->first();
        $config = $saved ? (array) json_decode($saved->config ?? "{}", true) : [];
        if (empty($config["url"]) || empty($config["token"])) {
            $ipamError = $LANG["ipam_configure_first"] ?? "Configure NetBox URL and token first.";
        } else {
            $result = IpManagerNetBoxSync::pull($config);
            $ipamMessage = sprintf(
                $LANG["ipam_sync_result"] ?? "Subnets created: %d, updated: %d. IPs created: %d, updated: %d.",
                $result["subnets_created"],
                $result["subnets_updated"],
                $result["ips_created"],
                $result["ips_updated"]
            );
            if (!empty($result["errors"])) {
                $ipamError = implode(" ", array_slice($result["errors"], 0, 5));
            }
        }
    }
}

$config = $saved ? (array) json_decode($saved->config ?? "{}", true) : [];
$enabled = $saved && $saved->enabled;

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_ipam"] ?? "IPAM (NetBox)"); ?></div>
    <div class="panel-body">
        <?php if ($ipamMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($ipamMessage); ?></div>
        <?php endif; ?>
        <?php if ($ipamError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($ipamError); ?></div>
        <?php endif; ?>
        <p><?php echo htmlspecialchars($LANG["ipam_info"] ?? "Sync IP pools and addresses from NetBox. Configure NetBox URL and API token, then run Sync to pull prefixes as subnets and IP addresses. Optionally push assigned IPs back to NetBox when you assign in WHMCS."); ?></p>

        <h4><?php echo htmlspecialchars($LANG["ipam_netbox_config"] ?? "NetBox configuration"); ?></h4>
        <form method="post" class="form-horizontal">
            <input type="hidden" name="save_netbox" value="1">
            <div class="form-group">
                <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["ipam_netbox_url"] ?? "NetBox URL"); ?></label>
                <div class="col-sm-4">
                    <input type="url" name="netbox_url" class="form-control" placeholder="https://netbox.example.com"
                        value="<?php echo htmlspecialchars($config["url"] ?? ""); ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["ipam_netbox_token"] ?? "API Token"); ?></label>
                <div class="col-sm-4">
                    <input type="password" name="netbox_token" class="form-control" placeholder="<?php echo htmlspecialchars($LANG["ipam_token_placeholder"] ?? "Leave blank to keep existing"); ?>"
                        value="">
                </div>
                <div class="col-sm-4 help-block"><?php echo htmlspecialchars($LANG["ipam_token_help"] ?? "Leave blank to keep current token."); ?></div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["ipam_site_id"] ?? "Site ID"); ?></label>
                <div class="col-sm-4">
                    <input type="text" name="netbox_site_id" class="form-control" placeholder="<?php echo htmlspecialchars($LANG["ipam_optional"] ?? "Optional"); ?>"
                        value="<?php echo htmlspecialchars((string) ($config["site_id"] ?? "")); ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["ipam_tenant_id"] ?? "Tenant ID"); ?></label>
                <div class="col-sm-4">
                    <input type="text" name="netbox_tenant_id" class="form-control" placeholder="<?php echo htmlspecialchars($LANG["ipam_optional"] ?? "Optional"); ?>"
                        value="<?php echo htmlspecialchars((string) ($config["tenant_id"] ?? "")); ?>">
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-4">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="netbox_enabled" value="1" <?php echo $enabled ? " checked" : ""; ?>>
                        <?php echo htmlspecialchars($LANG["ipam_netbox_enabled"] ?? "Enable NetBox IPAM"); ?>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-4">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="netbox_push_on_assign" value="1" <?php echo !empty($config["push_on_assign"]) ? " checked" : ""; ?>>
                        <?php echo htmlspecialchars($LANG["ipam_push_on_assign"] ?? "Push to NetBox when assigning IP"); ?>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-4">
                    <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($LANG["save"] ?? "Save"); ?></button>
                </div>
            </div>
        </form>

        <hr>
        <h4><?php echo htmlspecialchars($LANG["ipam_sync_from_netbox"] ?? "Sync from NetBox"); ?></h4>
        <p class="text-muted"><?php echo htmlspecialchars($LANG["ipam_sync_help"] ?? "Pull all prefixes as subnets and their IP addresses. Existing mappings are updated; new ones created. NetBox status (active, reserved, etc.) is mapped to assigned/reserved/free."); ?></p>
        <form method="post">
            <button type="submit" name="sync_from_netbox" value="1" class="btn btn-success">
                <i class="fa fa-cloud-download"></i> <?php echo htmlspecialchars($LANG["ipam_sync_button"] ?? "Sync from NetBox"); ?>
            </button>
        </form>
    </div>
</div>

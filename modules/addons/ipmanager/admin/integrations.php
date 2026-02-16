<?php

/**
 * 3rd party integrations - cPanel, DirectAdmin, Plesk, Proxmox, SolusVM.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . "/../lib/integrations/BaseIntegration.php";
require_once __DIR__ . "/../lib/integrations/CpanelIntegration.php";
require_once __DIR__ . "/../lib/integrations/DirectAdminIntegration.php";
require_once __DIR__ . "/../lib/integrations/PleskIntegration.php";
require_once __DIR__ . "/../lib/integrations/ProxmoxIntegration.php";
require_once __DIR__ . "/../lib/integrations/SolusVmIntegration.php";

$modulelink = $vars["modulelink"];
$LANG       = $vars["_lang"] ?? [];
include __DIR__ . "/_menu.php";

$integrations = [
    "cpanel"       => IpManagerCpanelIntegration::class,
    "directadmin"  => IpManagerDirectAdminIntegration::class,
    "plesk"        => IpManagerPleskIntegration::class,
    "proxmox"      => IpManagerProxmoxIntegration::class,
    "solusvm"      => IpManagerSolusVmIntegration::class,
];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["integration"])) {
    $key = (string) $_POST["integration"];
    $enabled = !empty($_POST["enabled"]);
    if (isset($integrations[$key])) {
        $row = Capsule::table(ipmanager_table("integration_config"))->where("integration", $key)->first();
        $config = $row ? json_decode($row->config ?? "{}", true) : [];
        $config["enabled"] = $enabled;
        $now = date("Y-m-d H:i:s");
        if ($row) {
            Capsule::table(ipmanager_table("integration_config"))->where("integration", $key)->update([
                "config"     => json_encode($config),
                "enabled"    => $enabled,
                "updated_at" => $now,
            ]);
        } else {
            Capsule::table(ipmanager_table("integration_config"))->insert([
                "integration" => $key,
                "config"      => json_encode($config),
                "enabled"     => $enabled,
                "created_at"  => $now,
                "updated_at"  => $now,
            ]);
        }
        $integrationsMessage = $LANG["integration_saved"] ?? "Integration saved.";
    }
}

$saved = [];
foreach (Capsule::table(ipmanager_table("integration_config"))->get() as $r) {
    $saved[$r->integration] = ["enabled" => (bool) $r->enabled, "config" => json_decode($r->config ?? "{}", true)];
}

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_integrations"] ?? "Integrations"); ?></div>
    <div class="panel-body">
        <?php if (isset($integrationsMessage)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($integrationsMessage); ?></div>
        <?php endif; ?>
        <p><?php echo htmlspecialchars($LANG["integrations_info"] ?? "When you assign an IP to a service, IP Manager can push the IP to the server using the configured integration. Enable the integration that matches your server module (cPanel, DirectAdmin, Plesk, Proxmox, SolusVM)."); ?></p>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars($LANG["name"] ?? "Name"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["enabled"] ?? "Enabled"); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($integrations as $key => $class): ?>
                    <?php $name = $class::getName(); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td>
                            <form method="post" class="form-inline">
                                <input type="hidden" name="integration" value="<?php echo htmlspecialchars($key); ?>">
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="enabled" value="1" <?php echo !empty($saved[$key]["enabled"]) ? " checked" : ""; ?>>
                                </label>
                                <button type="submit" class="btn btn-xs btn-primary"><?php echo htmlspecialchars($LANG["save"] ?? "Save"); ?></button>
                            </form>
                        </td>
                        <td><?php echo htmlspecialchars($LANG["integration_push_on_assign"] ?? "Push IP on assign when server type matches"); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

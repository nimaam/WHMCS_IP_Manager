<?php

/**
 * Sync WHMCS product dedicated IPs with IP Manager.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

$modulelink = $vars["modulelink"];
$LANG       = $vars["_lang"] ?? [];
include __DIR__ . "/_menu.php";

$syncResult = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["run_sync"])) {
    $synced = 0;
    $skipped = 0;
    $created = 0;
    $errors = [];
    $liveOnly = !empty($_POST["sync_live_only"]);

    $query = Capsule::table("tblhosting")
        ->whereNotNull("dedicatedip")
        ->where("dedicatedip", "!=", "")
        ->select("id as service_id", "userid as client_id", "dedicatedip");
    if ($liveOnly) {
        $query->whereIn("status", ["Active", "Pending"]);
    }
    $rows = $query->get();

    foreach ($rows as $row) {
        $serviceId = (int) $row->service_id;
        $clientId  = (int) $row->client_id;
        $ip        = trim($row->dedicatedip);
        if ($ip === "") {
            $skipped++;
            continue;
        }

        $existing = Capsule::table(ipmanager_table("assignments") . " as a")
            ->join(ipmanager_table("ip_addresses") . " as ip", "ip.id", "=", "a.ip_address_id")
            ->where("a.service_id", $serviceId)
            ->where("ip.ip", $ip)
            ->first();
        if ($existing) {
            $skipped++;
            continue;
        }

        $ipRow = Capsule::table(ipmanager_table("ip_addresses"))->where("ip", $ip)->first();
        if ($ipRow) {
            if ($ipRow->status === "free") {
                if (ipmanager_assign_ip_to_service((int) $ipRow->id, $clientId, $serviceId, false)) {
                    $synced++;
                } else {
                    $errors[] = "Service #" . $serviceId . " IP " . $ip . ": assign failed";
                }
            } else {
                $skipped++;
            }
            continue;
        }

        $subnet = ipmanager_find_subnet_containing_ip($ip);
        if (!$subnet) {
            $errors[] = "Service #" . $serviceId . " IP " . $ip . ": no matching subnet";
            $skipped++;
            continue;
        }

        try {
            $now = date("Y-m-d H:i:s");
            $ipId = (int) Capsule::table(ipmanager_table("ip_addresses"))->insertGetId([
                "subnet_id"  => $subnet->id,
                "pool_id"    => null,
                "ip"         => $ip,
                "version"    => (int) $subnet->version,
                "status"     => "assigned",
                "created_at" => $now,
                "updated_at" => $now,
            ]);
            Capsule::table(ipmanager_table("assignments"))->insert([
                "ip_address_id" => $ipId,
                "client_id"     => $clientId,
                "service_id"    => $serviceId,
                "assigned_type" => "service",
                "assigned_at"   => $now,
                "created_at"    => $now,
                "updated_at"   => $now,
            ]);
            $created++;
            $synced++;
        } catch (Exception $e) {
            $errors[] = "Service #" . $serviceId . " IP " . $ip . ": " . $e->getMessage();
        }
    }

    $syncResult = [
        "synced"   => $synced,
        "created"  => $created,
        "skipped"  => $skipped,
        "errors"   => $errors,
    ];
}

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_sync"] ?? "Synchronize"); ?></div>
    <div class="panel-body">
        <p><?php echo htmlspecialchars($LANG["sync_info"] ?? "Synchronize IP addresses used by products in WHMCS with IP Manager subnets."); ?></p>
        <p class="text-muted"><?php echo htmlspecialchars($LANG["sync_help"] ?? "For each service with a dedicated IP in WHMCS: if the IP is already in IP Manager (any subnet), it will be assigned to that service; if not, it will be added to a subnet that contains it and then assigned. Services already correctly assigned are skipped."); ?></p>

        <?php if ($syncResult !== null): ?>
            <div class="alert alert-info">
                <strong><?php echo htmlspecialchars($LANG["sync_done"] ?? "Sync complete."); ?></strong><br>
                <?php echo (int) $syncResult["synced"]; ?> <?php echo htmlspecialchars($LANG["sync_synced"] ?? "synced"); ?>,
                <?php echo (int) $syncResult["created"]; ?> <?php echo htmlspecialchars($LANG["sync_created"] ?? "IPs created"); ?>,
                <?php echo (int) $syncResult["skipped"]; ?> <?php echo htmlspecialchars($LANG["sync_skipped"] ?? "skipped"); ?>.
                <?php if (!empty($syncResult["errors"])): ?>
                    <br><strong><?php echo htmlspecialchars($LANG["errors"] ?? "Errors"); ?>:</strong>
                    <ul class="small">
                        <?php foreach (array_slice($syncResult["errors"], 0, 10) as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($syncResult["errors"]) > 10): ?>
                            <li>… <?php echo count($syncResult["errors"]) - 10; ?> more</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-horizontal">
            <div class="form-group">
                <div class="col-sm-12">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="sync_live_only" value="1"<?php echo (!isset($_POST["run_sync"]) || !empty($_POST["sync_live_only"])) ? " checked" : ""; ?>>
                        <?php echo htmlspecialchars($LANG["sync_live_only"] ?? "Import only live services (Active and Pending)"); ?>
                    </label>
                    <p class="help-block text-muted small"><?php echo htmlspecialchars($LANG["sync_live_only_help"] ?? "When checked, only services with status Active or Pending are imported. Suspended, Cancelled and Terminated are skipped to avoid orphans."); ?></p>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-12">
                    <button type="submit" name="run_sync" value="1" class="btn btn-primary">
                        <i class="fa fa-refresh"></i> <?php echo htmlspecialchars($LANG["run_sync"] ?? "Run Sync"); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

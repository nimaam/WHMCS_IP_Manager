<?php

/**
 * Orphaned assignments - IPs assigned to suspended/cancelled/terminated services.
 * Release them to free the IP in IP Manager, WHMCS, and on the server (Proxmox, etc.).
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

$releaseMessage = null;
$releaseError   = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $releaseOne = isset($_POST["release_ip_id"]) ? (int) $_POST["release_ip_id"] : 0;
    $releaseAll = !empty($_POST["release_all"]);
    $releaseIds = isset($_POST["release_ids"]) && is_array($_POST["release_ids"]) ? array_map("intval", $_POST["release_ids"]) : [];

    if ($releaseOne > 0) {
        if (ipmanager_unassign_ip($releaseOne, true)) {
            $releaseMessage = $LANG["orphans_released_one"] ?? "IP released.";
        } else {
            $releaseError = $LANG["orphans_release_failed"] ?? "Release failed.";
        }
    } elseif ($releaseAll || !empty($releaseIds)) {
        $toRelease = $releaseAll ? [] : $releaseIds;
        if ($releaseAll) {
            $rows = Capsule::table(ipmanager_table("assignments") . " as a")
                ->join("tblhosting as h", "h.id", "=", "a.service_id")
                ->whereRaw("h.domainstatus NOT IN (?, ?)", ["Active", "Pending"])
                ->select("a.ip_address_id")
                ->distinct()
                ->pluck("ip_address_id");
            $toRelease = $rows->toArray();
        }
        $released = 0;
        foreach ($toRelease as $ipAddressId) {
            if ($ipAddressId > 0 && ipmanager_unassign_ip($ipAddressId, true)) {
                $released++;
            }
        }
        $releaseMessage = ($LANG["orphans_released_count"] ?? "%d IP(s) released.") . "";
        $releaseMessage = str_replace("%d", (string) $released, $releaseMessage);
    }
}

$orphans = Capsule::table(ipmanager_table("assignments") . " as a")
    ->join(ipmanager_table("ip_addresses") . " as ip", "ip.id", "=", "a.ip_address_id")
    ->join("tblhosting as h", "h.id", "=", "a.service_id")
    ->leftJoin("tblclients as c", "c.id", "=", "h.userid")
    ->leftJoin("tblproducts as p", "p.id", "=", "h.packageid")
    ->whereRaw("h.domainstatus NOT IN (?, ?)", ["Active", "Pending"])
    ->select(
        "a.id as assignment_id",
        "a.ip_address_id",
        "a.service_id",
        "a.client_id",
        "ip.ip",
        "h.domainstatus as service_status",
        "h.domain",
        "c.firstname",
        "c.lastname",
        "p.name as product_name"
    )
    ->orderBy("a.id")
    ->get();

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_orphans"] ?? "Orphaned Assignments"); ?></div>
    <div class="panel-body">
        <?php if ($releaseMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($releaseMessage); ?></div>
        <?php endif; ?>
        <?php if ($releaseError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($releaseError); ?></div>
        <?php endif; ?>
        <p><?php echo htmlspecialchars($LANG["orphans_info"] ?? "These IPs are assigned to services that are not Active or Pending (e.g. Suspended, Cancelled, Terminated). Releasing removes the IP from this module, clears WHMCS dedicated IP, and tells the server (Proxmox, cPanel, etc.) to free the IP."); ?></p>

        <?php if ($orphans->isEmpty()): ?>
            <p class="text-muted"><?php echo htmlspecialchars($LANG["orphans_none"] ?? "No orphaned assignments."); ?></p>
        <?php else: ?>
            <form method="post">
                <div class="form-group">
                    <button type="submit" name="release_all" value="1" class="btn btn-warning" onclick="return confirm('<?php echo htmlspecialchars($LANG["orphans_confirm_release_all"] ?? "Release all listed IPs?"); ?>');">
                        <i class="fa fa-unlink"></i> <?php echo htmlspecialchars($LANG["orphans_release_all"] ?? "Release all"); ?>
                    </button>
                </div>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars($LANG["ip"] ?? "IP"); ?></th>
                            <th><?php echo htmlspecialchars($LANG["service_id"] ?? "Service ID"); ?></th>
                            <th><?php echo htmlspecialchars($LANG["client"] ?? "Client"); ?></th>
                            <th><?php echo htmlspecialchars($LANG["product"] ?? "Product"); ?></th>
                            <th><?php echo htmlspecialchars($LANG["service_status"] ?? "Service status"); ?></th>
                            <th><?php echo htmlspecialchars($LANG["actions"] ?? "Actions"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orphans as $row): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($row->ip ?? ""); ?></code></td>
                                <td><?php echo (int) $row->service_id; ?></td>
                                <td><?php echo htmlspecialchars(trim(($row->firstname ?? "") . " " . ($row->lastname ?? ""))); ?> (#<?php echo (int) $row->client_id; ?>)</td>
                                <td><?php echo htmlspecialchars($row->product_name ?? "—"); ?></td>
                                <td><span class="label label-default"><?php echo htmlspecialchars($row->service_status ?? ""); ?></span></td>
                                <td>
                                    <button type="submit" name="release_ip_id" value="<?php echo (int) $row->ip_address_id; ?>" class="btn btn-xs btn-danger" onclick="return confirm('<?php echo htmlspecialchars($LANG["orphans_confirm_release_one"] ?? "Release this IP?"); ?>');">
                                        <i class="fa fa-unlink"></i> <?php echo htmlspecialchars($LANG["orphans_release"] ?? "Release"); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        <?php endif; ?>
    </div>
</div>

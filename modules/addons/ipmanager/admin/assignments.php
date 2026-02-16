<?php

/**
 * Assignments admin - assign/unassign IPs to services, sync to WHMCS.
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

$clientIdFilter = isset($_GET["client_id"]) ? (int) $_GET["client_id"] : null;
$serviceIdFilter = isset($_GET["service_id"]) ? (int) $_GET["service_id"] : null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postAction = $_POST["assign_action"] ?? "";
    if ($postAction === "assign" && isset($_POST["service_id"], $_POST["client_id"])) {
        $serviceId = (int) $_POST["service_id"];
        $clientId  = (int) $_POST["client_id"];
        $poolId    = isset($_POST["pool_id"]) && $_POST["pool_id"] !== "" ? (int) $_POST["pool_id"] : null;
        $subnetId  = isset($_POST["subnet_id"]) && $_POST["subnet_id"] !== "" ? (int) $_POST["subnet_id"] : null;
        $free = ipmanager_get_free_ip_from_pool_or_subnet($poolId, $subnetId);
        if ($free && ipmanager_assign_ip_to_service((int) $free->id, $clientId, $serviceId, true)) {
            $assignMessage = $LANG["assign_success"] ?? "IP assigned.";
        } else {
            $assignError = $LANG["assign_no_free_ip"] ?? "No free IP in selected pool/subnet or assign failed.";
        }
    } elseif ($postAction === "unassign" && isset($_POST["ip_address_id"])) {
        $ipId = (int) $_POST["ip_address_id"];
        if (ipmanager_unassign_ip($ipId, true)) {
            $assignMessage = $LANG["unassign_success"] ?? "IP unassigned.";
        } else {
            $assignError = $LANG["unassign_failed"] ?? "Unassign failed.";
        }
    }
}

$subnets = Capsule::table(ipmanager_table("subnets"))->orderBy("name")->get();
$pools   = Capsule::table(ipmanager_table("pools"))->orderBy("name")->get();

$q = Capsule::table("tblhosting as h")
    ->leftJoin("tblclients as c", "c.id", "=", "h.userid")
    ->leftJoin("tblproducts as p", "p.id", "=", "h.packageid")
    ->select("h.id as service_id", "h.userid as client_id", "h.dedicatedip", "h.domainstatus as service_status", "c.firstname", "c.lastname", "p.name as product_name");
if ($clientIdFilter > 0) {
    $q->where("h.userid", $clientIdFilter);
}
if ($serviceIdFilter > 0) {
    $q->where("h.id", $serviceIdFilter);
}
$services = $q->orderBy("h.id", "desc")->limit(500)->get();

$assignmentsByService = [];
$assignRows = Capsule::table(ipmanager_table("assignments") . " as a")
    ->join(ipmanager_table("ip_addresses") . " as ip", "ip.id", "=", "a.ip_address_id")
    ->whereIn("a.service_id", $services->pluck("service_id")->toArray())
    ->select("a.service_id", "a.ip_address_id", "ip.ip")
    ->get();
foreach ($assignRows as $ar) {
    $assignmentsByService[$ar->service_id][] = ["ip_address_id" => $ar->ip_address_id, "ip" => $ar->ip];
}

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_assignments"] ?? "Assignments"); ?></div>
    <div class="panel-body">
        <?php if (isset($assignMessage)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($assignMessage); ?></div>
        <?php endif; ?>
        <?php if (isset($assignError)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($assignError); ?></div>
        <?php endif; ?>
        <p><?php echo htmlspecialchars($LANG["assignments_info"] ?? "Assign or unassign IPs to/from services. WHMCS dedicated IP is synced automatically."); ?></p>
        <form method="get" class="form-inline" style="margin-bottom:15px">
            <input type="hidden" name="module" value="ipmanager">
            <input type="hidden" name="action" value="assignments">
            <input type="text" name="client_id" class="form-control" placeholder="<?php echo htmlspecialchars($LANG["client_id"] ?? "Client ID"); ?>" value="<?php echo $clientIdFilter ? (int) $clientIdFilter : ""; ?>">
            <input type="text" name="service_id" class="form-control" placeholder="<?php echo htmlspecialchars($LANG["service_id"] ?? "Service ID"); ?>" value="<?php echo $serviceIdFilter ? (int) $serviceIdFilter : ""; ?>">
            <button type="submit" class="btn btn-default"><?php echo htmlspecialchars($LANG["filter"] ?? "Filter"); ?></button>
        </form>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars($LANG["service_id"] ?? "Service ID"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["client"] ?? "Client"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["product"] ?? "Product"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["service_status"] ?? "Service status"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["assigned_ips"] ?? "Assigned IPs"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["whmcs_dedicatedip"] ?? "WHMCS dedicatedip"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["actions"] ?? "Actions"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $svc): ?>
                    <?php
                    $assigned = $assignmentsByService[$svc->service_id] ?? [];
                    ?>
                    <tr>
                        <td><?php echo (int) $svc->service_id; ?></td>
                        <td>#<?php echo (int) $svc->client_id; ?> <?php echo htmlspecialchars(trim($svc->firstname . " " . $svc->lastname)); ?></td>
                        <td><?php echo htmlspecialchars($svc->product_name ?? "—"); ?></td>
                        <td><span class="label label-<?php echo in_array($svc->service_status ?? "", ["Active", "Pending"], true) ? "success" : "default"; ?>"><?php echo htmlspecialchars($svc->service_status ?? "—"); ?></span></td>
                        <td>
                            <?php foreach ($assigned as $a): ?>
                                <code><?php echo htmlspecialchars($a["ip"]); ?></code>
                            <?php endforeach; ?>
                            <?php if (empty($assigned)): ?>—<?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($svc->dedicatedip ?? ""); ?></code></td>
                        <td>
                            <?php foreach ($assigned as $a): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="assign_action" value="unassign">
                                    <input type="hidden" name="ip_address_id" value="<?php echo (int) $a["ip_address_id"]; ?>">
                                    <button type="submit" class="btn btn-xs btn-danger"><?php echo htmlspecialchars($LANG["unassign"] ?? "Unassign"); ?></button>
                                </form>
                            <?php endforeach; ?>
                            <form method="post" class="form-inline" style="display:inline">
                                <input type="hidden" name="assign_action" value="assign">
                                <input type="hidden" name="service_id" value="<?php echo (int) $svc->service_id; ?>">
                                <input type="hidden" name="client_id" value="<?php echo (int) $svc->client_id; ?>">
                                <select name="pool_id" class="form-control input-sm">
                                    <option value="">— <?php echo htmlspecialchars($LANG["pool"] ?? "Pool"); ?> —</option>
                                    <?php foreach ($pools as $pl): ?>
                                        <option value="<?php echo (int) $pl->id; ?>"><?php echo htmlspecialchars($pl->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="subnet_id" class="form-control input-sm">
                                    <option value="">— <?php echo htmlspecialchars($LANG["subnet"] ?? "Subnet"); ?> —</option>
                                    <?php foreach ($subnets as $sn): ?>
                                        <option value="<?php echo (int) $sn->id; ?>"><?php echo htmlspecialchars($sn->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-xs btn-success"><?php echo htmlspecialchars($LANG["assign"] ?? "Assign"); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

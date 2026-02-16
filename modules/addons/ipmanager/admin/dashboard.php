<?php

/**
 * IP Manager admin dashboard.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

$modulelink = $vars["modulelink"];
$version    = $vars["version"];
$LANG       = $vars["_lang"] ?? [];

include __DIR__ . "/_menu.php";

$stats = [
    "subnets"     => 0,
    "pools"       => 0,
    "ip_assigned" => 0,
    "ip_free"     => 0,
];

try {
    $stats["subnets"] = Capsule::table(ipmanager_table("subnets"))->count();
    $stats["pools"]   = Capsule::table(ipmanager_table("pools"))->count();
    $stats["ip_assigned"] = Capsule::table(ipmanager_table("ip_addresses"))->where("status", "assigned")->count();
    $stats["ip_free"]     = Capsule::table(ipmanager_table("ip_addresses"))->where("status", "free")->count();
} catch (Exception $e) {
    $stats["error"] = $e->getMessage();
}

?>
<div class="row">
    <div class="col-md-3 col-sm-6">
        <div class="panel panel-default">
            <div class="panel-heading"><?php echo htmlspecialchars($LANG["stat_subnets"] ?? "IP Subnets"); ?></div>
            <div class="panel-body text-center">
                <h3><?php echo (int) $stats["subnets"]; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="panel panel-default">
            <div class="panel-heading"><?php echo htmlspecialchars($LANG["stat_pools"] ?? "IP Pools"); ?></div>
            <div class="panel-body text-center">
                <h3><?php echo (int) $stats["pools"]; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="panel panel-default">
            <div class="panel-heading"><?php echo htmlspecialchars($LANG["stat_assigned"] ?? "Assigned IPs"); ?></div>
            <div class="panel-body text-center">
                <h3><?php echo (int) $stats["ip_assigned"]; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="panel panel-default">
            <div class="panel-heading"><?php echo htmlspecialchars($LANG["stat_free"] ?? "Free IPs"); ?></div>
            <div class="panel-body text-center">
                <h3><?php echo (int) $stats["ip_free"]; ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($stats["error"])): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($stats["error"]); ?></div>
<?php endif; ?>

<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["quick_actions"] ?? "Quick Actions"); ?></div>
    <div class="panel-body">
        <a href="<?php echo $modulelink; ?>&action=subnets" class="btn btn-primary">
            <i class="fa fa-sitemap"></i> <?php echo htmlspecialchars($LANG["add_subnet"] ?? "Add IP Subnet"); ?>
        </a>
        <a href="<?php echo $modulelink; ?>&action=configurations" class="btn btn-default">
            <i class="fa fa-cogs"></i> <?php echo htmlspecialchars($LANG["manage_configurations"] ?? "Manage Configurations"); ?>
        </a>
        <a href="<?php echo $modulelink; ?>&action=sync" class="btn btn-default">
            <i class="fa fa-refresh"></i> <?php echo htmlspecialchars($LANG["sync_whmcs"] ?? "Sync with WHMCS"); ?>
        </a>
    </div>
</div>

<p class="text-muted">IP Manager v<?php echo htmlspecialchars($version); ?></p>

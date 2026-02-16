<?php

/**
 * Admin horizontal menu for IP Manager.
 *
 * @var string $modulelink
 * @var array  $_lang
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$current = $_GET["action"] ?? "dashboard";
$base = $modulelink . "&action=";
$menu = [
    "dashboard"      => ["label" => $_lang["menu_dashboard"] ?? "Dashboard", "icon" => "fa-tachometer"],
    "subnets"        => ["label" => $_lang["menu_subnets"] ?? "IP Subnets", "icon" => "fa-sitemap"],
    "pools"          => ["label" => $_lang["menu_pools"] ?? "IP Pools", "icon" => "fa-database"],
    "configurations" => ["label" => $_lang["menu_configurations"] ?? "Configurations", "icon" => "fa-cogs"],
    "assignments"    => ["label" => $_lang["menu_assignments"] ?? "Assignments", "icon" => "fa-link"],
    "sync"           => ["label" => $_lang["menu_sync"] ?? "Synchronize", "icon" => "fa-refresh"],
    "export"         => ["label" => $_lang["menu_export"] ?? "Export", "icon" => "fa-download"],
    "import"         => ["label" => $_lang["menu_import"] ?? "Import", "icon" => "fa-upload"],
    "logs"           => ["label" => $_lang["menu_logs"] ?? "Logs", "icon" => "fa-list-alt"],
    "settings"       => ["label" => $_lang["menu_settings"] ?? "Settings", "icon" => "fa-wrench"],
    "translations"   => ["label" => $_lang["menu_translations"] ?? "Translations", "icon" => "fa-language"],
    "acl"            => ["label" => $_lang["menu_acl"] ?? "ACL", "icon" => "fa-lock"],
    "integrations"   => ["label" => $_lang["menu_integrations"] ?? "Integrations", "icon" => "fa-plug"],
    "ipam"           => ["label" => $_lang["menu_ipam"] ?? "IPAM (NetBox)", "icon" => "fa-cloud-download"],
];

$logoUrl = "../modules/addons/ipmanager/assets/logo.svg";
?>
<div class="panel panel-default" style="margin-bottom: 15px;">
    <div class="panel-body" style="padding: 10px 15px;">
        <div style="margin-bottom: 12px;">
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="IP Manager" class="ipmanager-logo" style="max-height: 40px; width: auto; display: inline-block; vertical-align: middle;" onerror="this.style.display='none'">
        </div>
        <ul class="nav nav-tabs" style="border-bottom: none; margin: 0;">
            <?php foreach ($menu as $action => $item): ?>
                <li class="<?php echo $current === $action ? "active" : ""; ?>">
                    <a href="<?php echo $base . $action; ?>">
                        <i class="fa <?php echo $item["icon"]; ?>"></i>
                        <?php echo htmlspecialchars($item["label"]); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

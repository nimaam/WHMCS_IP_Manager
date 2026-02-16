<?php

/**
 * Cron: run IP Manager usage alerts.
 * Schedule e.g. daily: 0 9 * * * php /path/to/whmcs/modules/addons/ipmanager/cron/usage_alerts.php
 *
 * @copyright 2025
 */

$whmcsPath = realpath(dirname(__DIR__) . "/../../..");
if (!is_file($whmcsPath . "/init.php")) {
    $whmcsPath = realpath(dirname(__DIR__) . "/../..");
}
if (!is_file($whmcsPath . "/init.php")) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}

require_once $whmcsPath . "/init.php";

use WHMCS\Database\Capsule;

$addon = Capsule::table("tbladdonmodules")
    ->where("module", "ipmanager")
    ->where("setting", "usage_alert_percent")
    ->first();
$threshold = $addon && is_numeric($addon->value) ? (int) $addon->value : 80;

require_once dirname(__DIR__) . "/lib/helpers.php";
require_once dirname(__DIR__) . "/lib/UsageAlerts.php";

$result = IpManagerUsageAlerts::run($threshold, 24);
echo "Usage alerts sent: " . $result["sent"] . ", skipped: " . $result["skipped"] . "\n";

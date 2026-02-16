<?php

/**
 * Cron: run IP Manager usage alerts and IP cleaner (when enabled).
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

function ipmanager_get_addon_setting(string $key): ?string {
    $row = Capsule::table("tbladdonmodules")->where("module", "ipmanager")->where("setting", $key)->first();
    return $row && isset($row->value) ? (string) $row->value : null;
}

require_once dirname(__DIR__) . "/lib/helpers.php";
require_once dirname(__DIR__) . "/lib/UsageAlerts.php";
require_once dirname(__DIR__) . "/lib/IpCleaner.php";

$threshold = ipmanager_get_addon_setting("usage_alert_percent");
$threshold = is_numeric($threshold) ? (int) $threshold : 80;

$result = IpManagerUsageAlerts::run($threshold, 24);
echo "Usage alerts sent: " . $result["sent"] . ", skipped: " . $result["skipped"] . "\n";

$cleanerEnabled = ipmanager_get_addon_setting("cleaner_enabled");
if ($cleanerEnabled === "on" || $cleanerEnabled === "1" || $cleanerEnabled === "yes") {
    $cleanerBehavior = ipmanager_get_addon_setting("cleaner_behavior");
    if ($cleanerBehavior !== "mark_free" && $cleanerBehavior !== "notify_only") {
        $cleanerBehavior = IpManagerIpCleaner::BEHAVIOR_NOTIFY_ONLY;
    }
    $cleanerResult = IpManagerIpCleaner::run($cleanerBehavior);
    echo "IP Cleaner: checked " . $cleanerResult["checked"] . ", orphaned " . $cleanerResult["orphaned"];
    if ($cleanerResult["marked_free"] > 0) {
        echo ", marked free " . $cleanerResult["marked_free"];
    }
    if ($cleanerResult["notified"]) {
        echo ", notification sent";
    }
    echo "\n";
}

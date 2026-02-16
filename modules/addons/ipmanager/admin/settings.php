<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . "/../lib/UsageAlerts.php";
require_once __DIR__ . "/../lib/IpCleaner.php";

$modulelink = $vars["modulelink"];
$LANG       = $vars["_lang"] ?? [];
$usageAlertPercent = (int) ($vars["usage_alert_percent"] ?? 80);
if ($usageAlertPercent <= 0) {
    $usageAlertPercent = 80;
}
$cleanerEnabled = ($vars["cleaner_enabled"] ?? "") === "on" || ($vars["cleaner_enabled"] ?? "") === "1";
$cleanerBehavior = $vars["cleaner_behavior"] ?? "notify_only";
if ($cleanerBehavior !== "mark_free" && $cleanerBehavior !== "notify_only") {
    $cleanerBehavior = "notify_only";
}

$usageAlertResult = null;
$cleanerResult = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["run_usage_check"])) {
        $usageAlertResult = IpManagerUsageAlerts::run($usageAlertPercent, 0);
    }
    if (isset($_POST["run_ip_cleaner"])) {
        $cleanerResult = IpManagerIpCleaner::run($cleanerBehavior);
    }
}

include __DIR__ . "/_menu.php";

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_settings"] ?? "Settings"); ?></div>
    <div class="panel-body">
        <p><?php echo htmlspecialchars($LANG["settings_info"] ?? "Module settings are configured in Addons → Addon Modules → IP Manager (Configure)."); ?></p>

        <h4><?php echo htmlspecialchars($LANG["usage_alerts"] ?? "Usage alerts"); ?></h4>
        <p><?php echo htmlspecialchars($LANG["usage_alerts_help"] ?? "When subnet/pool usage exceeds the configured threshold (%), an email is sent to the first admin. Run the check manually below or add a cron job."); ?></p>
        <?php if ($usageAlertResult !== null): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($LANG["usage_alerts_sent"] ?? "Alerts sent"); ?>: <?php echo (int) $usageAlertResult["sent"]; ?>,
                <?php echo htmlspecialchars($LANG["usage_alerts_skipped"] ?? "Skipped"); ?>: <?php echo (int) $usageAlertResult["skipped"]; ?>
            </div>
        <?php endif; ?>
        <form method="post" style="display:inline">
            <button type="submit" name="run_usage_check" value="1" class="btn btn-default"><?php echo htmlspecialchars($LANG["run_usage_check"] ?? "Run usage check now"); ?></button>
        </form>
        <p class="text-muted small" style="margin-top:15px">
            <?php echo htmlspecialchars($LANG["usage_alerts_cron"] ?? "Cron"); ?>: <code>php modules/addons/ipmanager/cron/usage_alerts.php</code>
        </p>

        <hr>
        <h4><?php echo htmlspecialchars($LANG["ip_cleaner"] ?? "IP Cleaner"); ?></h4>
        <p><?php echo htmlspecialchars($LANG["ip_cleaner_help"] ?? "Finds assigned IPs that are no longer in use (service dedicated IP no longer matches). Enable and set behavior in Addons → Addon Modules → IP Manager (Configure)."); ?></p>
        <p><strong><?php echo htmlspecialchars($LANG["ip_cleaner_behavior"] ?? "Behavior"); ?>:</strong>
            <?php if ($cleanerBehavior === "notify_only"): ?>
                <?php echo htmlspecialchars($LANG["cleaner_notify_only"] ?? "Notify only – email report of orphaned IPs, no changes."); ?>
            <?php else: ?>
                <?php echo htmlspecialchars($LANG["cleaner_mark_free"] ?? "Mark free – unassign orphaned IPs and mark as free, then email report."); ?>
            <?php endif; ?>
        </p>
        <?php if ($cleanerResult !== null): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($LANG["cleaner_checked"] ?? "Checked"); ?>: <?php echo (int) $cleanerResult["checked"]; ?>,
                <?php echo htmlspecialchars($LANG["cleaner_orphaned"] ?? "Orphaned"); ?>: <?php echo (int) $cleanerResult["orphaned"]; ?>
                <?php if ($cleanerResult["marked_free"] > 0): ?>,
                    <?php echo htmlspecialchars($LANG["cleaner_marked_free"] ?? "Marked free"); ?>: <?php echo (int) $cleanerResult["marked_free"]; ?>
                <?php endif; ?>
                <?php if ($cleanerResult["notified"]): ?>,
                    <?php echo htmlspecialchars($LANG["notification_sent"] ?? "Notification sent"); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form method="post" style="display:inline">
            <button type="submit" name="run_ip_cleaner" value="1" class="btn btn-default" <?php echo !$cleanerEnabled ? "disabled title=\"" . htmlspecialchars($LANG["cleaner_enable_first"] ?? "Enable IP Cleaner in module config first") . "\"" : ""; ?>><?php echo htmlspecialchars($LANG["run_ip_cleaner"] ?? "Run IP Cleaner now"); ?></button>
        </form>
    </div>
</div>

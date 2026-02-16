<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . "/../lib/UsageAlerts.php";

$modulelink = $vars["modulelink"];
$LANG       = $vars["_lang"] ?? [];
$usageAlertPercent = (int) ($vars["usage_alert_percent"] ?? 80);
if ($usageAlertPercent <= 0) {
    $usageAlertPercent = 80;
}

$usageAlertResult = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["run_usage_check"])) {
    $usageAlertResult = IpManagerUsageAlerts::run($usageAlertPercent, 0);
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
        <form method="post">
            <button type="submit" name="run_usage_check" value="1" class="btn btn-default"><?php echo htmlspecialchars($LANG["run_usage_check"] ?? "Run usage check now"); ?></button>
        </form>
        <p class="text-muted small" style="margin-top:15px">
            <?php echo htmlspecialchars($LANG["usage_alerts_cron"] ?? "Cron"); ?>: <code>php modules/addons/ipmanager/cron/usage_alerts.php</code>
        </p>
    </div>
</div>

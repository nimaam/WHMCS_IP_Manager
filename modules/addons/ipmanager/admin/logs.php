<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

$modulelink = $vars["modulelink"];
$LANG       = $vars["_lang"] ?? [];
include __DIR__ . "/_menu.php";

$logs = Capsule::table(ipmanager_table("logs"))->orderBy("id", "desc")->limit(100)->get();

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_logs"] ?? "Logs"); ?></div>
    <div class="panel-body">
        <table class="table table-striped table-condensed">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php echo htmlspecialchars($LANG["action"] ?? "Action"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["details"] ?? "Details"); ?></th>
                    <th><?php echo htmlspecialchars($LANG["date"] ?? "Date"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo (int) $log->id; ?></td>
                        <td><?php echo htmlspecialchars($log->action ?? ""); ?></td>
                        <td><?php echo htmlspecialchars(substr($log->details ?? "", 0, 80)); ?></td>
                        <td><?php echo htmlspecialchars($log->created_at ?? ""); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($logs->count() === 0): ?>
            <p class="text-muted"><?php echo htmlspecialchars($LANG["no_logs"] ?? "No log entries yet."); ?></p>
        <?php endif; ?>
    </div>
</div>

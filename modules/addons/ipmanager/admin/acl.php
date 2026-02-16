<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$modulelink = $vars["modulelink"];
$LANG       = $vars["_lang"] ?? [];
include __DIR__ . "/_menu.php";

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_acl"] ?? "ACL"); ?></div>
    <div class="panel-body">
        <p><?php echo htmlspecialchars($LANG["acl_info"] ?? "Control staff access level to specific resources (subnets, pools, configurations)."); ?></p>
    </div>
</div>

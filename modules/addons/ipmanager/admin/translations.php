<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$modulelink = $vars["modulelink"];
$LANG       = $vars["_lang"] ?? [];
include __DIR__ . "/_menu.php";

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_translations"] ?? "Translations"); ?></div>
    <div class="panel-body">
        <p><?php echo htmlspecialchars($LANG["translations_info"] ?? "Customize module language files for multi-language support."); ?></p>
    </div>
</div>

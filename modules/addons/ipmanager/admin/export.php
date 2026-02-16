<?php

/**
 * Export IP subnets and pools - CSV, XML, JSON.
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

$format = $_GET["format"] ?? "";
if ($format !== "" && in_array($format, ["csv", "xml", "json"], true)) {
    $subnets = Capsule::table(ipmanager_table("subnets"))->orderBy("parent_id")->orderBy("name")->get();
    $pools   = Capsule::table(ipmanager_table("pools"))->orderBy("subnet_id")->orderBy("name")->get();

    if ($format === "csv") {
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=ipmanager_export_" . date("Y-m-d") . ".csv");
        $out = fopen("php://output", "w");
        fputcsv($out, ["type", "id", "parent_id", "subnet_id", "name", "cidr", "gateway", "start_ip", "end_ip", "version"]);
        foreach ($subnets as $s) {
            fputcsv($out, ["subnet", $s->id, $s->parent_id ?? "", $s->subnet_id ?? "", $s->name, $s->cidr ?? "", $s->gateway ?? "", $s->start_ip ?? "", $s->end_ip ?? "", $s->version ?? 4]);
        }
        foreach ($pools as $p) {
            fputcsv($out, ["pool", $p->id, "", $p->subnet_id ?? "", $p->name, $p->cidr ?? "", "", $p->start_ip ?? "", $p->end_ip ?? "", $p->version ?? 4]);
        }
        fclose($out);
        exit;
    }

    if ($format === "xml") {
        header("Content-Type: application/xml; charset=utf-8");
        header("Content-Disposition: attachment; filename=ipmanager_export_" . date("Y-m-d") . ".xml");
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo "<ipmanager_export date=\"" . date("c") . "\">\n";
        echo "  <subnets>\n";
        foreach ($subnets as $s) {
            echo "    <subnet id=\"" . (int) $s->id . "\" parent_id=\"" . (int) ($s->parent_id ?? 0) . "\" name=\"" . htmlspecialchars($s->name) . "\" cidr=\"" . htmlspecialchars($s->cidr ?? "") . "\" gateway=\"" . htmlspecialchars($s->gateway ?? "") . "\" start_ip=\"" . htmlspecialchars($s->start_ip ?? "") . "\" end_ip=\"" . htmlspecialchars($s->end_ip ?? "") . "\" version=\"" . (int) ($s->version ?? 4) . "\" />\n";
        }
        echo "  </subnets>\n  <pools>\n";
        foreach ($pools as $p) {
            echo "    <pool id=\"" . (int) $p->id . "\" subnet_id=\"" . (int) ($p->subnet_id ?? 0) . "\" name=\"" . htmlspecialchars($p->name) . "\" cidr=\"" . htmlspecialchars($p->cidr ?? "") . "\" start_ip=\"" . htmlspecialchars($p->start_ip ?? "") . "\" end_ip=\"" . htmlspecialchars($p->end_ip ?? "") . "\" version=\"" . (int) ($p->version ?? 4) . "\" />\n";
        }
        echo "  </pools>\n</ipmanager_export>\n";
        exit;
    }

    if ($format === "json") {
        header("Content-Type: application/json; charset=utf-8");
        header("Content-Disposition: attachment; filename=ipmanager_export_" . date("Y-m-d") . ".json");
        echo json_encode(["date" => date("c"), "subnets" => $subnets->toArray(), "pools" => $pools->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_export"] ?? "Export"); ?></div>
    <div class="panel-body">
        <p><?php echo htmlspecialchars($LANG["export_info"] ?? "Export IP subnets/pools in CSV, XML or JSON format."); ?></p>
        <p>
            <a href="<?php echo $modulelink; ?>&action=export&format=csv" class="btn btn-default"><i class="fa fa-file-text-o"></i> CSV</a>
            <a href="<?php echo $modulelink; ?>&action=export&format=xml" class="btn btn-default"><i class="fa fa-file-code-o"></i> XML</a>
            <a href="<?php echo $modulelink; ?>&action=export&format=json" class="btn btn-default"><i class="fa fa-file-o"></i> JSON</a>
        </p>
    </div>
</div>

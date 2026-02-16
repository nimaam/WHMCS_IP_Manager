<?php

/**
 * Import IP subnets and pools from CSV, XML or JSON.
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

$importResult = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_FILES["import_file"]["tmp_name"])) {
    $format = $_POST["import_format"] ?? "json";
    $file  = $_FILES["import_file"]["tmp_name"];
    $subnetsCreated = 0;
    $poolsCreated   = 0;
    $errors = [];

    if ($format === "json") {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (!$data || !isset($data["subnets"])) {
            $errors[] = "Invalid JSON or missing subnets key.";
        } else {
            $idMap = [];
            foreach ($data["subnets"] as $s) {
                $parentId = null;
                if (!empty($s["parent_id"]) && isset($idMap[$s["parent_id"]])) {
                    $parentId = $idMap[$s["parent_id"]];
                }
                try {
                    $now = date("Y-m-d H:i:s");
                    $newId = (int) Capsule::table(ipmanager_table("subnets"))->insertGetId([
                        "parent_id"  => $parentId,
                        "name"       => $s["name"] ?? "Imported",
                        "cidr"       => $s["cidr"] ?? "",
                        "gateway"    => $s["gateway"] ?? null,
                        "start_ip"   => $s["start_ip"] ?? null,
                        "end_ip"     => $s["end_ip"] ?? null,
                        "version"    => (int) ($s["version"] ?? 4),
                        "excluded_ips" => isset($s["excluded_ips"]) ? (is_string($s["excluded_ips"]) ? $s["excluded_ips"] : json_encode($s["excluded_ips"])) : null,
                        "created_at" => $now,
                        "updated_at" => $now,
                    ]);
                    $idMap[$s["id"] ?? $newId] = $newId;
                    $subnetsCreated++;
                } catch (Exception $e) {
                    $errors[] = "Subnet " . ($s["name"] ?? "") . ": " . $e->getMessage();
                }
            }
            foreach ($data["pools"] ?? [] as $p) {
                $subnetId = isset($p["subnet_id"], $idMap[$p["subnet_id"]]) ? $idMap[$p["subnet_id"]] : (int) ($p["subnet_id"] ?? 0);
                if ($subnetId <= 0) {
                    continue;
                }
                try {
                    $now = date("Y-m-d H:i:s");
                    Capsule::table(ipmanager_table("pools"))->insert([
                        "subnet_id"  => $subnetId,
                        "name"       => $p["name"] ?? "Imported",
                        "cidr"       => $p["cidr"] ?? null,
                        "start_ip"   => $p["start_ip"] ?? null,
                        "end_ip"     => $p["end_ip"] ?? null,
                        "version"    => (int) ($p["version"] ?? 4),
                        "created_at" => $now,
                        "updated_at" => $now,
                    ]);
                    $poolsCreated++;
                } catch (Exception $e) {
                    $errors[] = "Pool " . ($p["name"] ?? "") . ": " . $e->getMessage();
                }
            }
        }
    } elseif ($format === "xml") {
        $xml = @simplexml_load_file($file);
        if ($xml === false) {
            $errors[] = "Invalid XML.";
        } else {
            $idMap = [];
            foreach ($xml->subnets->subnet ?? [] as $s) {
                $attrs = $s->attributes();
                $parentId = null;
                $oldParent = (int) ($attrs["parent_id"] ?? 0);
                if ($oldParent > 0 && isset($idMap[$oldParent])) {
                    $parentId = $idMap[$oldParent];
                }
                try {
                    $now = date("Y-m-d H:i:s");
                    $oldId = (int) ($attrs["id"] ?? 0);
                    $newId = (int) Capsule::table(ipmanager_table("subnets"))->insertGetId([
                        "parent_id"  => $parentId,
                        "name"       => (string) ($attrs["name"] ?? "Imported"),
                        "cidr"       => (string) ($attrs["cidr"] ?? ""),
                        "gateway"    => (string) ($attrs["gateway"] ?? "") ?: null,
                        "start_ip"   => (string) ($attrs["start_ip"] ?? "") ?: null,
                        "end_ip"     => (string) ($attrs["end_ip"] ?? "") ?: null,
                        "version"    => (int) ($attrs["version"] ?? 4),
                        "created_at" => $now,
                        "updated_at" => $now,
                    ]);
                    if ($oldId > 0) {
                        $idMap[$oldId] = $newId;
                    }
                    $subnetsCreated++;
                } catch (Exception $e) {
                    $errors[] = "Subnet: " . $e->getMessage();
                }
            }
            foreach ($xml->pools->pool ?? [] as $p) {
                $attrs = $p->attributes();
                $subnetId = isset($idMap[(int) $attrs["subnet_id"]]) ? $idMap[(int) $attrs["subnet_id"]] : (int) ($attrs["subnet_id"] ?? 0);
                if ($subnetId <= 0) {
                    continue;
                }
                try {
                    $now = date("Y-m-d H:i:s");
                    Capsule::table(ipmanager_table("pools"))->insert([
                        "subnet_id"  => $subnetId,
                        "name"       => (string) ($attrs["name"] ?? "Imported"),
                        "cidr"       => (string) ($attrs["cidr"] ?? "") ?: null,
                        "start_ip"   => (string) ($attrs["start_ip"] ?? "") ?: null,
                        "end_ip"     => (string) ($attrs["end_ip"] ?? "") ?: null,
                        "version"    => (int) ($attrs["version"] ?? 4),
                        "created_at" => $now,
                        "updated_at" => $now,
                    ]);
                    $poolsCreated++;
                } catch (Exception $e) {
                    $errors[] = "Pool: " . $e->getMessage();
                }
            }
        }
    } else {
        $errors[] = "Unsupported format.";
    }

    $importResult = [
        "subnets" => $subnetsCreated,
        "pools"   => $poolsCreated,
        "errors"  => $errors,
    ];
}

?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo htmlspecialchars($LANG["menu_import"] ?? "Import"); ?></div>
    <div class="panel-body">
        <p><?php echo htmlspecialchars($LANG["import_info"] ?? "Import IP subnets/pools from CSV, XML or JSON."); ?></p>
        <p class="text-warning"><?php echo htmlspecialchars($LANG["import_warning"] ?? "Import creates new subnets and pools. Parent IDs are remapped. Use JSON or XML exported from this module for best results."); ?></p>

        <?php if ($importResult !== null): ?>
            <div class="alert alert-info">
                <?php echo (int) $importResult["subnets"]; ?> <?php echo htmlspecialchars($LANG["import_subnets_created"] ?? "subnets created"); ?>,
                <?php echo (int) $importResult["pools"]; ?> <?php echo htmlspecialchars($LANG["import_pools_created"] ?? "pools created"); ?>.
                <?php if (!empty($importResult["errors"])): ?>
                    <ul class="small">
                        <?php foreach (array_slice($importResult["errors"], 0, 10) as $e): ?>
                            <li><?php echo htmlspecialchars($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="form-horizontal">
            <div class="form-group">
                <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["import_format"] ?? "Format"); ?></label>
                <div class="col-sm-4">
                    <select name="import_format" class="form-control">
                        <option value="json">JSON</option>
                        <option value="xml">XML</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["import_file"] ?? "File"); ?></label>
                <div class="col-sm-4">
                    <input type="file" name="import_file" class="form-control" accept=".json,.xml" required>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-4">
                    <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($LANG["import_submit"] ?? "Import"); ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

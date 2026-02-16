<?php

/**
 * Configurations admin - CRUD and relations (products, addons, configoptions, servers).
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

$subAction = $_GET["sub"] ?? "list";
$id        = (int) ($_GET["id"] ?? 0);

if ($subAction === "save" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim((string) ($_POST["name"] ?? ""));
    $omitDedicated = !empty($_POST["omit_dedicated_ip_field"]);
    $useCustomField = !empty($_POST["use_custom_field_instead_of_assigned"]);
    $customFieldName = trim((string) ($_POST["custom_field_name"] ?? ""));
    $editId = (int) ($_POST["id"] ?? 0);
    if ($name !== "") {
        try {
            $now = date("Y-m-d H:i:s");
            if ($editId > 0) {
                Capsule::table(ipmanager_table("configurations"))->where("id", $editId)->update([
                    "name"                                => $name,
                    "omit_dedicated_ip_field"              => $omitDedicated,
                    "use_custom_field_instead_of_assigned" => $useCustomField,
                    "custom_field_name"                    => $customFieldName !== "" ? $customFieldName : null,
                    "updated_at"                           => $now,
                ]);
            } else {
                $configId = (int) Capsule::table(ipmanager_table("configurations"))->insertGetId([
                    "name"                                => $name,
                    "omit_dedicated_ip_field"              => $omitDedicated,
                    "use_custom_field_instead_of_assigned" => $useCustomField,
                    "custom_field_name"                    => $customFieldName !== "" ? $customFieldName : null,
                    "created_at"                           => $now,
                    "updated_at"                           => $now,
                ]);
                $poolId   = isset($_POST["pool_id"]) && $_POST["pool_id"] !== "" ? (int) $_POST["pool_id"] : null;
                $subnetId = isset($_POST["subnet_id"]) && $_POST["subnet_id"] !== "" ? (int) $_POST["subnet_id"] : null;
                $productIds = isset($_POST["product_ids"]) && is_array($_POST["product_ids"]) ? array_map("intval", array_filter($_POST["product_ids"])) : [];
                $serverIds  = isset($_POST["server_ids"]) && is_array($_POST["server_ids"]) ? array_map("intval", array_filter($_POST["server_ids"])) : [];
                $productIds = array_filter($productIds, static function ($id) { return $id > 0; });
                $serverIds  = array_filter($serverIds, static function ($id) { return $id > 0; });
                if (($poolId > 0 || $subnetId > 0) && (count($productIds) > 0 || count($serverIds) > 0)) {
                    foreach ($productIds as $relId) {
                        Capsule::table(ipmanager_table("configuration_relations"))->insert([
                            "configuration_id" => $configId,
                            "relation_type"    => "product",
                            "relation_id"      => $relId,
                            "pool_id"          => $poolId,
                            "subnet_id"        => $subnetId,
                            "created_at"       => $now,
                            "updated_at"       => $now,
                        ]);
                    }
                    foreach ($serverIds as $relId) {
                        Capsule::table(ipmanager_table("configuration_relations"))->insert([
                            "configuration_id" => $configId,
                            "relation_type"    => "server",
                            "relation_id"      => $relId,
                            "pool_id"          => $poolId,
                            "subnet_id"        => $subnetId,
                            "created_at"       => $now,
                            "updated_at"       => $now,
                        ]);
                    }
                }
            }
            if ($editId > 0) {
                $configId = $editId;
            }
            header("Location: " . $modulelink . "&action=configurations&sub=edit&id=" . $configId . (isset($_POST["product_ids"]) || isset($_POST["server_ids"]) ? "&bulk_saved=1" : ""));
            exit;
        } catch (Exception $e) {
            $configError = $e->getMessage();
        }
    }
    $subAction = $editId > 0 ? "edit" : "add";
    $id = $editId;
}

if ($subAction === "relation_save" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $configId   = (int) ($_POST["configuration_id"] ?? 0);
    $relType    = (string) ($_POST["relation_type"] ?? "");
    $relId      = (int) ($_POST["relation_id"] ?? 0);
    $poolId     = isset($_POST["pool_id"]) && $_POST["pool_id"] !== "" ? (int) $_POST["pool_id"] : null;
    $subnetId   = isset($_POST["subnet_id"]) && $_POST["subnet_id"] !== "" ? (int) $_POST["subnet_id"] : null;
        if ($configId > 0 && in_array($relType, ["product", "addon", "configoption", "server"], true) && $relId > 0 && ($poolId > 0 || $subnetId > 0)) {
        try {
            $now = date("Y-m-d H:i:s");
            $exists = Capsule::table(ipmanager_table("configuration_relations"))
                ->where("configuration_id", $configId)
                ->where("relation_type", $relType)
                ->where("relation_id", $relId)
                ->exists();
            if ($exists) {
                Capsule::table(ipmanager_table("configuration_relations"))
                    ->where("configuration_id", $configId)
                    ->where("relation_type", $relType)
                    ->where("relation_id", $relId)
                    ->update(["pool_id" => $poolId, "subnet_id" => $subnetId, "updated_at" => $now]);
            } else {
                Capsule::table(ipmanager_table("configuration_relations"))->insert([
                    "configuration_id" => $configId,
                    "relation_type"    => $relType,
                    "relation_id"      => $relId,
                    "pool_id"          => $poolId,
                    "subnet_id"        => $subnetId,
                    "created_at"       => $now,
                    "updated_at"       => $now,
                ]);
            }
            header("Location: " . $modulelink . "&action=configurations&sub=edit&id=" . $configId);
            exit;
        } catch (Exception $e) {
            $configError = $e->getMessage();
        }
    }
}

if ($subAction === "relation_bulk_save" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $configId = (int) ($_POST["configuration_id"] ?? 0);
    $poolId   = isset($_POST["pool_id"]) && $_POST["pool_id"] !== "" ? (int) $_POST["pool_id"] : null;
    $subnetId = isset($_POST["subnet_id"]) && $_POST["subnet_id"] !== "" ? (int) $_POST["subnet_id"] : null;
    $productIds = isset($_POST["product_ids"]) && is_array($_POST["product_ids"]) ? array_map("intval", $_POST["product_ids"]) : [];
    $serverIds  = isset($_POST["server_ids"]) && is_array($_POST["server_ids"]) ? array_map("intval", $_POST["server_ids"]) : [];
    $productIds = array_filter($productIds, static function ($id) { return $id > 0; });
    $serverIds  = array_filter($serverIds, static function ($id) { return $id > 0; });

    if ($configId > 0 && ($poolId > 0 || $subnetId > 0)) {
        try {
            $now = date("Y-m-d H:i:s");
            Capsule::table(ipmanager_table("configuration_relations"))
                ->where("configuration_id", $configId)
                ->whereIn("relation_type", ["product", "server"])
                ->delete();

            foreach ($productIds as $relId) {
                Capsule::table(ipmanager_table("configuration_relations"))->insert([
                    "configuration_id" => $configId,
                    "relation_type"    => "product",
                    "relation_id"      => $relId,
                    "pool_id"          => $poolId,
                    "subnet_id"        => $subnetId,
                    "created_at"       => $now,
                    "updated_at"       => $now,
                ]);
            }
            foreach ($serverIds as $relId) {
                Capsule::table(ipmanager_table("configuration_relations"))->insert([
                    "configuration_id" => $configId,
                    "relation_type"    => "server",
                    "relation_id"      => $relId,
                    "pool_id"          => $poolId,
                    "subnet_id"        => $subnetId,
                    "created_at"       => $now,
                    "updated_at"       => $now,
                ]);
            }
            header("Location: " . $modulelink . "&action=configurations&sub=edit&id=" . $configId . "&bulk_saved=1");
            exit;
        } catch (Exception $e) {
            $configError = $e->getMessage();
        }
    }
}

if ($subAction === "relation_delete" && isset($_GET["rel_id"])) {
    $relId = (int) $_GET["rel_id"];
    $rel = Capsule::table(ipmanager_table("configuration_relations"))->where("id", $relId)->first();
    if ($rel) {
        $configId = (int) $rel->configuration_id;
        Capsule::table(ipmanager_table("configuration_relations"))->where("id", $relId)->delete();
        header("Location: " . $modulelink . "&action=configurations&sub=edit&id=" . $configId);
        exit;
    }
}

$subnets = Capsule::table(ipmanager_table("subnets"))->orderBy("name")->get();
$pools   = Capsule::table(ipmanager_table("pools") . " as p")
    ->leftJoin(ipmanager_table("subnets") . " as s", "s.id", "=", "p.subnet_id")
    ->select("p.id", "p.name", "p.subnet_id", "s.name as subnet_name")
    ->orderBy("p.name")
    ->get();
$products = Capsule::table("tblproducts")->orderBy("name")->get();
$addons   = Capsule::table("tbladdons")->orderBy("name")->get();
$configOptions = Capsule::table("tblproductconfigoptions")->orderBy("optionname")->get();
$servers  = Capsule::table("tblservers")->orderBy("name")->get();

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?php echo htmlspecialchars($LANG["menu_configurations"] ?? "Configurations"); ?>
        <?php if ($subAction !== "add"): ?>
            <span class="pull-right">
                <a href="<?php echo $modulelink; ?>&action=configurations&sub=add" class="btn btn-success btn-sm">
                    <i class="fa fa-plus"></i> <?php echo htmlspecialchars($LANG["add_configuration"] ?? "Add Configuration"); ?>
                </a>
            </span>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <?php if (isset($configError)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($configError); ?></div>
        <?php endif; ?>

        <?php if ($subAction === "add" || ($subAction === "edit" && $id > 0 && !isset($_GET["rel_id"]))): ?>
            <?php
            $config = null;
            if ($subAction === "edit" && $id > 0) {
                $config = Capsule::table(ipmanager_table("configurations"))->where("id", $id)->first();
            }
            ?>
            <form method="post" action="<?php echo $modulelink; ?>&action=configurations&sub=save" class="form-horizontal">
                <?php if ($config): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $config->id; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["name"] ?? "Name"); ?></label>
                    <div class="col-sm-4">
                        <input type="text" name="name" class="form-control" required
                            value="<?php echo $config ? htmlspecialchars($config->name ?? "") : ""; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-4">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="omit_dedicated_ip_field" value="1" <?php echo ($config && $config->omit_dedicated_ip_field) ? " checked" : ""; ?>>
                            <?php echo htmlspecialchars($LANG["omit_dedicated_ip"] ?? "Omit dedicated IP field"); ?>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-4">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="use_custom_field_instead_of_assigned" value="1" <?php echo ($config && $config->use_custom_field_instead_of_assigned) ? " checked" : ""; ?>>
                            <?php echo htmlspecialchars($LANG["use_custom_field_assigned"] ?? "Use custom field instead of Assigned IP"); ?>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["custom_field_name"] ?? "Custom field name"); ?></label>
                    <div class="col-sm-4">
                        <input type="text" name="custom_field_name" class="form-control"
                            value="<?php echo $config ? htmlspecialchars($config->custom_field_name ?? "") : ""; ?>">
                    </div>
                </div>
                <?php if ($config): ?>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-4">
                        <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($LANG["save"] ?? "Save"); ?></button>
                        <a href="<?php echo $modulelink; ?>&action=configurations" class="btn btn-default"><?php echo htmlspecialchars($LANG["cancel"] ?? "Cancel"); ?></a>
                    </div>
                </div>
            </form>
                <?php endif; ?>
                <?php
                if ($config) {
                    $existingProductIds = array_map("intval", Capsule::table(ipmanager_table("configuration_relations"))
                        ->where("configuration_id", $config->id)
                        ->where("relation_type", "product")
                        ->pluck("relation_id")
                        ->toArray());
                    $existingServerIds = array_map("intval", Capsule::table(ipmanager_table("configuration_relations"))
                        ->where("configuration_id", $config->id)
                        ->where("relation_type", "server")
                        ->pluck("relation_id")
                        ->toArray());
                    $existingPoolId = null;
                    $existingSubnetId = null;
                    $firstRel = Capsule::table(ipmanager_table("configuration_relations"))
                        ->where("configuration_id", $config->id)
                        ->whereIn("relation_type", ["product", "server"])
                        ->first();
                    if ($firstRel) {
                        $existingPoolId = (int) ($firstRel->pool_id ?? 0) ?: null;
                        $existingSubnetId = (int) ($firstRel->subnet_id ?? 0) ?: null;
                    }
                } else {
                    $existingProductIds = [];
                    $existingServerIds = [];
                    $existingPoolId = null;
                    $existingSubnetId = null;
                }
                ?>
                <hr>
                <h4><?php echo htmlspecialchars($LANG["assign_pool_products_servers"] ?? "Assign pool to products and servers"); ?></h4>
                <p class="text-muted small"><?php echo htmlspecialchars($LANG["assign_pool_products_servers_help"] ?? "Select a Pool or Subnet, then check one or more Products and/or Servers (clusters/nodes). Save to apply. These will receive an IP from the chosen pool when provisioned."); ?></p>
                <?php if ($config): ?>
                <form method="post" action="<?php echo $modulelink; ?>&action=configurations&sub=relation_bulk_save" class="form-horizontal">
                    <input type="hidden" name="configuration_id" value="<?php echo (int) $config->id; ?>">
                <?php endif; ?>
                    <div class="form-group">
                        <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["pool"] ?? "Pool"); ?></label>
                        <div class="col-sm-4">
                            <select name="pool_id" class="form-control" id="bulk-pool-id">
                                <option value="">— <?php echo htmlspecialchars($LANG["pool"] ?? "Pool"); ?> —</option>
                                <?php foreach ($pools as $pl): ?>
                                    <option value="<?php echo (int) $pl->id; ?>"<?php echo ($existingPoolId === (int) $pl->id) ? " selected" : ""; ?>><?php echo htmlspecialchars($pl->name); ?> (<?php echo htmlspecialchars($pl->subnet_name ?? ""); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet"] ?? "Subnet"); ?></label>
                        <div class="col-sm-4">
                            <select name="subnet_id" class="form-control" id="bulk-subnet-id">
                                <option value="">— <?php echo htmlspecialchars($LANG["subnet"] ?? "Subnet"); ?> —</option>
                                <?php foreach ($subnets as $sn): ?>
                                    <option value="<?php echo (int) $sn->id; ?>"<?php echo ($existingSubnetId === (int) $sn->id) ? " selected" : ""; ?>><?php echo htmlspecialchars($sn->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-block small"><?php echo htmlspecialchars($LANG["pool_or_subnet_required"] ?? "Select at least one Pool or Subnet."); ?></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["products"] ?? "Products"); ?></label>
                        <div class="col-sm-6">
                            <div class="well well-sm" style="max-height: 200px; overflow-y: auto;">
                                <?php if ($products->isEmpty()): ?>
                                    <p class="text-muted"><?php echo htmlspecialchars($LANG["no_products"] ?? "No products."); ?></p>
                                <?php else: ?>
                                    <?php foreach ($products as $pr): ?>
                                        <label class="checkbox-inline" style="display: block; margin-left: 0;">
                                            <input type="checkbox" name="product_ids[]" value="<?php echo (int) $pr->id; ?>"<?php echo in_array((int) $pr->id, $existingProductIds, true) ? " checked" : ""; ?>>
                                            <?php echo htmlspecialchars($pr->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["servers_nodes"] ?? "Servers (clusters / nodes)"); ?></label>
                        <div class="col-sm-6">
                            <div class="well well-sm" style="max-height: 200px; overflow-y: auto;">
                                <?php if ($servers->isEmpty()): ?>
                                    <p class="text-muted"><?php echo htmlspecialchars($LANG["no_servers"] ?? "No servers. Add servers in Setup → Products/Services → Servers."); ?></p>
                                <?php else: ?>
                                    <?php foreach ($servers as $sv): ?>
                                        <label class="checkbox-inline" style="display: block; margin-left: 0;">
                                            <input type="checkbox" name="server_ids[]" value="<?php echo (int) $sv->id; ?>"<?php echo in_array((int) $sv->id, $existingServerIds, true) ? " checked" : ""; ?>>
                                            <?php echo htmlspecialchars($sv->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-6">
                            <?php if ($config): ?>
                                <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($LANG["save_products_servers"] ?? "Save products and servers selection"); ?></button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($LANG["create_configuration"] ?? "Create configuration"); ?></button>
                            <a href="<?php echo $modulelink; ?>&action=configurations" class="btn btn-default"><?php echo htmlspecialchars($LANG["cancel"] ?? "Cancel"); ?></a>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                <?php if ($config && !empty($_GET["bulk_saved"])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($LANG["bulk_saved"] ?? "Products and servers selection saved."); ?></div>
                <?php endif; ?>
                <?php if ($config): ?>
                <hr>
                <h4><?php echo htmlspecialchars($LANG["configuration_relations"] ?? "Relations (Products, Addons, Config Options, Servers)"); ?></h4>
                <table class="table table-striped table-condensed">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars($LANG["relation_type"] ?? "Type"); ?></th>
                            <th><?php echo htmlspecialchars($LANG["relation_id"] ?? "Target"); ?></th>
                            <th><?php echo htmlspecialchars($LANG["pool_subnet"] ?? "Pool / Subnet"); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rels = Capsule::table(ipmanager_table("configuration_relations") . " as r")
                            ->leftJoin(ipmanager_table("pools") . " as p", "p.id", "=", "r.pool_id")
                            ->leftJoin(ipmanager_table("subnets") . " as s", "s.id", "=", "r.subnet_id")
                            ->where("r.configuration_id", $config->id)
                            ->select("r.*", "p.name as pool_name", "s.name as subnet_name")
                            ->get();
                        foreach ($rels as $r):
                            $targetName = $r->relation_id;
                            if ($r->relation_type === "product") {
                                $prod = Capsule::table("tblproducts")->where("id", $r->relation_id)->first();
                                $targetName = $prod ? $prod->name : $r->relation_id;
                            } elseif ($r->relation_type === "addon") {
                                $a = Capsule::table("tbladdons")->where("id", $r->relation_id)->first();
                                $targetName = $a ? $a->name : $r->relation_id;
                            } elseif ($r->relation_type === "configoption") {
                                $c = Capsule::table("tblproductconfigoptions")->where("id", $r->relation_id)->first();
                                $targetName = $c ? ($c->optionname ?? $c->name ?? $r->relation_id) : $r->relation_id;
                            } elseif ($r->relation_type === "server") {
                                $s = Capsule::table("tblservers")->where("id", $r->relation_id)->first();
                                $targetName = $s ? $s->name : $r->relation_id;
                            }
                            $poolSubnet = ($r->pool_name ? $r->pool_name : "") . ($r->subnet_name ? " / " . $r->subnet_name : "");
                            if ($poolSubnet === " / ") {
                                $poolSubnet = "—";
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r->relation_type); ?></td>
                                <td><?php echo htmlspecialchars($targetName); ?></td>
                                <td><?php echo htmlspecialchars($poolSubnet); ?></td>
                                <td>
                                    <a href="<?php echo $modulelink; ?>&action=configurations&sub=relation_delete&rel_id=<?php echo (int) $r->id; ?>" class="btn btn-xs btn-danger" onclick="return confirm('<?php echo htmlspecialchars($LANG["confirm_delete"] ?? "Delete?"); ?>');"><?php echo $LANG["delete"] ?? "Delete"; ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <form method="post" action="<?php echo $modulelink; ?>&action=configurations&sub=relation_save" class="form-inline" id="relation-form">
                    <input type="hidden" name="configuration_id" value="<?php echo (int) $config->id; ?>">
                    <select name="relation_type" id="rel-type" class="form-control" required>
                        <option value="">— <?php echo htmlspecialchars($LANG["select_type"] ?? "Type"); ?> —</option>
                        <option value="product"><?php echo htmlspecialchars($LANG["product"] ?? "Product"); ?></option>
                        <option value="addon"><?php echo htmlspecialchars($LANG["addon"] ?? "Addon"); ?></option>
                        <option value="configoption"><?php echo htmlspecialchars($LANG["configoption"] ?? "Config Option"); ?></option>
                        <option value="server"><?php echo htmlspecialchars($LANG["server"] ?? "Server"); ?></option>
                    </select>
                    <select name="relation_id" id="rel-id" class="form-control" required>
                        <option value="">— <?php echo htmlspecialchars($LANG["select"] ?? "Select"); ?> —</option>
                    </select>
                    <select name="pool_id" class="form-control">
                        <option value="">— <?php echo htmlspecialchars($LANG["pool"] ?? "Pool"); ?> —</option>
                        <?php foreach ($pools as $pl): ?>
                            <option value="<?php echo (int) $pl->id; ?>"><?php echo htmlspecialchars($pl->name); ?> (<?php echo htmlspecialchars($pl->subnet_name ?? ""); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <select name="subnet_id" class="form-control">
                        <option value="">— <?php echo htmlspecialchars($LANG["subnet"] ?? "Subnet"); ?> —</option>
                        <?php foreach ($subnets as $sn): ?>
                            <option value="<?php echo (int) $sn->id; ?>"><?php echo htmlspecialchars($sn->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($LANG["add_relation"] ?? "Add"); ?></button>
                </form>
                <script>
                (function(){
                    var data = {
                        product: [<?php $a=[]; foreach($products as $pr){ $a[]='{id:'.(int)$pr->id.',name:'.json_encode($pr->name).'}'; } echo implode(',',$a); ?>],
                        addon: [<?php $a=[]; foreach($addons as $ad){ $a[]='{id:'.(int)$ad->id.',name:'.json_encode($ad->name).'}'; } echo implode(',',$a); ?>],
                        configoption: [<?php $a=[]; foreach($configOptions as $co){ $a[]='{id:'.(int)$co->id.',name:'.json_encode($co->optionname ?? $co->name ?? '').'}'; } echo implode(',',$a); ?>],
                        server: [<?php $a=[]; foreach($servers as $sv){ $a[]='{id:'.(int)$sv->id.',name:'.json_encode($sv->name).'}'; } echo implode(',',$a); ?>]
                    };
                    var sel = document.getElementById('rel-type'), relId = document.getElementById('rel-id');
                    sel.onchange = function(){
                        var type = this.value, opts = '<option value="">— Select —</option>';
                        if (data[type]) for (var i=0;i<data[type].length;i++) opts += '<option value="'+data[type][i].id+'">'+data[type][i].name+'</option>';
                        relId.innerHTML = opts;
                    };
                })();
                </script>
                <p class="text-muted small"><?php echo htmlspecialchars($LANG["relation_help"] ?? "Select type, then one target, then either a Pool or Subnet for IP assignment."); ?></p>
                <p class="text-muted small"><strong><?php echo htmlspecialchars($LANG["relation_help_product"] ?? "Product"); ?>:</strong> <?php echo htmlspecialchars($LANG["relation_help_product_desc"] ?? "Any order of this product gets an IP from the chosen pool (any server/cluster)."); ?>
                    <strong><?php echo htmlspecialchars($LANG["relation_help_server"] ?? "Server"); ?>:</strong> <?php echo htmlspecialchars($LANG["relation_help_server_desc"] ?? "Any service on this server (cluster or node from WHMCS Servers) gets an IP from the pool. Add one relation per server if you have multiple clusters/nodes."); ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted"><?php echo htmlspecialchars($LANG["configurations_info"] ?? "Create configurations for different IP assignment scenarios (products, addons, configurable options, servers)."); ?></p>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars($LANG["name"] ?? "Name"); ?></th>
                        <th><?php echo htmlspecialchars($LANG["actions"] ?? "Actions"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (Capsule::table(ipmanager_table("configurations"))->orderBy("sort_order")->orderBy("name")->get() as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c->name); ?></td>
                            <td>
                                <a href="<?php echo $modulelink; ?>&action=configurations&sub=edit&id=<?php echo (int) $c->id; ?>" class="btn btn-xs btn-default"><?php echo $LANG["edit"] ?? "Edit"; ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (Capsule::table(ipmanager_table("configurations"))->count() === 0): ?>
                <p class="text-muted"><?php echo htmlspecialchars($LANG["no_configurations"] ?? "No configurations yet."); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php

/**
 * IP Pools admin - full CRUD and link to configurations.
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
$subnetId  = isset($_GET["subnet_id"]) ? (int) $_GET["subnet_id"] : null;

if ($subAction === "save" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim((string) ($_POST["name"] ?? ""));
    $subnetIdPost = (int) ($_POST["subnet_id"] ?? 0);
    $cidr     = trim((string) ($_POST["cidr"] ?? ""));
    $editId   = (int) ($_POST["id"] ?? 0);
    if ($name !== "" && $subnetIdPost > 0) {
        $startIp = null;
        $endIp   = null;
        $version = 4;
        if ($cidr !== "") {
            $range = ipmanager_cidr_to_range($cidr);
            if ($range !== null) {
                $startIp = $range[0];
                $endIp   = $range[1];
                $version = strpos($cidr, ":") !== false ? 6 : 4;
            }
        }
        try {
            $now = date("Y-m-d H:i:s");
            if ($editId > 0) {
                Capsule::table(ipmanager_table("pools"))->where("id", $editId)->update([
                    "name"       => $name,
                    "cidr"       => $cidr !== "" ? $cidr : null,
                    "start_ip"   => $startIp,
                    "end_ip"     => $endIp,
                    "version"    => $version,
                    "updated_at" => $now,
                ]);
            } else {
                Capsule::table(ipmanager_table("pools"))->insert([
                    "subnet_id"  => $subnetIdPost,
                    "name"       => $name,
                    "cidr"       => $cidr !== "" ? $cidr : null,
                    "start_ip"   => $startIp,
                    "end_ip"     => $endIp,
                    "version"    => $version,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
            }
            header("Location: " . $modulelink . "&action=pools&subnet_id=" . $subnetIdPost);
            exit;
        } catch (Exception $e) {
            $poolError = $e->getMessage();
        }
    } else {
        $poolError = $LANG["pool_name_subnet_required"] ?? "Name and Subnet are required.";
    }
    $subAction = $editId > 0 ? "edit" : "add";
    $id = $editId;
}

if ($subAction === "delete" && $id > 0 && $_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $pool = Capsule::table(ipmanager_table("pools"))->where("id", $id)->first();
        $sid = $pool ? (int) $pool->subnet_id : 0;
        Capsule::table(ipmanager_table("pools"))->where("id", $id)->delete();
        header("Location: " . $modulelink . "&action=pools" . ($sid ? "&subnet_id=" . $sid : ""));
        exit;
    } catch (Exception $e) {
        $poolError = $e->getMessage();
    }
}

$subnets = Capsule::table(ipmanager_table("subnets"))->orderBy("name")->get();
$poolSubnetId = $subnetId;
if ($poolSubnetId === null && $subnets->isNotEmpty()) {
    $poolSubnetId = (int) $subnets->first()->id;
}

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?php echo htmlspecialchars($LANG["menu_pools"] ?? "IP Pools"); ?>
        <span class="pull-right">
            <a href="<?php echo $modulelink; ?>&action=pools&sub=add&subnet_id=<?php echo (int) $poolSubnetId; ?>" class="btn btn-success btn-sm">
                <i class="fa fa-plus"></i> <?php echo htmlspecialchars($LANG["add_pool"] ?? "Add Pool"); ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?php if (isset($poolError)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($poolError); ?></div>
        <?php endif; ?>

        <?php if ($subAction === "add" || $subAction === "edit"): ?>
            <?php
            $pool = null;
            if ($subAction === "edit" && $id > 0) {
                $pool = Capsule::table(ipmanager_table("pools"))->where("id", $id)->first();
                if ($pool) {
                    $poolSubnetId = (int) $pool->subnet_id;
                }
            }
            ?>
            <form method="post" action="<?php echo $modulelink; ?>&action=pools&sub=save" class="form-horizontal">
                <?php if ($pool): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $pool->id; ?>">
                    <input type="hidden" name="subnet_id" value="<?php echo (int) $pool->subnet_id; ?>">
                <?php else: ?>
                    <input type="hidden" name="subnet_id" value="<?php echo (int) $poolSubnetId; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["pool_name"] ?? "Name"); ?></label>
                    <div class="col-sm-4">
                        <input type="text" name="name" class="form-control" required
                            value="<?php echo $pool ? htmlspecialchars($pool->name ?? "") : ""; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["pool_subnet"] ?? "Subnet"); ?></label>
                    <div class="col-sm-4">
                        <select name="subnet_id" class="form-control" <?php echo $pool ? "disabled" : ""; ?>>
                            <?php foreach ($subnets as $s): ?>
                                <option value="<?php echo (int) $s->id; ?>" <?php echo ($poolSubnetId === (int) $s->id) ? " selected" : ""; ?>><?php echo htmlspecialchars($s->name); ?> (<?php echo htmlspecialchars($s->cidr); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($pool): ?>
                            <input type="hidden" name="subnet_id" value="<?php echo (int) $pool->subnet_id; ?>">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet_cidr"] ?? "CIDR"); ?></label>
                    <div class="col-sm-4">
                        <input type="text" name="cidr" class="form-control" placeholder="Optional: 192.168.1.0/28"
                            value="<?php echo $pool ? htmlspecialchars($pool->cidr ?? "") : ""; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-4">
                        <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($LANG["save"] ?? "Save"); ?></button>
                        <a href="<?php echo $modulelink; ?>&action=pools<?php echo $poolSubnetId ? "&subnet_id=" . $poolSubnetId : ""; ?>" class="btn btn-default"><?php echo htmlspecialchars($LANG["cancel"] ?? "Cancel"); ?></a>
                    </div>
                </div>
            </form>
        <?php elseif ($subAction === "delete" && $id > 0): ?>
            <?php $pool = Capsule::table(ipmanager_table("pools"))->where("id", $id)->first(); ?>
            <?php if ($pool): ?>
                <div class="alert alert-warning">
                    <?php echo htmlspecialchars($LANG["pool_delete_confirm"] ?? "Delete this pool? IPs in this pool will be unlinked."); ?>
                </div>
                <form method="post" action="<?php echo $modulelink; ?>&action=pools&sub=delete&id=<?php echo $id; ?>">
                    <button type="submit" class="btn btn-danger"><?php echo htmlspecialchars($LANG["delete"] ?? "Delete"); ?></button>
                    <a href="<?php echo $modulelink; ?>&action=pools&subnet_id=<?php echo (int) $pool->subnet_id; ?>" class="btn btn-default"><?php echo htmlspecialchars($LANG["cancel"] ?? "Cancel"); ?></a>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="form-inline" style="margin-bottom:15px">
                <label><?php echo htmlspecialchars($LANG["filter_subnet"] ?? "Subnet"); ?></label>
                <select class="form-control" onchange="location.href=this.value">
                    <option value="<?php echo $modulelink; ?>&action=pools">— <?php echo htmlspecialchars($LANG["all"] ?? "All"); ?> —</option>
                    <?php foreach ($subnets as $s): ?>
                        <option value="<?php echo $modulelink; ?>&action=pools&subnet_id=<?php echo (int) $s->id; ?>" <?php echo $poolSubnetId === (int) $s->id ? " selected" : ""; ?>><?php echo htmlspecialchars($s->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            $q = Capsule::table(ipmanager_table("pools") . " as p")
                ->leftJoin(ipmanager_table("subnets") . " as s", "s.id", "=", "p.subnet_id")
                ->select("p.*", "s.name as subnet_name", "s.cidr as subnet_cidr");
            if ($poolSubnetId !== null) {
                $q->where("p.subnet_id", $poolSubnetId);
            }
            $pools = $q->orderBy("p.name")->get();
            ?>
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars($LANG["pool_name"] ?? "Name"); ?></th>
                        <th><?php echo htmlspecialchars($LANG["pool_subnet"] ?? "Subnet"); ?></th>
                        <th><?php echo htmlspecialchars($LANG["subnet_cidr"] ?? "CIDR"); ?></th>
                        <th><?php echo htmlspecialchars($LANG["actions"] ?? "Actions"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pools as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p->name); ?></td>
                            <td><?php echo htmlspecialchars($p->subnet_name ?? "—"); ?></td>
                            <td><code><?php echo htmlspecialchars($p->cidr ?? "—"); ?></code></td>
                            <td>
                                <a href="<?php echo $modulelink; ?>&action=pools&sub=edit&id=<?php echo (int) $p->id; ?>" class="btn btn-xs btn-default"><?php echo $LANG["edit"] ?? "Edit"; ?></a>
                                <a href="<?php echo $modulelink; ?>&action=pools&sub=delete&id=<?php echo (int) $p->id; ?>" class="btn btn-xs btn-danger"><?php echo $LANG["delete"] ?? "Delete"; ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($pools->isEmpty()): ?>
                <p class="text-muted"><?php echo htmlspecialchars($LANG["no_pools"] ?? "No pools. Add a pool to a subnet first."); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

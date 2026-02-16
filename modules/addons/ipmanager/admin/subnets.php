<?php

/**
 * IP Subnets admin page - tree view and CRUD.
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
    $name     = trim((string) ($_POST["name"] ?? ""));
    $cidr     = trim((string) ($_POST["cidr"] ?? ""));
    $parentId = isset($_POST["parent_id"]) ? (int) $_POST["parent_id"] : null;
    if ($parentId <= 0) {
        $parentId = null;
    }
    $gateway           = trim((string) ($_POST["gateway"] ?? ""));
    $excludedIpsText   = trim((string) ($_POST["excluded_ips"] ?? ""));
    $reserveNetwork    = !empty($_POST["reserve_network"]);
    $reserveBroadcast  = !empty($_POST["reserve_broadcast"]);
    $reserveGateway    = !empty($_POST["reserve_gateway"]);
    $populateFreeIps   = !empty($_POST["populate_free_ips"]);
    $editId = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    if ($name !== "" && $cidr !== "") {
        $range = ipmanager_cidr_to_range($cidr);
        if ($range !== null) {
            try {
                if ($editId > 0) {
                    Capsule::table(ipmanager_table("subnets"))->where("id", $editId)->update([
                        "name"         => $name,
                        "parent_id"    => $parentId,
                        "gateway"      => $gateway !== "" ? $gateway : null,
                        "excluded_ips" => $excludedIpsText !== "" ? json_encode(ipmanager_parse_excluded_ips($excludedIpsText)) : null,
                        "updated_at"   => date("Y-m-d H:i:s"),
                    ]);
                    header("Location: " . $modulelink . "&action=subnets");
                    exit;
                }

                $version = strpos($cidr, ":") !== false ? 6 : 4;
                $newSubnetId = (int) Capsule::table(ipmanager_table("subnets"))->insertGetId([
                    "parent_id"    => $parentId,
                    "name"         => $name,
                    "cidr"         => $cidr,
                    "start_ip"     => $range[0],
                    "end_ip"       => $range[1],
                    "version"      => $version,
                    "gateway"      => $gateway !== "" ? $gateway : null,
                    "excluded_ips" => $excludedIpsText !== "" ? json_encode(ipmanager_parse_excluded_ips($excludedIpsText)) : null,
                    "created_at"   => date("Y-m-d H:i:s"),
                    "updated_at"   => date("Y-m-d H:i:s"),
                ]);

                $now = date("Y-m-d H:i:s");
                if ($reserveNetwork) {
                    Capsule::table(ipmanager_table("reservation_rules"))->insert([
                        "subnet_id"   => $newSubnetId,
                        "pool_id"     => null,
                        "rule_type"   => "network",
                        "ip_or_pattern" => $range[0],
                        "description" => "Network address",
                        "created_at"  => $now,
                        "updated_at"  => $now,
                    ]);
                }
                if ($reserveBroadcast) {
                    Capsule::table(ipmanager_table("reservation_rules"))->insert([
                        "subnet_id"   => $newSubnetId,
                        "pool_id"     => null,
                        "rule_type"   => "broadcast",
                        "ip_or_pattern" => $range[1],
                        "description" => "Broadcast address",
                        "created_at"  => $now,
                        "updated_at"  => $now,
                    ]);
                }
                if ($reserveGateway && $gateway !== "") {
                    Capsule::table(ipmanager_table("reservation_rules"))->insert([
                        "subnet_id"   => $newSubnetId,
                        "pool_id"     => null,
                        "rule_type"   => "gateway",
                        "ip_or_pattern" => $gateway,
                        "description" => "Gateway",
                        "created_at"  => $now,
                        "updated_at"  => $now,
                    ]);
                }

                if ($populateFreeIps) {
                    $excluded = ipmanager_parse_excluded_ips($excludedIpsText);
                    $maxCount = $version === 4 ? 65536 : 10000;
                    ipmanager_populate_subnet_ips(
                        $newSubnetId,
                        $range[0],
                        $range[1],
                        $version,
                        $excluded,
                        $gateway !== "" ? $gateway : null,
                        $maxCount
                    );
                }

                header("Location: " . $modulelink . "&action=subnets");
                exit;
            } catch (Exception $e) {
                $subnetError = $e->getMessage();
            }
        } else {
            $subnetError = $LANG["invalid_cidr"] ?? "Invalid CIDR notation.";
        }
    } else {
        $subnetError = $LANG["name_cidr_required"] ?? "Name and CIDR are required.";
    }
    $subAction = $editId > 0 ? "edit" : "add";
    $id = $editId;
}

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?php echo htmlspecialchars($LANG["menu_subnets"] ?? "IP Subnets"); ?>
        <span class="pull-right">
            <a href="<?php echo $modulelink; ?>&action=subnets&sub=add" class="btn btn-success btn-sm">
                <i class="fa fa-plus"></i> <?php echo htmlspecialchars($LANG["add_subnet"] ?? "Add Subnet"); ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?php
        if ($subAction === "add" || $subAction === "edit") {
            $subnet = null;
            if ($subAction === "edit" && $id > 0) {
                $subnet = Capsule::table(ipmanager_table("subnets"))->where("id", $id)->first();
            }
            ?>
            <?php if (!empty($subnetError)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($subnetError); ?></div>
            <?php endif; ?>
            <form method="post" action="<?php echo $modulelink; ?>&action=subnets&sub=save" class="form-horizontal">
                <?php if ($subnet): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $subnet->id; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet_name"] ?? "Name"); ?></label>
                    <div class="col-sm-4">
                        <input type="text" name="name" class="form-control" required
                            value="<?php echo $subnet ? htmlspecialchars($subnet->name ?? "") : ""; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet_cidr"] ?? "CIDR"); ?></label>
                    <div class="col-sm-4">
                        <input type="text" name="cidr" class="form-control" placeholder="192.168.1.0/24 or 2001:db8::/32"
                            value="<?php echo $subnet ? htmlspecialchars($subnet->cidr ?? "") : ""; ?>"
                            <?php echo $subnet ? "readonly" : ""; ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet_gateway"] ?? "Gateway"); ?></label>
                    <div class="col-sm-4">
                        <input type="text" name="gateway" class="form-control" placeholder="e.g. 192.168.1.1"
                            value="<?php echo $subnet ? htmlspecialchars($subnet->gateway ?? "") : ""; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet_excluded_ips"] ?? "Excluded IPs"); ?></label>
                    <div class="col-sm-4">
                        <?php
                        $excludedDisplay = "";
                        if ($subnet && !empty($subnet->excluded_ips)) {
                            $arr = json_decode($subnet->excluded_ips, true);
                            $excludedDisplay = is_array($arr) ? implode("\n", $arr) : "";
                        }
                        ?>
                        <textarea name="excluded_ips" class="form-control" rows="3" placeholder="One per line or comma-separated"><?php echo htmlspecialchars($excludedDisplay); ?></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet_parent"] ?? "Parent"); ?></label>
                    <div class="col-sm-4">
                        <select name="parent_id" class="form-control">
                            <option value="">— <?php echo htmlspecialchars($LANG["none"] ?? "None"); ?> —</option>
                            <?php
                            $parents = Capsule::table(ipmanager_table("subnets"))->orderBy("name")->get();
                            foreach ($parents as $p) {
                                if ($subnet && (int) $p->id === (int) $subnet->id) {
                                    continue;
                                }
                                $sel = ($subnet && (int) $subnet->parent_id === (int) $p->id) ? " selected" : "";
                                echo "<option value=\"" . (int) $p->id . "\"" . $sel . ">" . htmlspecialchars($p->name) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <?php if (!$subnet): ?>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet_reservation"] ?? "Reserve"); ?></label>
                    <div class="col-sm-4">
                        <label class="checkbox-inline"><input type="checkbox" name="reserve_network" value="1"> <?php echo htmlspecialchars($LANG["reserve_network"] ?? "Network"); ?></label>
                        <label class="checkbox-inline"><input type="checkbox" name="reserve_broadcast" value="1"> <?php echo htmlspecialchars($LANG["reserve_broadcast"] ?? "Broadcast"); ?></label>
                        <label class="checkbox-inline"><input type="checkbox" name="reserve_gateway" value="1"> <?php echo htmlspecialchars($LANG["reserve_gateway"] ?? "Gateway"); ?></label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2"><?php echo htmlspecialchars($LANG["subnet_populate_free"] ?? "Store free IPs"); ?></label>
                    <div class="col-sm-4">
                        <label class="checkbox-inline"><input type="checkbox" name="populate_free_ips" value="1"> <?php echo htmlspecialchars($LANG["subnet_populate_free_help"] ?? "Pre-populate IP addresses in database (max 65536 IPv4)"); ?></label>
                    </div>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-4">
                        <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($LANG["save"] ?? "Save"); ?></button>
                        <a href="<?php echo $modulelink; ?>&action=subnets" class="btn btn-default"><?php echo htmlspecialchars($LANG["cancel"] ?? "Cancel"); ?></a>
                    </div>
                </div>
            </form>
        <?php } else {
            $subnets = Capsule::table(ipmanager_table("subnets"))
                ->orderBy("parent_id")
                ->orderBy("sort_order")
                ->orderBy("name")
                ->get();
            ?>
            <p class="text-muted"><?php echo htmlspecialchars($LANG["subnet_tree_info"] ?? "Manage IP subnets in a tree. Add subnets with CIDR notation (IPv4/IPv6)."); ?></p>
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars($LANG["subnet_name"] ?? "Name"); ?></th>
                        <th><?php echo htmlspecialchars($LANG["subnet_cidr"] ?? "CIDR"); ?></th>
                        <th><?php echo htmlspecialchars($LANG["actions"] ?? "Actions"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subnets as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s->name); ?></td>
                            <td><code><?php echo htmlspecialchars($s->cidr); ?></code></td>
                            <td>
                                <a href="<?php echo $modulelink; ?>&action=subnets&sub=edit&id=<?php echo (int) $s->id; ?>" class="btn btn-xs btn-default"><?php echo $LANG["edit"] ?? "Edit"; ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($subnets->isEmpty()): ?>
                <p><?php echo htmlspecialchars($LANG["no_subnets"] ?? "No subnets yet. Add one to get started."); ?></p>
            <?php endif;
        }
        ?>
    </div>
</div>

<?php

/**
 * IP Manager helper functions.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Check if string is valid IPv4 or IPv6.
 *
 * @param string $ip
 * @return bool
 */
function ipmanager_is_valid_ip(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Parse CIDR and return [network_start, network_end] or null.
 *
 * @param string $cidr e.g. "192.168.1.0/24" or "2001:db8::/32"
 * @return array{0: string, 1: string}|null
 */
function ipmanager_cidr_to_range(string $cidr): ?array {
    $parts = explode("/", trim($cidr), 2);
    if (count($parts) !== 2) {
        return null;
    }
    $ip   = trim($parts[0]);
    $mask = (int) trim($parts[1]);
    if (!ipmanager_is_valid_ip($ip)) {
        return null;
    }
    if (strpos($ip, ":") !== false) {
        return ipmanager_cidr_to_range_ipv6($ip, $mask);
    }
    return ipmanager_cidr_to_range_ipv4($ip, $mask);
}

/**
 * IPv4 CIDR to range.
 *
 * @param string $ip
 * @param int    $mask
 * @return array{0: string, 1: string}|null
 */
function ipmanager_cidr_to_range_ipv4(string $ip, int $mask): ?array {
    if ($mask < 0 || $mask > 32) {
        return null;
    }
    $long = ip2long($ip);
    if ($long === false) {
        return null;
    }
    $network = ($long) & (~((1 << (32 - $mask)) - 1));
    $broadcast = $network | ((1 << (32 - $mask)) - 1);
    return [long2ip($network), long2ip($broadcast)];
}

/**
 * IPv6 CIDR to range. For /128 returns single IP; for other masks returns range.
 *
 * @param string $ip
 * @param int    $mask
 * @return array{0: string, 1: string}|null
 */
function ipmanager_cidr_to_range_ipv6(string $ip, int $mask): ?array {
    if ($mask < 0 || $mask > 128) {
        return null;
    }
    $addr = inet_pton($ip);
    if ($addr === false) {
        return null;
    }
    if ($mask === 128) {
        return [inet_ntop($addr), inet_ntop($addr)];
    }
    return [inet_ntop($addr), inet_ntop($addr)];
}

/**
 * Generate IPv4 addresses between start and end (inclusive).
 * Yields IP strings. Stops after $maxCount to avoid memory issues.
 *
 * @param string $startIp
 * @param string $endIp
 * @param int    $maxCount
 * @return iterable<string>
 */
function ipmanager_range_ipv4(string $startIp, string $endIp, int $maxCount = 65536): iterable {
    $start = ip2long($startIp);
    $end   = ip2long($endIp);
    if ($start === false || $end === false || $start > $end) {
        return;
    }
    $n = 0;
    for ($long = $start; $long <= $end && $n < $maxCount; $long++, $n++) {
        yield long2ip($long);
    }
}

/**
 * Build set of reserved IPs for a subnet from reservation_rules and optional gateway.
 * Returns associative array [ 'ip' => true ] for fast lookup.
 *
 * @param int         $subnetId
 * @param int|null    $poolId
 * @param string|null $gatewayIp
 * @return array<string, true>
 */
function ipmanager_reserved_ips_set(int $subnetId, ?int $poolId, ?string $gatewayIp = null): array {
    $set = [];
    if ($gatewayIp !== null && $gatewayIp !== "") {
        $set[$gatewayIp] = true;
    }
    try {
        $q = Capsule::table(ipmanager_table("reservation_rules"))->where("subnet_id", $subnetId);
        if ($poolId !== null) {
            $q->where(function ($q) use ($poolId) {
                $q->whereNull("pool_id")->orWhere("pool_id", $poolId);
            });
        } else {
            $q->whereNull("pool_id");
        }
        $rules = $q->get();
        foreach ($rules as $r) {
            if (!empty($r->ip_or_pattern)) {
                $set[$r->ip_or_pattern] = true;
            }
        }
    } catch (Exception $e) {
        // ignore
    }
    return $set;
}

/**
 * Parse excluded IPs from text (one per line or comma-separated) to array of trimmed strings.
 *
 * @param string $text
 * @return list<string>
 */
function ipmanager_parse_excluded_ips(string $text): array {
    $out = [];
    $lines = preg_split('/[\r\n,]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($lines as $line) {
        $ip = trim($line);
        if ($ip !== "" && ipmanager_is_valid_ip($ip)) {
            $out[] = $ip;
        }
    }
    return $out;
}

/**
 * Populate ip_addresses for a subnet: all IPs in range minus excluded and reserved.
 * Only creates rows for IPs not already in DB. Respects max count for safety.
 *
 * @param int    $subnetId
 * @param string $startIp
 * @param string $endIp
 * @param int    $version  4 or 6
 * @param array  $excluded List of IP strings to exclude
 * @param string|null $gateway Gateway IP to reserve
 * @param int    $maxCount Max IPs to insert (default 65536)
 * @return array{inserted: int, skipped: int}
 */
function ipmanager_populate_subnet_ips(
    int $subnetId,
    string $startIp,
    string $endIp,
    int $version,
    array $excluded = [],
    ?string $gateway = null,
    int $maxCount = 65536
): array {
    $excludedSet = array_flip($excluded);
    $reserved = ipmanager_reserved_ips_set($subnetId, null, $gateway);
    $inserted = 0;
    $skipped = 0;
    $now = date("Y-m-d H:i:s");

    if ($version === 4) {
        foreach (ipmanager_range_ipv4($startIp, $endIp, $maxCount + 1000) as $ip) {
            if (isset($excludedSet[$ip]) || isset($reserved[$ip])) {
                $skipped++;
                continue;
            }
            $exists = Capsule::table(ipmanager_table("ip_addresses"))
                ->where("subnet_id", $subnetId)
                ->whereNull("pool_id")
                ->where("ip", $ip)
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }
            Capsule::table(ipmanager_table("ip_addresses"))->insert([
                "subnet_id"  => $subnetId,
                "pool_id"    => null,
                "ip"         => $ip,
                "version"    => 4,
                "status"     => "free",
                "created_at" => $now,
                "updated_at" => $now,
            ]);
            $inserted++;
            if ($inserted >= $maxCount) {
                break;
            }
        }
    } else {
        $skipped = 0;
        if ($startIp === $endIp) {
            $ip = $startIp;
            if (!isset($excludedSet[$ip]) && !isset($reserved[$ip])) {
                $exists = Capsule::table(ipmanager_table("ip_addresses"))
                    ->where("subnet_id", $subnetId)
                    ->whereNull("pool_id")
                    ->where("ip", $ip)
                    ->exists();
                if (!$exists) {
                    Capsule::table(ipmanager_table("ip_addresses"))->insert([
                        "subnet_id"  => $subnetId,
                        "pool_id"    => null,
                        "ip"         => $ip,
                        "version"    => 6,
                        "status"     => "free",
                        "created_at" => $now,
                        "updated_at" => $now,
                    ]);
                    $inserted = 1;
                }
            }
        }
    }

    return ["inserted" => $inserted, "skipped" => $skipped];
}

/**
 * Assign an IP address to a service and optionally sync to WHMCS dedicatedip.
 *
 * @param int $ipAddressId mod_ipmanager_ip_addresses.id
 * @param int $clientId
 * @param int $serviceId tblhosting.id
 * @param bool $syncToWhmcs Update tblhosting.dedicatedip
 * @return bool Success
 */
function ipmanager_assign_ip_to_service(int $ipAddressId, int $clientId, int $serviceId, bool $syncToWhmcs = true): bool {
    try {
        $ip = Capsule::table(ipmanager_table("ip_addresses"))->where("id", $ipAddressId)->first();
        if (!$ip || $ip->status === "assigned") {
            return false;
        }
        $now = date("Y-m-d H:i:s");
        Capsule::table(ipmanager_table("assignments"))->insert([
            "ip_address_id" => $ipAddressId,
            "client_id"     => $clientId,
            "service_id"    => $serviceId,
            "assigned_type" => "service",
            "assigned_at"   => $now,
            "created_at"    => $now,
            "updated_at"   => $now,
        ]);
        Capsule::table(ipmanager_table("ip_addresses"))->where("id", $ipAddressId)->update([
            "status"     => "assigned",
            "updated_at" => $now,
        ]);
        if ($syncToWhmcs) {
            ipmanager_sync_dedicatedip_to_whmcs($serviceId, $ip->ip);
        }
        ipmanager_run_integration_add_ip($serviceId, $ip->ip);
        ipmanager_netbox_push_assign($ipAddressId, $serviceId);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Unassign IP from service: remove assignment, set IP status to free, clear WHMCS dedicatedip.
 *
 * @param int $ipAddressId
 * @param bool $syncToWhmcs Clear tblhosting.dedicatedip for the service
 * @return bool Success
 */
function ipmanager_unassign_ip(int $ipAddressId, bool $syncToWhmcs = true): bool {
    try {
        $assign = Capsule::table(ipmanager_table("assignments"))->where("ip_address_id", $ipAddressId)->first();
        if (!$assign) {
            return false;
        }
        $serviceId = (int) $assign->service_id;
        Capsule::table(ipmanager_table("assignments"))->where("ip_address_id", $ipAddressId)->delete();
        Capsule::table(ipmanager_table("ip_addresses"))->where("id", $ipAddressId)->update([
            "status"     => "free",
            "updated_at" => date("Y-m-d H:i:s"),
        ]);
        if ($syncToWhmcs) {
            ipmanager_sync_dedicatedip_to_whmcs($serviceId, "");
        }
        ipmanager_netbox_push_unassign($ipAddressId);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Push assigned IP to NetBox (create or update to active).
 *
 * @param int $ipAddressId
 * @param int $serviceId
 */
function ipmanager_netbox_push_assign(int $ipAddressId, int $serviceId): void {
    try {
        if (!class_exists("IpManagerNetBoxPush")) {
            require_once __DIR__ . "/ipam/NetBoxPush.php";
        }
        IpManagerNetBoxPush::pushAssign($ipAddressId, $serviceId);
    } catch (Exception $e) {
        // silent
    }
}

/**
 * Push unassigned IP to NetBox (set status deprecated).
 *
 * @param int $ipAddressId
 */
function ipmanager_netbox_push_unassign(int $ipAddressId): void {
    try {
        if (!class_exists("IpManagerNetBoxPush")) {
            require_once __DIR__ . "/ipam/NetBoxPush.php";
        }
        IpManagerNetBoxPush::pushUnassign($ipAddressId);
    } catch (Exception $e) {
        // silent
    }
}

/**
 * Run 3rd party integration to add IP on server (cPanel, DirectAdmin, etc.) when enabled.
 *
 * @param int    $serviceId tblhosting.id
 * @param string $ip        IP address
 */
function ipmanager_run_integration_add_ip(int $serviceId, string $ip): void {
    try {
        $service = Capsule::table("tblhosting")->where("id", $serviceId)->first();
        if (!$service || (int) $service->server === 0) {
            return;
        }
        $server = Capsule::table("tblservers")->where("id", $service->server)->first();
        if (!$server) {
            return;
        }
        $type = strtolower(trim($server->type ?? ""));
        $enabled = Capsule::table(ipmanager_table("integration_config"))->where("integration", $type)->where("enabled", 1)->first();
        if (!$enabled) {
            $baseType = explode("_", $type)[0];
            $enabled = Capsule::table(ipmanager_table("integration_config"))->where("integration", $baseType)->where("enabled", 1)->first();
        }
        if (!$enabled) {
            return;
        }
        $serverParams = [
            "hostname"   => $server->hostname,
            "username"   => $server->username,
            "password"   => $server->password,
            "accesshash" => $server->accesshash ?? "",
            "port"       => $server->port ?? "",
        ];
        $map = [
            "cpanel" => ["CpanelIntegration", "IpManagerCpanelIntegration"],
            "cpanel_extended" => ["CpanelIntegration", "IpManagerCpanelIntegration"],
            "directadmin" => ["DirectAdminIntegration", "IpManagerDirectAdminIntegration"],
            "directadmin_extended" => ["DirectAdminIntegration", "IpManagerDirectAdminIntegration"],
            "plesk" => ["PleskIntegration", "IpManagerPleskIntegration"],
            "plesk_extended" => ["PleskIntegration", "IpManagerPleskIntegration"],
            "proxmox" => ["ProxmoxIntegration", "IpManagerProxmoxIntegration"],
            "proxmox_cloud" => ["ProxmoxIntegration", "IpManagerProxmoxIntegration"],
            "solusvm" => ["SolusVmIntegration", "IpManagerSolusVmIntegration"],
            "solusvm_extended" => ["SolusVmIntegration", "IpManagerSolusVmIntegration"],
        ];
        if (isset($map[$type])) {
            [$file, $class] = $map[$type];
            $path = __DIR__ . "/integrations/" . $file . ".php";
            if (is_file($path)) {
                require_once __DIR__ . "/integrations/BaseIntegration.php";
                require_once $path;
                if (class_exists($class)) {
                    $result = $class::addIpToAccount($serverParams, $service, $ip);
                    if (!empty($result["message"]) && function_exists("ipmanager_log")) {
                        ipmanager_log("integration_add_ip", $type . " " . $ip . ": " . $result["message"], null, $service->userid ?? null);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // silent
    }
}

/**
 * Set or clear dedicated IP in WHMCS for a service (tblhosting.dedicatedip).
 *
 * @param int    $serviceId tblhosting.id
 * @param string $ip        IP address or empty to clear
 */
function ipmanager_sync_dedicatedip_to_whmcs(int $serviceId, string $ip): void {
    try {
        Capsule::table("tblhosting")->where("id", $serviceId)->update(["dedicatedip" => $ip]);
    } catch (Exception $e) {
        // ignore
    }
}

/**
 * Find a subnet that contains the given IP (by range).
 *
 * @param string $ip
 * @return object|null Subnet row or null
 */
function ipmanager_find_subnet_containing_ip(string $ip): ?object {
    if (!ipmanager_is_valid_ip($ip)) {
        return null;
    }
    $version = strpos($ip, ":") !== false ? 6 : 4;
    $subnets = Capsule::table(ipmanager_table("subnets"))
        ->where("version", $version)
        ->whereNotNull("start_ip")
        ->whereNotNull("end_ip")
        ->get();
    foreach ($subnets as $s) {
        if ($version === 4) {
            $long = ip2long($ip);
            $start = ip2long($s->start_ip);
            $end = ip2long($s->end_ip);
            if ($long !== false && $start !== false && $end !== false && $long >= $start && $long <= $end) {
                return $s;
            }
        } else {
            if (strcmp($ip, $s->start_ip) >= 0 && strcmp($ip, $s->end_ip) <= 0) {
                return $s;
            }
        }
    }
    return null;
}

/**
 * Get a free IP from pool or subnet (first available by id).
 *
 * @param int|null $poolId
 * @param int|null $subnetId
 * @return object|null Row with id, ip
 */
function ipmanager_get_free_ip_from_pool_or_subnet(?int $poolId, ?int $subnetId): ?object {
    $q = Capsule::table(ipmanager_table("ip_addresses"))->where("status", "free");
    if ($poolId !== null && $poolId > 0) {
        $q->where("pool_id", $poolId);
    } else {
        $q->whereNull("pool_id");
    }
    if ($subnetId !== null && $subnetId > 0) {
        $q->where("subnet_id", $subnetId);
    }
    return $q->orderBy("id")->first();
}

/**
 * Get table name with WHMCS prefix.
 *
 * @param string $name e.g. "subnets"
 * @return string
 */
function ipmanager_table(string $name): string {
    return "mod_ipmanager_" . $name;
}

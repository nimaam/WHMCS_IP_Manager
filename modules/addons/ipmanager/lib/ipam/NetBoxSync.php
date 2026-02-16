<?php

/**
 * Sync from NetBox: pull prefixes → subnets, IP addresses → ip_addresses.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . "/NetBoxClient.php";

class IpManagerNetBoxSync {

    private const IPAM_SOURCE = "netbox";

    /**
     * Run pull sync from NetBox. Returns summary and errors.
     *
     * @param array{url: string, token: string, site_id?: int, tenant_id?: int} $config
     * @return array{subnets_created: int, subnets_updated: int, ips_created: int, ips_updated: int, errors: list<string>}
     */
    public static function pull(array $config): array {
        $url = rtrim($config["url"] ?? "", "/");
        $token = $config["token"] ?? "";
        if ($url === "" || $token === "") {
            return [
                "subnets_created" => 0,
                "subnets_updated" => 0,
                "ips_created"     => 0,
                "ips_updated"     => 0,
                "errors"          => ["NetBox URL and token are required."],
            ];
        }

        $client = new IpManagerNetBoxClient($url, $token);
        if (!$client->testConnection()) {
            return [
                "subnets_created" => 0,
                "subnets_updated" => 0,
                "ips_created"     => 0,
                "ips_updated"     => 0,
                "errors"          => ["Could not connect to NetBox. Check URL and API token."],
            ];
        }

        $filters = [];
        if (!empty($config["site_id"])) {
            $filters["site_id"] = (int) $config["site_id"];
        }
        if (!empty($config["tenant_id"])) {
            $filters["tenant_id"] = (int) $config["tenant_id"];
        }

        $prefixes = $client->getPrefixes($filters);
        $subnetsCreated = 0;
        $subnetsUpdated = 0;
        $ipsCreated = 0;
        $ipsUpdated = 0;
        $errors = [];

        $now = date("Y-m-d H:i:s");
        $prefixIdToOurSubnetId = [];

        foreach ($prefixes as $prefix) {
            $netboxId = (int) ($prefix["id"] ?? 0);
            $cidr = $prefix["prefix"] ?? "";
            if ($cidr === "" || $netboxId === 0) {
                continue;
            }
            $description = $prefix["description"] ?? "";
            $name = $description !== "" ? $description : $cidr;

            $range = ipmanager_cidr_to_range($cidr);
            $startIp = $range ? $range[0] : null;
            $endIp = $range ? $range[1] : null;
            $version = strpos($cidr, ":") !== false ? 6 : 4;

            $existing = Capsule::table(ipmanager_table("ipam_mapping"))
                ->where("ipam_source", self::IPAM_SOURCE)
                ->where("entity_type", "subnet")
                ->where("ipam_id", (string) $netboxId)
                ->first();

            if ($existing) {
                $ourSubnetId = (int) $existing->entity_id;
                Capsule::table(ipmanager_table("subnets"))->where("id", $ourSubnetId)->update([
                    "name"       => $name,
                    "start_ip"   => $startIp,
                    "end_ip"     => $endIp,
                    "updated_at" => $now,
                ]);
                $subnetsUpdated++;
            } else {
                $ourSubnetId = (int) Capsule::table(ipmanager_table("subnets"))->insertGetId([
                    "parent_id"  => null,
                    "name"       => $name,
                    "cidr"       => $cidr,
                    "start_ip"   => $startIp,
                    "end_ip"     => $endIp,
                    "version"    => $version,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
                Capsule::table(ipmanager_table("ipam_mapping"))->insert([
                    "entity_type"  => "subnet",
                    "entity_id"    => $ourSubnetId,
                    "ipam_source"  => self::IPAM_SOURCE,
                    "ipam_id"     => (string) $netboxId,
                    "created_at"  => $now,
                    "updated_at"  => $now,
                ]);
                $subnetsCreated++;
            }
            $prefixIdToOurSubnetId[$netboxId] = $ourSubnetId;

            $ipAddresses = $client->getIpAddressesForPrefix($netboxId);
            foreach ($ipAddresses as $nbIp) {
                $addr = $nbIp["address"] ?? "";
                if ($addr === "") {
                    continue;
                }
                $ipOnly = explode("/", $addr)[0] ?? $addr;
                if (!ipmanager_is_valid_ip($ipOnly)) {
                    continue;
                }
                $nbStatus = $nbIp["status"] ?? [];
                $statusValue = is_array($nbStatus) ? ($nbStatus["value"] ?? "active") : (string) $nbStatus;
                $ourStatus = self::netboxStatusToOurs($statusValue);

                $nbIpId = (int) ($nbIp["id"] ?? 0);
                $existingIp = Capsule::table(ipmanager_table("ipam_mapping"))
                    ->where("ipam_source", self::IPAM_SOURCE)
                    ->where("entity_type", "ip_address")
                    ->where("ipam_id", (string) $nbIpId)
                    ->first();

                if ($existingIp) {
                    $ourIpId = (int) $existingIp->entity_id;
                    Capsule::table(ipmanager_table("ip_addresses"))->where("id", $ourIpId)->update([
                        "status"     => $ourStatus,
                        "updated_at" => $now,
                    ]);
                    $ipsUpdated++;
                } else {
                    $exists = Capsule::table(ipmanager_table("ip_addresses"))
                        ->where("subnet_id", $ourSubnetId)
                        ->whereNull("pool_id")
                        ->where("ip", $ipOnly)
                        ->first();
                    if ($exists) {
                        Capsule::table(ipmanager_table("ip_addresses"))->where("id", $exists->id)->update([
                            "status"     => $ourStatus,
                            "updated_at" => $now,
                        ]);
                        Capsule::table(ipmanager_table("ipam_mapping"))->insert([
                            "entity_type"  => "ip_address",
                            "entity_id"   => (int) $exists->id,
                            "ipam_source"  => self::IPAM_SOURCE,
                            "ipam_id"     => (string) $nbIpId,
                            "created_at"  => $now,
                            "updated_at"  => $now,
                        ]);
                        $ipsUpdated++;
                    } else {
                        try {
                            $ourIpId = (int) Capsule::table(ipmanager_table("ip_addresses"))->insertGetId([
                                "subnet_id"  => $ourSubnetId,
                                "pool_id"    => null,
                                "ip"         => $ipOnly,
                                "version"    => $version,
                                "status"     => $ourStatus,
                                "created_at" => $now,
                                "updated_at" => $now,
                            ]);
                            Capsule::table(ipmanager_table("ipam_mapping"))->insert([
                                "entity_type"  => "ip_address",
                                "entity_id"   => $ourIpId,
                                "ipam_source"  => self::IPAM_SOURCE,
                                "ipam_id"     => (string) $nbIpId,
                                "created_at"  => $now,
                                "updated_at"  => $now,
                            ]);
                            $ipsCreated++;
                        } catch (Exception $e) {
                            $errors[] = "IP " . $ipOnly . ": " . $e->getMessage();
                        }
                    }
                }
            }
        }

        return [
            "subnets_created" => $subnetsCreated,
            "subnets_updated" => $subnetsUpdated,
            "ips_created"     => $ipsCreated,
            "ips_updated"     => $ipsUpdated,
            "errors"          => $errors,
        ];
    }

    private static function netboxStatusToOurs(string $netboxStatus): string {
        $map = [
            "active"    => "assigned",
            "reserved"  => "reserved",
            "deprecated" => "free",
            "dhcp"      => "assigned",
        ];
        return $map[strtolower($netboxStatus)] ?? "free";
    }

    /**
     * Get NetBox prefix ID for our subnet (for push).
     *
     * @param int $ourSubnetId
     * @return int|null
     */
    public static function getNetBoxPrefixId(int $ourSubnetId): ?int {
        $row = Capsule::table(ipmanager_table("ipam_mapping"))
            ->where("entity_type", "subnet")
            ->where("entity_id", $ourSubnetId)
            ->where("ipam_source", self::IPAM_SOURCE)
            ->first();
        return $row ? (int) $row->ipam_id : null;
    }

    /**
     * Get NetBox IP address ID for our ip_address (for push).
     *
     * @param int $ourIpAddressId
     * @return int|null
     */
    public static function getNetBoxIpId(int $ourIpAddressId): ?int {
        $row = Capsule::table(ipmanager_table("ipam_mapping"))
            ->where("entity_type", "ip_address")
            ->where("entity_id", $ourIpAddressId)
            ->where("ipam_source", self::IPAM_SOURCE)
            ->first();
        return $row ? (int) $row->ipam_id : null;
    }

    /**
     * Store mapping from our ip_address to NetBox IP id (after create in NetBox).
     *
     * @param int $ourIpAddressId
     * @param int $netboxIpId
     */
    public static function setNetBoxIpId(int $ourIpAddressId, int $netboxIpId): void {
        $now = date("Y-m-d H:i:s");
        $exists = Capsule::table(ipmanager_table("ipam_mapping"))
            ->where("entity_type", "ip_address")
            ->where("entity_id", $ourIpAddressId)
            ->where("ipam_source", self::IPAM_SOURCE)
            ->first();
        if ($exists) {
            Capsule::table(ipmanager_table("ipam_mapping"))->where("id", $exists->id)->update([
                "ipam_id" => (string) $netboxIpId,
                "updated_at" => $now,
            ]);
        } else {
            Capsule::table(ipmanager_table("ipam_mapping"))->insert([
                "entity_type"  => "ip_address",
                "entity_id"    => $ourIpAddressId,
                "ipam_source"  => self::IPAM_SOURCE,
                "ipam_id"      => (string) $netboxIpId,
                "created_at"   => $now,
                "updated_at"   => $now,
            ]);
        }
    }
}

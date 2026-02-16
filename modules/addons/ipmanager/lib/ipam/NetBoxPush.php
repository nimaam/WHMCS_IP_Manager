<?php

/**
 * Push assign/unassign to NetBox (update IP status and description).
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . "/NetBoxClient.php";
require_once __DIR__ . "/NetBoxSync.php";

class IpManagerNetBoxPush {

    private const IPAM_NETBOX_KEY = "netbox_ipam";

    /**
     * Called after assigning an IP: create or update IP in NetBox (status active).
     *
     * @param int $ipAddressId Our ip_address id
     * @param int $serviceId   WHMCS service id
     */
    public static function pushAssign(int $ipAddressId, int $serviceId): void {
        $config = self::getConfig();
        if ($config === null || empty($config["push_on_assign"])) {
            return;
        }
        $ipRow = Capsule::table(ipmanager_table("ip_addresses"))->where("id", $ipAddressId)->first();
        if (!$ipRow) {
            return;
        }
        $client = new IpManagerNetBoxClient($config["url"], $config["token"]);
        $netboxIpId = IpManagerNetBoxSync::getNetBoxIpId($ipAddressId);
        $description = "WHMCS Service #" . $serviceId;
        if ($netboxIpId !== null) {
            $client->updateIpAddress($netboxIpId, ["status" => "active", "description" => $description]);
        } else {
            $subnetId = (int) $ipRow->subnet_id;
            $prefixId = IpManagerNetBoxSync::getNetBoxPrefixId($subnetId);
            $version = (int) $ipRow->version;
            $addr = $version === 6 ? $ipRow->ip . "/128" : $ipRow->ip . "/32";
            $created = $client->createIpAddress($addr, "active", $description, $prefixId);
            if ($created !== null && !empty($created["id"])) {
                IpManagerNetBoxSync::setNetBoxIpId($ipAddressId, (int) $created["id"]);
            }
        }
    }

    /**
     * Called after unassigning: set IP status in NetBox to deprecated (free).
     *
     * @param int $ipAddressId Our ip_address id
     */
    public static function pushUnassign(int $ipAddressId): void {
        $config = self::getConfig();
        if ($config === null || empty($config["push_on_assign"])) {
            return;
        }
        $netboxIpId = IpManagerNetBoxSync::getNetBoxIpId($ipAddressId);
        if ($netboxIpId === null) {
            return;
        }
        $client = new IpManagerNetBoxClient($config["url"], $config["token"]);
        $client->updateIpAddress($netboxIpId, ["status" => "deprecated"]);
    }

    /**
     * @return array{url: string, token: string, push_on_assign: bool}|null
     */
    private static function getConfig(): ?array {
        $row = Capsule::table(ipmanager_table("integration_config"))
            ->where("integration", self::IPAM_NETBOX_KEY)
            ->where("enabled", 1)
            ->first();
        if (!$row || empty($row->config)) {
            return null;
        }
        $config = (array) json_decode($row->config, true);
        if (empty($config["url"]) || empty($config["token"])) {
            return null;
        }
        return $config;
    }
}

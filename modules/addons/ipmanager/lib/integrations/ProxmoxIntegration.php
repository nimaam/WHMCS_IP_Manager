<?php

/**
 * Proxmox VE VPS & Cloud / Proxmox VE Cloud VPS integration.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . "/BaseIntegration.php";

class IpManagerProxmoxIntegration extends IpManagerBaseIntegration {

    public static function getName(): string {
        return "Proxmox VE VPS & Cloud";
    }

    /**
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function addIpToAccount(array $serverParams, $service, string $ip): array {
        $host = $serverParams["hostname"] ?? "";
        $user = $serverParams["username"] ?? "";
        $pass = $serverParams["password"] ?? "";
        $port = $serverParams["port"] ?? 8006;
        if ($host === "" || $user === "" || $pass === "") {
            return ["success" => false, "message" => "Missing Proxmox credentials"];
        }
        $vmid = $service->username ?? $service->dedicatedip ?? "";
        if ($vmid === "") {
            return ["success" => false, "message" => "Missing VM ID or identifier"];
        }
        return ["success" => false, "message" => "Proxmox IP assignment: configure network via Proxmox API (stub)"];
    }

    /**
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function removeIpFromAccount(array $serverParams, $service, string $ip): array {
        return ["success" => false, "message" => "Proxmox remove IP (stub)"];
    }
}

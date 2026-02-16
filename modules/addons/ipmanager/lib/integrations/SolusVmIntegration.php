<?php

/**
 * SolusVM Extended VPS integration.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . "/BaseIntegration.php";

class IpManagerSolusVmIntegration extends IpManagerBaseIntegration {

    public static function getName(): string {
        return "SolusVM Extended VPS";
    }

    /**
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function addIpToAccount(array $serverParams, $service, string $ip): array {
        $host = $serverParams["hostname"] ?? "";
        $key  = $serverParams["accesshash"] ?? "";
        $pass = $serverParams["password"] ?? "";
        if ($host === "" || ($key === "" && $pass === "")) {
            return ["success" => false, "message" => "Missing SolusVM credentials"];
        }
        $vmid = $service->username ?? $service->dedicatedip ?? "";
        if ($vmid === "") {
            return ["success" => false, "message" => "Missing VPS ID"];
        }
        return ["success" => false, "message" => "SolusVM add IP: use SolusVM API (stub)"];
    }

    /**
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function removeIpFromAccount(array $serverParams, $service, string $ip): array {
        return ["success" => false, "message" => "SolusVM remove IP (stub)"];
    }
}

<?php

/**
 * Plesk / Plesk Extended integration - add/remove IP on subscription.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . "/BaseIntegration.php";

class IpManagerPleskIntegration extends IpManagerBaseIntegration {

    public static function getName(): string {
        return "Plesk / Plesk Extended";
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
        $port = $serverParams["port"] ?? 8443;
        if ($host === "" || $user === "" || $pass === "") {
            return ["success" => false, "message" => "Missing Plesk credentials"];
        }
        $subscriptionId = $service->domain ?? $service->username ?? "";
        if ($subscriptionId === "") {
            return ["success" => false, "message" => "Missing subscription/domain"];
        }
        $xml = '<?xml version="1.0"?><packet><ip><add><ip_address>' . htmlspecialchars($ip) . '</ip_address></add></ip></packet>';
        $url = "https://" . $host . ":" . $port . "/enterprise/control/agent.php";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $user . ":" . $pass);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: text/xml", "HTTP_PRETTY_PRINT: true"]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response !== false && $code >= 200 && $code < 300) {
            return ["success" => true];
        }
        return ["success" => false, "message" => $response !== false ? substr($response, 0, 200) : "API call failed"];
    }

    /**
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function removeIpFromAccount(array $serverParams, $service, string $ip): array {
        return ["success" => false, "message" => "Plesk remove IP not implemented in stub"];
    }
}

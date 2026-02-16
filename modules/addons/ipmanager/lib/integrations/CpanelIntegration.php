<?php

/**
 * cPanel / cPanel Extended integration - add/remove IP on account.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . "/BaseIntegration.php";

class IpManagerCpanelIntegration extends IpManagerBaseIntegration {

    public static function getName(): string {
        return "cPanel / cPanel Extended";
    }

    /**
     * Use WHM API (API2 or API1) to add IP to reseller/user.
     *
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function addIpToAccount(array $serverParams, $service, string $ip): array {
        $host = $serverParams["hostname"] ?? "";
        $user = $serverParams["username"] ?? "";
        $pass = $serverParams["password"] ?? "";
        $accessHash = $serverParams["accesshash"] ?? "";
        $port = $serverParams["port"] ?? 2087;
        $account = $service->username ?? "";
        if ($host === "" || $account === "") {
            return ["success" => false, "message" => "Missing host or account username"];
        }
        if ($pass === "" && $accessHash === "") {
            return ["success" => false, "message" => "Missing WHM credentials"];
        }

        $query = "ip=" . urlencode($ip) . "&user=" . urlencode($account);
        $result = self::whmApiCall($host, $port, $user, $pass, $accessHash, "addip", $query);
        if ($result === null) {
            return ["success" => false, "message" => "API call failed"];
        }
        if (isset($result["result"][0]["status"]) && $result["result"][0]["status"] == 1) {
            return ["success" => true];
        }
        $msg = $result["result"][0]["statusmsg"] ?? "Unknown error";
        return ["success" => false, "message" => $msg];
    }

    /**
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function removeIpFromAccount(array $serverParams, $service, string $ip): array {
        $host = $serverParams["hostname"] ?? "";
        $user = $serverParams["username"] ?? "";
        $pass = $serverParams["password"] ?? "";
        $accessHash = $serverParams["accesshash"] ?? "";
        $port = $serverParams["port"] ?? 2087;
        $account = $service->username ?? "";
        if ($host === "" || $account === "") {
            return ["success" => false, "message" => "Missing host or account username"];
        }

        $query = "ip=" . urlencode($ip) . "&user=" . urlencode($account);
        $result = self::whmApiCall($host, $port, $user, $pass, $accessHash, "delip", $query);
        if ($result === null) {
            return ["success" => false, "message" => "API call failed"];
        }
        if (isset($result["result"][0]["status"]) && $result["result"][0]["status"] == 1) {
            return ["success" => true];
        }
        return ["success" => false, "message" => $result["result"][0]["statusmsg"] ?? "Unknown error"];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function whmApiCall(string $host, $port, string $user, string $pass, string $accessHash, string $function, string $query): ?array {
        $protocol = ($port == 2087 || $port == 2083) ? "https" : "http";
        $url = $protocol . "://" . $host . ":" . $port . "/json-api/" . $function . "?" . $query;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($accessHash !== "") {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: WHM $user:" . preg_replace("/\s/", "", $accessHash)]);
        } else {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $user . ":" . $pass);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code >= 400) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}

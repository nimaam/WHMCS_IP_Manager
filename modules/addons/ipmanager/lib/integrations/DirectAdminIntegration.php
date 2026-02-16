<?php

/**
 * DirectAdmin / DirectAdmin Extended integration - add/remove IP.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . "/BaseIntegration.php";

class IpManagerDirectAdminIntegration extends IpManagerBaseIntegration {

    public static function getName(): string {
        return "DirectAdmin / DirectAdmin Extended";
    }

    /**
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function addIpToAccount(array $serverParams, $service, string $ip): array {
        $account = $service->username ?? "";
        if ($account === "") {
            return ["success" => false, "message" => "Missing account username"];
        }
        $result = self::daApi($serverParams, "CMD_API_ADD_IP", ["ip" => $ip, "reseller" => "yes"]);
        if ($result !== null && strpos($result, "error=0") !== false) {
            return ["success" => true];
        }
        return ["success" => false, "message" => $result ?? "API call failed"];
    }

    /**
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    public static function removeIpFromAccount(array $serverParams, $service, string $ip): array {
        $result = self::daApi($serverParams, "CMD_API_REMOVE_IP", ["ip" => $ip]);
        if ($result !== null && strpos($result, "error=0") !== false) {
            return ["success" => true];
        }
        return ["success" => false, "message" => $result ?? "API call failed"];
    }

    /**
     * @param array<string, mixed> $params
     * @return string|null
     */
    private static function daApi(array $params, string $cmd, array $post = []): ?string {
        $host = $params["hostname"] ?? "";
        $user = $params["username"] ?? "";
        $pass = $params["password"] ?? "";
        $port = $params["port"] ?? 2222;
        if ($host === "" || $user === "" || $pass === "") {
            return null;
        }
        $url = "https://" . $host . ":" . $port . "/" . $cmd;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $user . ":" . $pass);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response !== false ? $response : null;
    }
}

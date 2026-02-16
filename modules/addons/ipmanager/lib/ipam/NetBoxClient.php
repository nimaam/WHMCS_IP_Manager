<?php

/**
 * NetBox API client for IPAM sync (prefixes and IP addresses).
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class IpManagerNetBoxClient {

    private string $baseUrl;

    private string $token;

    private int $timeout;

    public function __construct(string $baseUrl, string $token, int $timeout = 30) {
        $this->baseUrl = rtrim($baseUrl, "/");
        $this->token = $token;
        $this->timeout = $timeout;
    }

    /**
     * GET request to NetBox API.
     *
     * @param string $endpoint e.g. "/api/ipam/prefixes/"
     * @param array<string, string> $query
     * @return array<string, mixed>|null
     */
    public function get(string $endpoint, array $query = []): ?array {
        $url = $this->baseUrl . $endpoint;
        if ($query !== []) {
            $url .= "?" . http_build_query($query);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Token " . $this->token,
            "Accept: application/json",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code >= 400) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * POST request.
     *
     * @param string $endpoint
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public function post(string $endpoint, array $body): ?array {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Token " . $this->token,
            "Accept: application/json",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * PATCH request.
     *
     * @param string $endpoint e.g. "/api/ipam/ip-addresses/123/"
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    public function patch(string $endpoint, array $body): ?array {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Token " . $this->token,
            "Accept: application/json",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Fetch all prefixes (paginated).
     *
     * @param array{site_id?: int, tenant_id?: int, status?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function getPrefixes(array $filters = []): array {
        $query = ["limit" => 250];
        if (isset($filters["site_id"])) {
            $query["site_id"] = $filters["site_id"];
        }
        if (isset($filters["tenant_id"])) {
            $query["tenant_id"] = $filters["tenant_id"];
        }
        if (isset($filters["status"])) {
            $query["status"] = $filters["status"];
        }
        $all = [];
        $nextUrl = $this->baseUrl . "/api/ipam/prefixes/";
        $currentQuery = $query;
        while ($nextUrl !== null) {
            $path = parse_url($nextUrl, PHP_URL_PATH);
            $path = $path !== null && $path !== "" ? $path : "/api/ipam/prefixes/";
            $path = preg_replace('#^' . preg_quote($this->baseUrl, "#") . '#', "", $path);
            if ($path === "") {
                $path = "/api/ipam/prefixes/";
            }
            $data = $this->get($path, $currentQuery);
            if ($data === null) {
                break;
            }
            $results = $data["results"] ?? [];
            foreach ($results as $r) {
                $all[] = $r;
            }
            $nextUrl = $data["next"] ?? null;
            if ($nextUrl !== null) {
                $currentQuery = [];
                $q = parse_url($nextUrl, PHP_URL_QUERY);
                if ($q !== null && $q !== "") {
                    parse_str($q, $currentQuery);
                }
            }
        }
        return $all;
    }

    /**
     * Fetch IP addresses for a prefix (by prefix ID or parent prefix).
     *
     * @param int $prefixId NetBox prefix ID
     * @return list<array<string, mixed>>
     */
    public function getIpAddressesForPrefix(int $prefixId): array {
        $all = [];
        $nextUrl = $this->baseUrl . "/api/ipam/ip-addresses/";
        $currentQuery = ["prefix_id" => $prefixId, "limit" => 250];
        do {
            $path = preg_replace('#^' . preg_quote($this->baseUrl, "#") . '#', "", parse_url($nextUrl, PHP_URL_PATH) ?: "/api/ipam/ip-addresses/");
            if ($path === "") {
                $path = "/api/ipam/ip-addresses/";
            }
            $data = $this->get($path, $currentQuery);
            if ($data === null) {
                break;
            }
            $results = $data["results"] ?? [];
            foreach ($results as $r) {
                $all[] = $r;
            }
            $nextUrl = $data["next"] ?? null;
            if ($nextUrl !== null) {
                $q = parse_url($nextUrl, PHP_URL_QUERY);
                $currentQuery = [];
                if ($q !== null && $q !== "") {
                    parse_str($q, $currentQuery);
                }
            }
        } while ($nextUrl !== null);
        return $all;
    }

    /**
     * Create an IP address in NetBox.
     *
     * @param string $address e.g. "10.0.0.1/32"
     * @param string $status  e.g. "active", "reserved"
     * @param string $description
     * @param int|null $prefixId Optional prefix to associate
     * @return array<string, mixed>|null Created object with id
     */
    public function createIpAddress(string $address, string $status = "active", string $description = "", ?int $prefixId = null): ?array {
        $body = ["address" => $address, "status" => $status];
        if ($description !== "") {
            $body["description"] = $description;
        }
        if ($prefixId !== null) {
            $body["prefix"] = $prefixId;
        }
        return $this->post("/api/ipam/ip-addresses/", $body);
    }

    /**
     * Update an IP address in NetBox.
     *
     * @param int $netboxIpId
     * @param array<string, mixed> $body e.g. ["status" => "deprecated"]
     * @return array<string, mixed>|null
     */
    public function updateIpAddress(int $netboxIpId, array $body): ?array {
        return $this->patch("/api/ipam/ip-addresses/" . $netboxIpId . "/", $body);
    }

    /**
     * Test connection (e.g. GET /api/status/ or /api/ipam/prefixes/?limit=1).
     *
     * @return bool
     */
    public function testConnection(): bool {
        $data = $this->get("/api/ipam/prefixes/", ["limit" => 1]);
        return $data !== null;
    }
}

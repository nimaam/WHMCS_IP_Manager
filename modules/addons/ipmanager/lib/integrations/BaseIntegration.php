<?php

/**
 * Base for 3rd party integration (cPanel, DirectAdmin, Plesk, etc.).
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

abstract class IpManagerBaseIntegration {

    /**
     * Human-readable name.
     */
    abstract public static function getName(): string;

    /**
     * Add IP to account on the server. Return true on success.
     *
     * @param array<string, mixed> $serverParams From tblservers (hostname, username, password, etc.)
     * @param object                $service     tblhosting row
     * @param string                $ip          IP address to add
     * @return array{success: bool, message?: string}
     */
    abstract public static function addIpToAccount(array $serverParams, $service, string $ip): array;

    /**
     * Remove IP from account. Return true on success.
     *
     * @param array<string, mixed> $serverParams
     * @param object                $service
     * @param string                $ip
     * @return array{success: bool, message?: string}
     */
    abstract public static function removeIpFromAccount(array $serverParams, $service, string $ip): array;
}

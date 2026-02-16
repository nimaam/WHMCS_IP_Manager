<?php

/**
 * IP Cleaner: detect assigned IPs that are no longer in use and notify or mark free.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

class IpManagerIpCleaner {

    public const BEHAVIOR_NOTIFY_ONLY = "notify_only";
    public const BEHAVIOR_MARK_FREE   = "mark_free";

    /**
     * Run the cleaner: find assignments where the service's dedicatedip no longer matches.
     *
     * @param string $behavior notify_only or mark_free
     * @return array{checked: int, orphaned: int, notified: int, marked_free: int, details: list<array{ip: string, service_id: int, client_id: int}>}
     */
    public static function run(string $behavior = self::BEHAVIOR_NOTIFY_ONLY): array {
        $assignments = Capsule::table(ipmanager_table("assignments") . " as a")
            ->join(ipmanager_table("ip_addresses") . " as ip", "ip.id", "=", "a.ip_address_id")
            ->where("a.service_id", ">", 0)
            ->select("a.id as assignment_id", "a.ip_address_id", "a.service_id", "a.client_id", "ip.ip")
            ->get();

        $checked = $assignments->count();
        $orphaned = 0;
        $markedFree = 0;
        $details = [];

        foreach ($assignments as $row) {
            $service = Capsule::table("tblhosting")->where("id", $row->service_id)->first();
            $currentDedicatedIp = $service && isset($service->dedicatedip) ? trim((string) $service->dedicatedip) : "";
            $assignedIp = trim((string) $row->ip);

            if ($currentDedicatedIp !== $assignedIp) {
                $orphaned++;
                $details[] = [
                    "ip"         => $assignedIp,
                    "service_id" => (int) $row->service_id,
                    "client_id"  => (int) $row->client_id,
                ];

                if ($behavior === self::BEHAVIOR_MARK_FREE) {
                    try {
                        Capsule::table(ipmanager_table("assignments"))->where("id", $row->assignment_id)->delete();
                        Capsule::table(ipmanager_table("ip_addresses"))->where("id", $row->ip_address_id)->update([
                            "status"     => "free",
                            "updated_at" => date("Y-m-d H:i:s"),
                        ]);
                        $markedFree++;
                    } catch (Exception $e) {
                        // skip
                    }
                }
            }
        }

        $notified = 0;
        if ($orphaned > 0 && ($behavior === self::BEHAVIOR_NOTIFY_ONLY || $markedFree > 0)) {
            if (self::sendNotification($orphaned, $details, $behavior, $markedFree)) {
                $notified = 1;
            }
        }

        return [
            "checked"     => $checked,
            "orphaned"    => $orphaned,
            "notified"    => $notified,
            "marked_free" => $markedFree,
            "details"     => $details,
        ];
    }

    private static function sendNotification(int $orphaned, array $details, string $behavior, int $markedFree): bool {
        try {
            $admin = Capsule::table("tbladmins")->orderBy("id")->first();
            $to = $admin && !empty($admin->email) ? $admin->email : "";
            if ($to === "") {
                return false;
            }
            $subject = "IP Manager: IP Cleaner report – " . $orphaned . " orphaned IP(s)";
            $body = "The IP Cleaner has run.\n\n";
            $body .= "Assigned IPs checked that are no longer in use (dedicatedip mismatch): " . $orphaned . "\n";
            $body .= "Behavior: " . ($behavior === self::BEHAVIOR_MARK_FREE ? "Mark Free" : "Notify Only") . "\n";
            if ($markedFree > 0) {
                $body .= "IPs marked as free: " . $markedFree . "\n";
            }
            $body .= "\nDetails:\n";
            foreach (array_slice($details, 0, 50) as $d) {
                $body .= "  IP " . $d["ip"] . " – Service #" . $d["service_id"] . ", Client #" . $d["client_id"] . "\n";
            }
            if (count($details) > 50) {
                $body .= "  ... and " . (count($details) - 50) . " more.\n";
            }
            $headers = "From: " . $to . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            return (bool) @mail($to, $subject, $body, $headers);
        } catch (Exception $e) {
            return false;
        }
    }
}

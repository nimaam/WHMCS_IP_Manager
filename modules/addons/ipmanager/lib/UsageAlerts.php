<?php

/**
 * Usage alerts: check subnet/pool usage and send email when over threshold.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

class IpManagerUsageAlerts {

    /**
     * Run usage check and send alerts. Call from cron or admin.
     *
     * @param int $defaultThresholdPercent Default from module config
     * @param int $minHoursBetweenAlerts  Don't resend within this many hours
     * @return array{sent: int, skipped: int}
     */
    public static function run(int $defaultThresholdPercent = 80, int $minHoursBetweenAlerts = 24): array {
        $sent = 0;
        $skipped = 0;

        $subnets = Capsule::table(ipmanager_table("subnets"))->get();
        foreach ($subnets as $subnet) {
            $total = Capsule::table(ipmanager_table("ip_addresses"))
                ->where("subnet_id", $subnet->id)
                ->whereNull("pool_id")
                ->count();
            if ($total === 0) {
                continue;
            }
            $assigned = Capsule::table(ipmanager_table("ip_addresses"))
                ->where("subnet_id", $subnet->id)
                ->whereNull("pool_id")
                ->where("status", "assigned")
                ->count();
            $percent = (int) round($assigned / $total * 100);
            $threshold = self::getThresholdForSubnet((int) $subnet->id, null, $defaultThresholdPercent);
            $alert = Capsule::table(ipmanager_table("usage_alerts"))
                ->where("subnet_id", $subnet->id)
                ->whereNull("pool_id")
                ->first();
            if ($percent >= $threshold && self::shouldSend($alert, $minHoursBetweenAlerts)) {
                if (self::sendAlert("subnet", $subnet->name, $subnet->cidr, $percent, $threshold, $assigned, $total)) {
                    $now = date("Y-m-d H:i:s");
                    if ($alert) {
                        Capsule::table(ipmanager_table("usage_alerts"))->where("id", $alert->id)->update(["last_sent_at" => $now]);
                    } else {
                        Capsule::table(ipmanager_table("usage_alerts"))->insert([
                            "subnet_id"         => $subnet->id,
                            "pool_id"           => null,
                            "percent_threshold" => $threshold,
                            "last_sent_at"      => $now,
                            "created_at"        => $now,
                            "updated_at"        => $now,
                        ]);
                    }
                    $sent++;
                }
            } else {
                $skipped++;
            }
        }

        $pools = Capsule::table(ipmanager_table("pools"))->get();
        foreach ($pools as $pool) {
            $total = Capsule::table(ipmanager_table("ip_addresses"))->where("pool_id", $pool->id)->count();
            if ($total === 0) {
                continue;
            }
            $assigned = Capsule::table(ipmanager_table("ip_addresses"))
                ->where("pool_id", $pool->id)
                ->where("status", "assigned")
                ->count();
            $percent = (int) round($assigned / $total * 100);
            $threshold = self::getThresholdForSubnet((int) $pool->subnet_id, (int) $pool->id, $defaultThresholdPercent);
            $alert = Capsule::table(ipmanager_table("usage_alerts"))
                ->where("subnet_id", $pool->subnet_id)
                ->where("pool_id", $pool->id)
                ->first();
            if ($percent >= $threshold && self::shouldSend($alert, $minHoursBetweenAlerts)) {
                if (self::sendAlert("pool", $pool->name, $pool->cidr ?? "", $percent, $threshold, $assigned, $total)) {
                    $now = date("Y-m-d H:i:s");
                    if ($alert) {
                        Capsule::table(ipmanager_table("usage_alerts"))->where("id", $alert->id)->update(["last_sent_at" => $now]);
                    } else {
                        Capsule::table(ipmanager_table("usage_alerts"))->insert([
                            "subnet_id"         => $pool->subnet_id,
                            "pool_id"           => $pool->id,
                            "percent_threshold" => $threshold,
                            "last_sent_at"      => $now,
                            "created_at"        => $now,
                            "updated_at"        => $now,
                        ]);
                    }
                    $sent++;
                }
            } else {
                $skipped++;
            }
        }

        return ["sent" => $sent, "skipped" => $skipped];
    }

    private static function getThresholdForSubnet(int $subnetId, ?int $poolId, int $default): int {
        $q = Capsule::table(ipmanager_table("usage_alerts"))->where("subnet_id", $subnetId);
        if ($poolId !== null) {
            $q->where("pool_id", $poolId);
        } else {
            $q->whereNull("pool_id");
        }
        $row = $q->first();
        return $row ? (int) $row->percent_threshold : $default;
    }

    private static function shouldSend(?object $alertRow, int $minHours): bool {
        if ($alertRow === null || $alertRow->last_sent_at === null) {
            return true;
        }
        $last = strtotime($alertRow->last_sent_at);
        return ($last === false) || (time() - $last >= $minHours * 3600);
    }

    private static function sendAlert(string $type, string $name, string $cidr, int $percent, int $threshold, int $assigned, int $total): bool {
        try {
            $to = self::getAdminEmail();
            if ($to === "") {
                return false;
            }
            $subject = "IP Manager: " . ucfirst($type) . " usage alert - " . $name;
            $body = "The " . $type . " \"" . $name . "\" (" . $cidr . ") has reached " . $percent . "% usage (threshold: " . $threshold . "%).\n";
            $body .= "Assigned: " . $assigned . " / Total: " . $total . "\n";
            $headers = "From: " . $to . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            return (bool) @mail($to, $subject, $body, $headers);
        } catch (Exception $e) {
            return false;
        }
    }

    private static function getAdminEmail(): string {
        try {
            $admin = Capsule::table("tbladmins")->orderBy("id")->first();
            return $admin && !empty($admin->email) ? $admin->email : "";
        } catch (Exception $e) {
            return "";
        }
    }
}

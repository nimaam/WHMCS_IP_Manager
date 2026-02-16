<?php

/**
 * IP Manager database schema: create and drop tables.
 *
 * @copyright 2025
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

class IpManagerSchema {

    /**
     * Create all module tables.
     */
    public static function install(): void {
        self::createSubnets();
        self::createPools();
        self::createIpAddresses();
        self::createReservationRules();
        self::createConfigurations();
        self::createConfigurationRelations();
        self::createAssignments();
        self::createCustomFields();
        self::createCustomFieldValues();
        self::createSubnetLocks();
        self::createLogs();
        self::createAcl();
        self::createIntegrationConfig();
        self::createUsageAlerts();
        self::createIpamMapping();
    }

    /**
     * Drop all module tables.
     */
    public static function uninstall(): void {
        $tables = [
            "ipam_mapping",
            "usage_alerts",
            "integration_config",
            "acl",
            "logs",
            "subnet_locks",
            "custom_field_values",
            "custom_fields",
            "assignments",
            "configuration_relations",
            "configurations",
            "reservation_rules",
            "ip_addresses",
            "pools",
            "subnets",
        ];
        foreach ($tables as $table) {
            Capsule::schema()->dropIfExists(ipmanager_table($table));
        }
    }

    private static function createSubnets(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("subnets"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("subnets"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("parent_id")->nullable()->default(null);
            $table->string("name", 255);
            $table->string("cidr", 64)->comment("e.g. 192.168.1.0/24 or 2001:db8::/32");
            $table->string("gateway", 64)->nullable();
            $table->string("start_ip", 64)->nullable();
            $table->string("end_ip", 64)->nullable();
            $table->unsignedTinyInteger("version")->default(4)->comment("4 or 6");
            $table->text("excluded_ips")->nullable()->comment("JSON array of excluded IPs");
            $table->unsignedInteger("sort_order")->default(0);
            $table->timestamps();
            $table->foreign("parent_id")->references("id")->on(ipmanager_table("subnets"))->onDelete("cascade");
            $table->index(["parent_id", "cidr"]);
        });
    }

    private static function createPools(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("pools"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("pools"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("subnet_id");
            $table->string("name", 255);
            $table->string("cidr", 64)->nullable();
            $table->string("start_ip", 64)->nullable();
            $table->string("end_ip", 64)->nullable();
            $table->unsignedTinyInteger("version")->default(4);
            $table->unsignedInteger("sort_order")->default(0);
            $table->timestamps();
            $table->foreign("subnet_id")->references("id")->on(ipmanager_table("subnets"))->onDelete("cascade");
            $table->index("subnet_id");
        });
    }

    private static function createIpAddresses(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("ip_addresses"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("ip_addresses"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("subnet_id");
            $table->unsignedInteger("pool_id")->nullable();
            $table->string("ip", 64);
            $table->unsignedTinyInteger("version")->default(4);
            $table->string("status", 32)->default("assigned")->comment("free, assigned, reserved");
            $table->timestamps();
            $table->unique(["subnet_id", "pool_id", "ip"], "ipmanager_ip_unique");
            $table->foreign("subnet_id")->references("id")->on(ipmanager_table("subnets"))->onDelete("cascade");
            $table->foreign("pool_id")->references("id")->on(ipmanager_table("pools"))->onDelete("set null");
            $table->index(["subnet_id", "status"]);
            $table->index(["pool_id", "status"]);
        });
    }

    private static function createReservationRules(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("reservation_rules"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("reservation_rules"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("subnet_id");
            $table->unsignedInteger("pool_id")->nullable();
            $table->string("rule_type", 32)->comment("network, gateway, broadcast, custom");
            $table->string("ip_or_pattern", 64)->nullable();
            $table->text("description")->nullable();
            $table->timestamps();
            $table->foreign("subnet_id")->references("id")->on(ipmanager_table("subnets"))->onDelete("cascade");
            $table->index("subnet_id");
        });
    }

    private static function createConfigurations(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("configurations"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("configurations"), function ($table) {
            $table->increments("id");
            $table->string("name", 255);
            $table->boolean("omit_dedicated_ip_field")->default(false);
            $table->boolean("use_custom_field_instead_of_assigned")->default(false);
            $table->string("custom_field_name", 128)->nullable();
            $table->unsignedInteger("sort_order")->default(0);
            $table->timestamps();
        });
    }

    private static function createConfigurationRelations(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("configuration_relations"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("configuration_relations"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("configuration_id");
            $table->string("relation_type", 32)->comment("product, addon, configoption, server");
            $table->unsignedInteger("relation_id")->comment("productid, addon id, configid, server id");
            $table->unsignedInteger("pool_id")->nullable();
            $table->unsignedInteger("subnet_id")->nullable();
            $table->timestamps();
            $table->foreign("configuration_id")->references("id")->on(ipmanager_table("configurations"))->onDelete("cascade");
            $table->unique(["configuration_id", "relation_type", "relation_id"], "ipmanager_config_rel_unique");
            $table->index("configuration_id");
        });
    }

    private static function createAssignments(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("assignments"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("assignments"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("ip_address_id");
            $table->unsignedInteger("client_id");
            $table->unsignedInteger("service_id")->comment("tblhosting.id");
            $table->unsignedInteger("addon_id")->nullable();
            $table->unsignedInteger("config_option_id")->nullable();
            $table->string("assigned_type", 32)->default("service")->comment("service, addon, configoption");
            $table->timestamp("assigned_at")->nullable();
            $table->timestamps();
            $table->foreign("ip_address_id")->references("id")->on(ipmanager_table("ip_addresses"))->onDelete("cascade");
            $table->unique("ip_address_id");
            $table->index(["client_id", "service_id"]);
            $table->index("service_id");
        });
    }

    private static function createCustomFields(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("custom_fields"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("custom_fields"), function ($table) {
            $table->increments("id");
            $table->string("entity_type", 32)->comment("subnet, pool, ip_address");
            $table->unsignedInteger("entity_id")->nullable()->comment("subnet_id, pool_id, or 0 for default");
            $table->string("field_name", 128);
            $table->string("field_type", 32)->default("text");
            $table->text("options")->nullable()->comment("JSON for dropdown/radio");
            $table->boolean("required")->default(false);
            $table->unsignedInteger("sort_order")->default(0);
            $table->timestamps();
            $table->index(["entity_type", "entity_id"]);
        });
    }

    private static function createCustomFieldValues(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("custom_field_values"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("custom_field_values"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("custom_field_id");
            $table->string("entity_type", 32);
            $table->unsignedInteger("entity_id");
            $table->text("value")->nullable();
            $table->timestamps();
            $table->foreign("custom_field_id")->references("id")->on(ipmanager_table("custom_fields"))->onDelete("cascade");
            $table->unique(["custom_field_id", "entity_type", "entity_id"], "ipmanager_cfv_unique");
            $table->index(["entity_type", "entity_id"]);
        });
    }

    private static function createSubnetLocks(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("subnet_locks"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("subnet_locks"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("subnet_id");
            $table->unsignedInteger("pool_id")->nullable();
            $table->unsignedInteger("client_id")->nullable();
            $table->unsignedInteger("service_id")->nullable();
            $table->string("lock_type", 32)->comment("client, service");
            $table->timestamps();
            $table->foreign("subnet_id")->references("id")->on(ipmanager_table("subnets"))->onDelete("cascade");
            $table->index(["subnet_id", "lock_type"]);
        });
    }

    private static function createLogs(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("logs"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("logs"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("admin_id")->nullable();
            $table->unsignedInteger("client_id")->nullable();
            $table->string("action", 64);
            $table->text("details")->nullable();
            $table->string("ip_address", 64)->nullable();
            $table->timestamp("created_at");
            $table->index(["action", "created_at"]);
        });
    }

    private static function createAcl(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("acl"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("acl"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("admin_role_id")->nullable();
            $table->unsignedInteger("admin_id")->nullable();
            $table->string("resource", 64)->comment("subnets, pools, configurations, etc.");
            $table->string("permission", 32)->default("view")->comment("view, manage, full");
            $table->timestamps();
            $table->index(["admin_id", "resource"]);
            $table->index(["admin_role_id", "resource"]);
        });
    }

    private static function createIntegrationConfig(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("integration_config"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("integration_config"), function ($table) {
            $table->increments("id");
            $table->string("integration", 64)->comment("cpanel, cpanel_extended, directadmin, plesk, etc.");
            $table->text("config")->nullable()->comment("JSON");
            $table->boolean("enabled")->default(true);
            $table->timestamps();
            $table->unique("integration");
        });
    }

    private static function createUsageAlerts(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("usage_alerts"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("usage_alerts"), function ($table) {
            $table->increments("id");
            $table->unsignedInteger("subnet_id");
            $table->unsignedInteger("pool_id")->nullable();
            $table->unsignedTinyInteger("percent_threshold");
            $table->timestamp("last_sent_at")->nullable();
            $table->timestamps();
            $table->foreign("subnet_id")->references("id")->on(ipmanager_table("subnets"))->onDelete("cascade");
            $table->index("subnet_id");
        });
    }

    private static function createIpamMapping(): void {
        if (Capsule::schema()->hasTable(ipmanager_table("ipam_mapping"))) {
            return;
        }
        Capsule::schema()->create(ipmanager_table("ipam_mapping"), function ($table) {
            $table->increments("id");
            $table->string("entity_type", 32)->comment("subnet, ip_address");
            $table->unsignedInteger("entity_id");
            $table->string("ipam_source", 32)->comment("netbox");
            $table->string("ipam_id", 64)->comment("External ID from IPAM");
            $table->timestamps();
            $table->unique(["entity_type", "entity_id", "ipam_source"], "ipmanager_ipam_mapping_unique");
            $table->index(["ipam_source", "ipam_id"]);
        });
    }
}

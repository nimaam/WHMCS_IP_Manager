{* IP Manager - Client area: View assigned IPs *}
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{$LANG.client_your_ips|default:'Your Assigned IP Addresses'}</h3>
    </div>
    <div class="panel-body">
        {if $assigned_ips}
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>{$LANG.client_product|default:'Product / Service'}</th>
                        <th>{$LANG.client_ip_address|default:'IP Address'}</th>
                        <th>{$LANG.client_subnet|default:'Subnet'}</th>
                        <th>{$LANG.client_actions|default:'Actions'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $assigned_ips as $row}
                        <tr>
                            <td>{$row.product_name}</td>
                            <td><code>{$row.ip}</code></td>
                            <td>{$row.subnet_name}</td>
                            <td>
                                <a href="index.php?m=ipmanager&action=unassign&serviceid={$row.service_id}&ipid={$row.ip_id}" class="btn btn-xs btn-default">
                                    {$LANG.client_unassign_ip|default:'Unassign'}
                                </a>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <p class="text-muted">{$LANG.client_no_ips|default:'You have no assigned IP addresses.'}</p>
        {/if}
        <p>
            <a href="index.php?m=ipmanager&action=order" class="btn btn-primary">{$LANG.client_order_ips|default:'Order Additional IPs'}</a>
        </p>
    </div>
</div>

{* IP Manager - Client area: Unassign IP confirmation *}
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{$LANG.client_unassign_ip|default:'Unassign IP'}</h3>
    </div>
    <div class="panel-body">
        {if $unassign_error}
            <p class="text-danger">{$LANG.client_unassign_error|default:'You cannot unassign this IP or it was already unassigned.'}</p>
            <a href="index.php?m=ipmanager" class="btn btn-default">{$LANG.client_ip_manager|default:'Back to IP Manager'}</a>
        {elseif $confirm}
            <p>{$LANG.client_unassign_confirm|default:'Are you sure you want to unassign this IP address?'}</p>
            <form method="post" action="index.php?m=ipmanager&action=unassign">
                <input type="hidden" name="serviceid" value="{$serviceid}">
                <input type="hidden" name="ipid" value="{$ipid}">
                <button type="submit" name="confirm" value="1" class="btn btn-danger">{$LANG.client_unassign_ip|default:'Unassign'}</button>
                <a href="index.php?m=ipmanager" class="btn btn-default">{$LANG.cancel|default:'Cancel'}</a>
            </form>
        {else}
            <p class="text-success">{$LANG.client_unassign_done|default:'IP address has been unassigned.'}</p>
            <a href="index.php?m=ipmanager" class="btn btn-primary">{$LANG.client_ip_manager|default:'Back to IP Manager'}</a>
        {/if}
    </div>
</div>

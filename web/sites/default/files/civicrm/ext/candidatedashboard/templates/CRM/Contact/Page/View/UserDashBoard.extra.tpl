<div class="crm-dashboard-personalinfo">
    <div class="header-dark">Personal Info</div>
    <div class="view-content">
        {if $personal_rows || $address_rows}
            <div class="crm-block crm-content-block">
                <table class="crm-info-panel">
                {strip}
                    <tr>
                        <td class="label">{ts}First Name:{/ts}</td>
                        <td>{$personal_rows.first_name}</td>
                    </tr>
                    <tr>
                        <td class="label">{ts}Last Name:{/ts}</td>
                        <td>{$personal_rows.last_name}</td>
                    </tr>
                    <tr>
                        <td class="label">{ts}Address:{/ts}</td>
                        <td>
                            {foreach from=$address_rows item=row}
                                {if $row}
                                <span>{$row}</span>
                                <br>
                                {/if}
                            {/foreach}
                        </td>
                    </tr>
                    <tr>
                        <td class="label">{ts}Email:{/ts}</td>
                        <td>{$personal_rows.email}</td>
                    </tr>
                    <tr>
                        <td class="label">{ts}Phone Number(s):{/ts}</td>
                        <td>
                            {foreach from=$personal_rows.phone_numbers item=number}
                                {$number}
                                <br>
                            {/foreach}
                        </td>
                    </tr>
                {/strip}
                </table>
            </div>
        {else}
            <div class="messages status no-popup">
            {icon icon="fa-info-circle"}{/icon}
            {ts}You have not set any personal information yet.{/ts}
            </div>
        {/if}
    </div>
</div>
<div class="crm-dashboard-notes">
    <div class="header-dark">Your Notes</div>
    <div class="view-content">
        {if $notes.$row_count > 0}
            {foreach from=$notes item=note}
                <div class="crm-block crm-content-block crm-note-view-block">
                    <table class="crm-info-panel">
                    <tr><td class="label">{ts}Subject{/ts}</td><td>{$note.subject}</td></tr>
                    <tr><td class="label">{ts}Date:{/ts}</td><td>{$note.note_date|crmDate}</td></tr>
                    <tr><td class="label">{ts}Modified Date:{/ts}</td><td>{$note.modified_date|crmDate}</td></tr>
                    <tr><td class="label">{ts}Note:{/ts}</td><td>{$note.note|nl2br}</td></tr>
                    </table>
                </div>
            {/foreach}
        {else}
            <div class="messages status no-popup">
            {icon icon="fa-info-circle"}{/icon}
            {ts}You do not have any notes.{/ts}
            </div>
        {/if}
    </div>
</div>
{literal}
    <script type="text/javascript">
        cj(document).ready(function(){
            cj(".crm-dashboard-personalinfo").prependTo(".dashboard-elements tbody");
            cj(".crm-dashboard-notes").insertAfter(cj(".crm-dashboard-personalinfo"))
            cj(".crm-dashboard-groups").remove();
            cj(".crm-dashboard-civimember").remove();
            cj(".crm-dashboard-permissionedOrgs").remove();
            cj(".crm-dashboard-pcp").remove();
        });
    </script>
{/literal}

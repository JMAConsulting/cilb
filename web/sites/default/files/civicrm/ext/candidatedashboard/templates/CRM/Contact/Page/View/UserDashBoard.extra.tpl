<div class="personalinfo">
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
                    <tr>
                        <td colspan=2><a href="/form/request-information-change">{ts}I Need to Change My First, Last or Middle Name, Social Security Number, or Date of Birth{/ts}</a></td>
                    </tr>
                    <tr>
                        <td colspan=2><a href="/form/change-contact-information">{ts}I Need to Change My Phone Number, Email, or Address{/ts}<a></td>
                    </tr>
                {/strip}
                </table>
            </div>
        {else}
            <div class="messages status no-popup">
            {icon icon="fa-info-circle"}{/icon}
            {ts}You have not set any personal information yet.{/ts}
            <br>
            <a href="/form/request-information-change">{ts}Click here to add your First, Last or Middle Name or Social Security Number, Date of Birth, Address or Phone Number(s){/ts}</a>
            </div>
        {/if}
    </div>
</div>
<div class="notes">
    <div class="header-dark">Your Notes</div>
    <div class="view-content">
        {if $notes->rowCount > 0}
            {foreach from=$notes item=note}
                <div class="crm-block crm-content-block crm-note-view-block">
                    <table class="crm-info-panel">
                    <tr><td class="label">{ts}Subject{/ts}</td><td>{if $note.subject}{$note.subject}{else}{ts}No subject{/ts}{/if}</td></tr>
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
            cj(".dashboard-elements tbody").first().prepend("<tr class=\"crm-dashboard-notes\">").prepend("<tr class=\"crm-dashboard-personalinfo\">");
            cj(".crm-dashboard-personalinfo").append(cj("<td>").append(cj(".personalinfo").html()));
            cj(".crm-dashboard-notes").append(cj("<td>").append(cj(".notes").html()));
            cj(".personalinfo").remove();
            cj(".notes").remove();
            {/literal}
            {foreach from=$event_rows item=row}
                cj(".crm-participant-event-id_{$row.event_id}").parent().append(
                    cj("<td>")
                    {if $row.event_start_date > $smarty.now|date_format:'%Y-%m-%d %H:%M:%S'}
                        .append("<a href=\"/form/reschedule-exam?id={$row.event_id}\">Reschedule Exam</a>")
                    {/if}
                );
            {/foreach}
            {literal}
            cj(".crm-dashboard-civievent .description").remove();
            cj("[class^='crm-participant-event-id_']").children().each(function(){
                cj(this).replaceWith(cj(this).html());
            });
            cj(".crm-source_contact_name").remove();
            cj(".crm-target_contact_name").remove();
            cj(".crm-dashboard-activities .columnheader").children().eq(3).remove();
            cj(".crm-dashboard-activities .columnheader").children().eq(-3).remove();
            cj(".crm-dashboard-civicontribute tr").each(function(){
                cj(this).children().eq(4).remove();
                cj(this).children().eq(1).remove();
            });
        });
    </script>
{/literal}

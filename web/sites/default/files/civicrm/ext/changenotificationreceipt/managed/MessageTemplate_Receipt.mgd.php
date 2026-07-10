<?php

use CRM_ChangeNotificationReceipt_ExtensionUtil as E;

$html = <<<'HTML'
<div style="font-family: Arial, Helvetica, sans-serif; color: #222; font-size: 13px;">
<table cellspacing="0" cellpadding="0" style="width:100%; border:0; margin:0 0 16px 0;"><tr><td style="width:160px; vertical-align:top;"><img src="https://www.floridaexam.com/sites/default/files/logo_2.gif" alt="CILB" style="display:block; max-width:150px;"/></td><td style="vertical-align:top; padding-left:12px;"><h2 style="margin:0 0 4px 0;">Florida Construction Industry Licensing Board (CILB) Examinations Change Notification Receipt</h2><p style="margin:0; color:#555;">Prepared for <strong>{$contactDisplayName}</strong> on {$generatedDate}</p></td></tr></table>
<p style="margin:0 0 12px 0;">The following changes were made to your CILB Exam contact information or exam registration:</p>
{if empty($sections)}<p style="margin:0;"><em>No change details are available.</em></p>{else}{foreach from=$sections item=section}<h3 style="margin:18px 0 6px 0; border-bottom:1px solid #ccc; padding-bottom:4px;">{$section.heading}</h3>{foreach from=$section.entries item=entry}<p style="margin:8px 0 4px 0;"><strong>{$entry.action}</strong></p>{if empty($entry.changes)}<p style="margin:0 0 8px 12px; color:#555;"><em>Record {$entry.action|lower}.</em></p>{else}<table cellspacing="0" cellpadding="6" style="border-collapse:collapse; width:100%; margin-bottom:10px;"><tr style="background:#f2f2f2;"><th style="border:1px solid #ccc; text-align:left;">Field</th><th style="border:1px solid #ccc; text-align:left;">Previous</th><th style="border:1px solid #ccc; text-align:left;">Updated</th></tr>{foreach from=$entry.changes item=chg}<tr><td style="border:1px solid #ccc;">{$chg.label}</td><td style="border:1px solid #ccc; color:#999;">{$chg.old}</td><td style="border:1px solid #ccc;">{$chg.new}</td></tr>{/foreach}</table>{/if}{/foreach}{/foreach}{/if}
<p style="margin-top:20px; font-size:11px; color:#888;">This receipt was generated automatically by the Florida Construction Industry Licensing Board exam system.</p>
</div>
HTML;

return [
  [
    'name' => 'MessageTemplate_ChangeNotificationReceipt',
    'entity' => 'MessageTemplate',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'msg_title' => 'CILB Change Notification - Receipt',
        'msg_subject' => 'Florida Construction Industry Licensing Board (CILB) Examinations Change Notification Receipt',
        'msg_text' => 'Florida Construction Industry Licensing Board (CILB) Examinations Change Notification Receipt',
        'msg_html' => $html,
        'is_active' => TRUE,
        'is_reserved' => FALSE,
      ],
    ],
  ],
];

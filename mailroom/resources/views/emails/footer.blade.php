<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#71717a">
You are receiving this email because you subscribed to {{ $campaign->audience->name }}.<br>
<a href="{{ $unsubscribeUrl }}" style="color:#71717a">Unsubscribe</a>
@if ($pixel)<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px">@endif
</td></tr></table>

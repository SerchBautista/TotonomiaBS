@props(['url'])

@php
$logoUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/totonomia-logo-nobg.png';
@endphp

<tr>
<td class="header header-cell" style="padding: 0 0 24px;">
<table class="inner-body" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto; background: linear-gradient(135deg, #0d1422 0%, #12102a 100%); border: 1px solid rgba(125, 152, 197, 0.14); border-bottom: 0; border-radius: 24px 24px 0 0; overflow: hidden; box-shadow: 0 24px 56px rgba(4, 10, 20, 0.4);">
<tr>
<td class="header-cell" style="padding: 28px 40px; border-bottom: 1px solid rgba(125, 152, 197, 0.12);">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none; color: #eff6ff;">
<table cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="vertical-align: middle;">
<img src="{{ $logoUrl }}" alt="{{ config('app.name', 'FinTech') }}" width="48" height="48" style="display: block; border-radius: 10px;" />
</td>
<td style="padding-left: 12px; vertical-align: middle; font-size: 18px; line-height: 1; font-weight: 800; letter-spacing: -0.03em; color: #eff6ff;">
{{ trim($slot) ?: config('app.name', 'FinTech') }}
</td>
</tr>
</table>
</a>
</td>
</tr>
</table>
</td>
</tr>

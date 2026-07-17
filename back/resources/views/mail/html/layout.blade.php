<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name', 'FinTech') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="dark light" />
<meta name="supported-color-schemes" content="dark light" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet" />
<style>
@media only screen and (max-width: 600px) {
    .inner-body,
    .footer {
        width: 100% !important;
    }

    .content-cell,
    .header-cell,
    .footer-cell,
    .subcopy-cell,
    .panel-content {
        padding-left: 24px !important;
        padding-right: 24px !important;
    }
}

@media only screen and (max-width: 500px) {
    .button {
        width: 100% !important;
    }
}
</style>
{!! $head ?? '' !!}
</head>
@php
    $recipientEmail = isset($message)
        ? collect($message->getSymfonyMessage()->getTo() ?? [])->map(
            fn ($address) => method_exists($address, 'getAddress') ? $address->getAddress() : null,
        )->filter()->first()
        : null;
@endphp
<body style="margin: 0; padding: 40px 16px; width: 100% !important; background-color: #080b14; color: #97a7c3; -webkit-text-size-adjust: none; font-family: Inter, 'Segoe UI', Arial, sans-serif;">
<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 0; padding: 0; background-color: #080b14;">
<tr>
<td align="center" style="padding: 0;">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 0; padding: 0;">
{!! $header ?? '' !!}

<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0; padding: 0; border: 0;">
<table class="inner-body" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #0d1422; border: 1px solid rgba(125, 152, 197, 0.14); border-radius: 24px; overflow: hidden; box-shadow: 0 24px 56px rgba(4, 10, 20, 0.4);">
<tr>
<td class="content-cell" style="padding: 40px 40px 32px;">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

@if ($recipientEmail)
<tr>
<td align="center" style="padding: 24px 40px 0;">
<table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto;">
<tr>
<td style="text-align: center; color: #5f6f8f; font-size: 12px; line-height: 1.6; font-family: Inter, 'Segoe UI', Arial, sans-serif;">
Este correo fue enviado a <a href="mailto:{{ $recipientEmail }}" style="color: #8b5cf6; text-decoration: none;">{{ $recipientEmail }}</a>.
</td>
</tr>
</table>
</td>
</tr>
@endif

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>

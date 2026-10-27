<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $newsletter->subject }}</title>
</head>
@php
    $backgroundColor = e((string) ($newsletter->background_color ?: '#F4F1EA'));
    $innerBackgroundColor = e((string) ($newsletter->inner_background_color ?: '#FBF8F2'));
@endphp
<body style="margin:0;padding:0;background:{{ $backgroundColor }};color:#1f2a37;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $newsletter->preheader }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:{{ $backgroundColor }};padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;background:{{ $innerBackgroundColor }};border:1px solid #e6dccb;">
                    @if($preview)
                        <tr>
                            <td style="padding:12px 24px;background:#fff7d6;color:#6f5100;font-size:14px;">
                                Preview test send. This message has not been sent to newsletter subscribers.
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:0 32px 32px;font-size:16px;line-height:1.65;color:#1f2a37;">
                            {!! $renderedBodyHtml !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

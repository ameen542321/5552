<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <meta name="supported-color-schemes" content="dark light">
    <title>إعادة تعيين كلمة المرور - CARLED</title>
</head>

<body style="margin:0; padding:0; background:#020617; color:#f8fafc; font-family:'Cairo','DejaVu Sans',Tahoma,Arial,sans-serif; direction:rtl; text-align:right;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        استخدم الرابط الآمن لإعادة تعيين كلمة مرور حسابك في CARLED خلال {{ $expiresInMinutes }} دقيقة.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background:#020617; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; overflow:hidden; background:#0f172a; border:1px solid #334155; border-radius:16px; color:#f8fafc; box-shadow:0 20px 45px rgba(0,0,0,.28);">
                    <tr>
                        <td align="center" style="padding:30px 24px 14px;">
                            <div style="font-size:30px; line-height:1.3; font-weight:900; letter-spacing:.5px; color:#00C4B4;">CARLED</div>
                            <div style="margin-top:6px; font-size:13px; line-height:1.7; color:#94a3b8;">إدارة أعمالك بأمان</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 24px 0; text-align:center;">
                            <h1 style="margin:0; font-size:24px; line-height:1.6; font-weight:900; color:#f8fafc;">إعادة تعيين كلمة المرور</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 0; font-size:16px; line-height:1.9; color:#cbd5e1; text-align:right;">
                            @if(filled($recipientName))
                                <p style="margin:0 0 10px; color:#f8fafc;">مرحبًا {{ $recipientName }}،</p>
                            @endif
                            <p style="margin:0;">تلقينا طلبًا لإعادة تعيين كلمة مرور حسابك في CARLED. اضغط الزر التالي لإنشاء كلمة مرور جديدة.</p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:26px 24px;">
                            <a href="{{ $resetUrl }}" style="display:inline-block; min-width:220px; box-sizing:border-box; background:#00C4B4; color:#0f172a; padding:14px 24px; border:1px solid #00C4B4; border-radius:12px; text-decoration:none; font-size:16px; line-height:1.4; font-weight:900; text-align:center;">
                                إنشاء كلمة مرور جديدة
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 22px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background:#1e293b; border:1px solid #334155; border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:14px; line-height:1.8; color:#cbd5e1; text-align:center;">
                                        صلاحية هذا الرابط <strong style="color:#f8fafc;">{{ $expiresInMinutes }} دقيقة</strong>، ويمكن استخدامه لإعادة تعيين كلمة المرور مرة واحدة.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="font-size:14px; line-height:1.8; padding:0 28px 22px; color:#cbd5e1; text-align:center;">
                            إذا لم يعمل الزر، انسخ الرابط التالي والصقه في المتصفح:
                            <a href="{{ $resetUrl }}" dir="ltr" style="display:block; margin-top:10px; color:#22d3ee; overflow-wrap:anywhere; word-break:break-word; text-align:left; text-decoration:underline;">{{ $resetUrl }}</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background:rgba(185,28,28,.12); border:1px solid #7f1d1d; border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:14px; line-height:1.8; color:#fecaca; text-align:right;">
                                        <strong style="color:#fca5a5;">لم تطلب تغيير كلمة المرور؟</strong><br>
                                        تجاهل هذه الرسالة، ولا تشارك الرابط مع أي شخص. لن تتغير كلمة مرورك ما لم تستخدم الرابط وتعيّن كلمة مرور جديدة.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top:1px solid #1e293b; padding:18px 24px 24px; font-size:12px; line-height:1.8; color:#94a3b8; text-align:center;">
                            هذه رسالة آلية خاصة بأمان حسابك في CARLED؛ يرجى عدم الرد عليها.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

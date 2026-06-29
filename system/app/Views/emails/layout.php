<?php
/** Layout base para emails HTML. Variable: $content. */
use App\Services\AppSettings;
use Core\View;
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                <tr><td style="background:#4f46e5;padding:16px 24px;color:#ffffff;font-weight:bold;font-size:16px;">
                    <?= View::e(AppSettings::name()) ?>
                </td></tr>
                <tr><td style="padding:24px;">
                    <?= $content ?? '' ?>
                </td></tr>
                <tr><td style="padding:16px 24px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px;">
                    Este es un mensaje automático de <?= View::e(AppSettings::name()) ?>.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>

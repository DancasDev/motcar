<?php
$siteName = config('App')->siteName;
$year     = date('Y');
?>
<!DOCTYPE html>
<html lang="<?= esc($locale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?></title>
</head>
<body style="font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 32px 16px; color: #1E293B;">
    <div style="max-width: 480px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; overflow: hidden; border: 1px solid #E2E8F0; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);">
        <div style="padding: 20px 28px; background-color: #FFFFFF; border-bottom: 1px solid #E2E8F0; text-align: center;">
            <img src="cid:motcar_logo" alt="<?= esc($siteName) ?>" style="max-width: 155px; height: auto; display: inline-block; vertical-align: middle;">
        </div>

        <div style="padding: 32px 32px 28px;">
            <div style="text-align: center; margin-bottom: 24px;">
                <img src="cid:<?= esc($icon) ?>" width="64" height="64" alt="" style="display: block; width: 64px; height: 64px; margin: 0 auto 14px;">
                <h2 style="color: #0A2540; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.25;">
                    <?= esc($title) ?>
                </h2>
            </div>

            <?= $content ?>
        </div>

        <div style="background-color: #F1F5F9; border-top: 1px solid #E2E8F0; padding: 18px 28px; text-align: center;">
            <p style="color: #64748B; font-size: 12px; margin: 0 0 8px; line-height: 1.4;">
                <?= esc(lang('Service.email_footer.auto_generated', [$siteName], $locale)) ?>
            </p>
            <p style="color: #475569; font-size: 11.5px; font-weight: 600; letter-spacing: 0.03em; margin: 0;">
                <?= esc(lang('Service.email_footer.copyright', [$year], $locale)) ?>
            </p>
        </div>
    </div>
</body>
</html>

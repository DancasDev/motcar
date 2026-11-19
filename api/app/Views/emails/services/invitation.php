<?php
$locale = $locale ?? service('language')->getLocale();
$title  = lang('Service.user_invitation.subject', [], $locale);

ob_start();
?>
<p style="color: #0A2540; font-size: 15px; font-weight: 600; line-height: 1.5; margin: 0 0 10px;">
    <?= lang('Service.user_invitation.greeting', [esc($name)], $locale) ?>
</p>
<p style="color: #475569; font-size: 14.5px; line-height: 1.6; margin: 0 0 20px;">
    <?= lang('Service.user_invitation.body', [], $locale) ?>
</p>
<?php if (!empty($username)): ?>
    <div style="background-color: #F1F5F9; border: 1px solid #E2E8F0; border-left: 4px solid #DBB51D; padding: 14px 16px; border-radius: 8px; margin: 20px 0;">
        <div style="color: #1E293B; font-size: 14px; line-height: 1.5;">
            <?= lang('Service.user_invitation.username_info', [esc($username)], $locale) ?>
        </div>
    </div>
<?php endif; ?>
<div style="text-align: center; margin: 28px 0;">
    <a href="<?= esc($setupUrl) ?>" style="display: inline-block; font-size: 15px; font-weight: 700; color: #FFFFFF; background-color: #0E54A4; padding: 14px 32px; text-decoration: none; border-radius: 8px; letter-spacing: 0.02em; box-shadow: 0 4px 12px rgba(14, 84, 164, 0.2);">
        <?= lang('Service.user_invitation.button', [], $locale) ?>
    </a>
</div>
<p style="color: #64748B; font-size: 13px; line-height: 1.5; margin: 0 0 20px; text-align: center;">
    <?= lang('Service.user_invitation.expires', [esc($expiresAt)], $locale) ?>
</p>
<div style="border-top: 1px solid #E2E8F0; padding-top: 16px; margin-top: 16px;">
    <p style="color: #94A3B8; font-size: 12px; line-height: 1.5; margin: 0; word-break: break-all;">
        <?= esc($setupUrl) ?>
    </p>
</div>
<?php
$content = ob_get_clean();

echo view('emails/layout', [
    'locale'  => $locale,
    'title'   => $title,
    'icon'    => 'icon_key',
    'content' => $content,
], ['debug' => false]);

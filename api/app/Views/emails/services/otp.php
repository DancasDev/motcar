<?php
$locale     = $locale ?? service('language')->getLocale();
$emailTitle = $title ?? lang('Service.' . $actionCode . '.subject', [], $locale);

ob_start();
?>
<p style="color: #475569; font-size: 14.5px; line-height: 1.6; text-align: center; margin: 0 0 20px;">
    <?= lang('Service.otp_email.greeting', [], $locale) ?>
</p>
<div style="text-align: center; margin: 24px 0;">
    <span style="display: inline-block; font-size: 28px; font-weight: 700; letter-spacing: 6px; color: #0E54A4; background-color: #F1F5F9; border: 1px solid #E2E8F0; padding: 14px 28px; border-radius: 10px;">
        <?= esc($otpCode) ?>
    </span>
</div>
<p style="color: #64748B; font-size: 13px; line-height: 1.5; text-align: center; margin: 0 0 20px;">
    <?= lang('Service.otp_email.expires', [esc($expiresAt)], $locale) ?>
</p>
<p style="color: #94A3B8; font-size: 12px; line-height: 1.5; margin: 20px 0 0; border-top: 1px solid #E2E8F0; padding-top: 16px; text-align: center;">
    <?= lang('Service.otp_email.disclaimer', [], $locale) ?>
</p>
<?php
$content = ob_get_clean();

echo view('emails/layout', [
    'locale'  => $locale,
    'title'   => $emailTitle,
    'icon'    => 'icon_lock',
    'content' => $content,
], ['debug' => false]);

<?php
$locale       = $locale ?? service('language')->getLocale();
$actionUrl    = $action_url ?? ($payload['action_url'] ?? null);
$actionLabel  = $action_label ?? ($payload['action_label'] ?? lang('Notifications.cta_default', [], $locale));
$primaryColor = config('App')->primaryColor;

ob_start();
?>
<div style="color: #1E293B; line-height: 1.6; font-size: 14.5px; margin-bottom: 24px;">
    <?= nl2br(strip_tags($message ?? '', '<b><strong><i><em><u><a><br><p>')) ?>
</div>

<?php if (!empty($actionUrl)): ?>
    <div style="margin: 28px 0 8px; text-align: center;">
        <a href="<?= esc($actionUrl) ?>" style="display: inline-block; padding: 14px 32px; border-radius: 8px; background-color: <?= esc($primaryColor) ?>; color: #FFFFFF; text-decoration: none; font-weight: 700; font-size: 15px; letter-spacing: 0.02em; box-shadow: 0 4px 12px rgba(14, 84, 164, 0.2);">
            <?= esc($actionLabel) ?>
        </a>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();

echo view('emails/layout', [
    'locale'  => $locale,
    'title'   => $title ?? '',
    'icon'    => 'icon_bell',
    'content' => $content,
], ['debug' => false]);

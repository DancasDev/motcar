<?php

namespace App\Libraries\Comms\Drivers;

use App\Libraries\Comms\Exceptions\DeliveryFailedException;
use CodeIgniter\Email\Email;
use Config\Email as EmailConfig;
use Throwable;

class EmailDriver implements DriverInterface {
    /**
     * Cache estática en tiempo de ejecución para evitar llamadas redundantes a file_exists().
     */
    private static array $viewCache = [];

    /**
     * Envía un correo electrónico individual o a una lista de destinatarios.
     *
     * @param string|array $to           Correo o array con destinatarios ('to', 'cc', 'bcc' o lista indexada)
     * @param string       $title        Asunto del correo
     * @param string|array $message      Cuerpo en HTML/texto o array con metadatos
     * @param array        $options      Opciones (type_code, email_service, layout, etc.)
     *
     * @throws DeliveryFailedException
     */
    public function send(string|array $to, string $title, string|array $message, array $options = []): bool {
        /** @var EmailConfig $config */
        $config    = config('Email');
        $fromEmail = $config->fromEmail ?: 'no-reply@example.com';
        $fromName  = $config->fromName ?: config('App')->siteName;

        $mainTo = '';
        $cc     = null;
        $bcc    = null;

        if (is_array($to)) {
            if (isset($to['to'])) {
                $mainTo = is_array($to['to']) ? implode(',', array_filter(array_map('trim', $to['to']))) : trim($to['to']);
                $cc     = $to['cc'] ?? null;
                $bcc    = $to['bcc'] ?? null;
            } else {
                $mainTo = implode(',', array_filter(array_map('trim', $to)));
            }
        } else {
            $mainTo = trim($to);
        }

        if (empty($mainTo)) {
            throw new DeliveryFailedException('email', 'Destinatario principal (to) no especificado o vacío.');
        }

        /** @var Email $emailService */
        $emailService = $options['email_service'] ?? service('email');
        $emailService->clear(true);

        $typeCode = $options['type_code'] ?? (is_array($message) ? ($message['type_code'] ?? null) : null);
        $payload  = is_array($message) ? $message : ($options['payload'] ?? []);
        $rawBody  = is_array($message) ? ($message['body'] ?? ($message['message'] ?? '')) : $message;

        $htmlBody = $this->resolveHtmlBody($typeCode, $title, $rawBody, $payload, $options);
        $htmlBody = $this->embedInlineAssets($emailService, $htmlBody);

        $emailService->setFrom($fromEmail, $fromName);
        $emailService->setTo($mainTo);

        if (!empty($cc)) {
            $emailService->setCC($cc);
        }
        if (!empty($bcc)) {
            $emailService->setBCC($bcc);
        }

        $emailService->setSubject($title);
        $emailService->setMessage($htmlBody);
        $emailService->setMailType('html');

        try {
            if (!$emailService->send(!empty($options['auto_clear']))) {
                $debug = $emailService->printDebugger(['headers', 'subject', 'body']);
                throw new \RuntimeException(strip_tags($debug));
            }
            return true;
        } catch (Throwable $e) {
            log_message('error', '[EmailDriver] Error enviando correo: ' . $e->getMessage());
            throw new DeliveryFailedException('email', $e->getMessage(), 502, $e);
        }
    }

    /**
     * Adjunta en línea (multipart/related CID) los recursos gráficos referenciados como src="cid:nombre".
     */
    private function embedInlineAssets(Email $emailService, string $htmlBody): string {
        if (!preg_match_all('/src=["\']cid:([a-z0-9_-]+)["\']/i', $htmlBody, $matches)) {
            return $htmlBody;
        }

        foreach (array_unique($matches[1]) as $assetName) {
            $assetPath = APPPATH . "Views/emails/assets/{$assetName}.png";
            if (is_file($assetPath)) {
                $emailService->attach($assetPath, 'inline');
                $cid = $emailService->setAttachmentCID($assetPath);
                if (is_string($cid)) {
                    $htmlBody = str_replace("cid:{$assetName}", "cid:{$cid}", $htmlBody);
                }
            }
        }

        return $htmlBody;
    }

    /**
     * Resuelve el HTML a partir de una vista específica de notificación o del layout estándar (default.php).
     */
    private function resolveHtmlBody(?string $typeCode, string $title, string $rawBody, array $payload, array $options): string {
        // 1. Si ya es un documento HTML completo
        if (str_starts_with(trim($rawBody), '<!') || str_starts_with(trim($rawBody), '<html')) {
            return $rawBody;
        }

        // 2. Determinar la plantilla a usar (específica del typeCode o default)
        $template = 'default';
        if (!empty($typeCode)) {
            if (!isset(self::$viewCache[$typeCode])) {
                self::$viewCache[$typeCode] = file_exists(APPPATH . "Views/emails/notifications/{$typeCode}.php")
                    ? $typeCode
                    : 'default';
            }
            $template = self::$viewCache[$typeCode];
        }

        // 3. Renderizar vista correspondiente con las variables del evento
        return view("emails/notifications/{$template}", [
            'title'        => $title,
            'message'      => $rawBody,
            'action_url'   => $payload['action_url'] ?? ($options['action_url'] ?? null),
            'action_label' => $payload['action_label'] ?? ($options['action_label'] ?? null),
            'payload'      => $payload,
        ], ['debug' => false]);
    }
}

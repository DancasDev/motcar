<?php

namespace App\Libraries\Comms\Drivers;

use App\Libraries\Comms\Exceptions\DeliveryFailedException;
use App\Libraries\Comms\Outbox\NotificationInterpolator;
use App\Models\System\NotificationDeviceModel;
use Config\Comms;
use Throwable;

class FcmPushDriver implements BatchDriverInterface
{
    private string $projectId;

    public function __construct(?string $projectId = null)
    {
        /** @var Comms $comms */
        $comms = config('Comms');
        $this->projectId = $projectId ?? $comms->firebaseProjectId;
    }

    /**
     * Envía una notificación push a un token o lista de tokens.
     */
    public function send(string|array $to, string $title, string|array $message, array $options = []): bool
    {
        $tokens = array_values(array_filter(array_map('trim', (array) $to)));
        if (empty($tokens)) {
            throw new DeliveryFailedException('push', 'Token FCM no proporcionado o lista de tokens vacía.');
        }

        $bodyText = is_array($message) ? ($message['message'] ?? ($message['body'] ?? '')) : (string) $message;
        $data = is_array($message) ? $message : $options;

        $items = [];
        foreach ($tokens as $idx => $t) {
            $items[$idx] = [
                'token'   => $t,
                'title'   => $title,
                'message' => $bodyText,
                'data'    => $data,
            ];
        }

        $results = $this->sendBatch($items);
        $sentCount = 0;
        $errors = [];

        foreach ($results as $idx => $res) {
            if (!empty($res['success'])) {
                $sentCount++;
            } else {
                $t = $tokens[$idx] ?? 'unknown';
                $errMsg = $res['error'] ?? 'Error desconocido';
                $errors[] = "[Token: {$t}]: {$errMsg}";
                log_message('error', "[FcmPushDriver] Error enviando a token FCM: {$errMsg}");
            }
        }

        if ($sentCount === 0) {
            throw new DeliveryFailedException('push', 'Fallo en la entrega a todos los tokens FCM (' . implode('; ', $errors) . ')');
        }

        return true;
    }

    /**
     * Envía un lote de notificaciones Push concurrentemente usando curl_multi nativo.
     *
     * @param array<int|string, array{token?: string, title: string, message: string, data?: array}> $items
     * @return array<int|string, array{success: bool, error?: string, message_id?: string}>
     */
    public function sendBatch(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        if (empty($this->projectId)) {
            throw new DeliveryFailedException('push', 'comms.firebase_project_id no configurado en el entorno');
        }

        $accessToken = $this->getAccessToken();
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; UTF-8',
        ];

        $mh = curl_multi_init();
        $handles = [];
        $tokens = [];
        $results = [];

        foreach ($items as $key => $item) {
            $token = trim((string) ($item['token'] ?? ''));
            if (empty($token)) {
                $results[$key] = ['success' => false, 'error' => 'Token FCM vacío'];
                continue;
            }

            $payload = $this->buildPayload($token, (string) ($item['title'] ?? ''), (string) ($item['message'] ?? ''), (array) ($item['data'] ?? []));
            $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => $jsonBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
            $tokens[$key] = $token;
        }

        if (empty($handles)) {
            curl_multi_close($mh);
            return $results;
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 0.1);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $deviceModel = null;
        foreach ($handles as $key => $ch) {
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseBody = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $body = json_decode((string) $responseBody, true) ?? [];
            $token = $tokens[$key];

            if ($httpCode >= 200 && $httpCode < 300) {
                $results[$key] = [
                    'success'    => true,
                    'message_id' => $body['name'] ?? null,
                ];
            } else {
                $errorCode = $body['error']['details'][0]['errorCode'] ?? $body['error']['status'] ?? '';
                if (in_array($errorCode, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true) || $httpCode === 404) {
                    $deviceModel ??= new NotificationDeviceModel();
                    $deviceModel->deactivateInvalidToken($token);
                    $errMsg = "Token FCM inválido/desregistrado ({$errorCode})";
                } else {
                    $errMsg = $body['error']['message'] ?? "FCM Error HTTP {$httpCode}";
                }

                $results[$key] = [
                    'success' => false,
                    'error'   => $errMsg,
                ];
            }
        }

        curl_multi_close($mh);
        return $results;
    }

    /**
     * Construye el payload de mensaje compatible con FCM HTTP v1.
     */
    public function buildPayload(string $token, string $title, string $message, array $data = []): array
    {
        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        $rawActionUrl = $data['action_url'] ?? $data['url'] ?? $data['link'] ?? null;
        $actionUrl = !empty($rawActionUrl) ? NotificationInterpolator::interpolate((string) $rawActionUrl, $data) : null;

        /** @var Comms $comms */
        $comms = config('Comms');
        $icon  = !empty($data['icon']) ? (string) $data['icon'] : $comms->pushDefaultIcon;
        $badge = !empty($data['badge']) ? (string) $data['badge'] : $comms->pushDefaultBadge;
        $color = !empty($data['color']) ? (string) $data['color'] : $comms->pushDefaultColor;
        $image = !empty($data['image']) ? (string) $data['image'] : null;

        $webpushNotification = [
            'title' => $title,
            'body'  => $message,
            'icon'  => $icon,
            'badge' => $badge,
        ];
        if (!empty($image)) {
            $webpushNotification['image'] = $image;
        }

        $webpush = [
            'headers'      => [
                'Urgency' => 'high',
            ],
            'notification' => $webpushNotification,
        ];

        if (!empty($actionUrl)) {
            $webpush['fcm_options'] = ['link' => (string) $actionUrl];
        }

        $androidNotification = [
            'title' => $title,
            'body'  => $message,
            'color' => $color,
        ];
        if (!empty($image)) {
            $androidNotification['image'] = $image;
        }

        $messagePayload = [
            'token'        => $token,
            'notification' => [
                'title' => $title,
                'body'  => $message,
            ],
            'webpush'      => $webpush,
            'android'      => [
                'priority'     => 'HIGH',
                'notification' => $androidNotification,
            ],
        ];

        if (!empty($stringData)) {
            $messagePayload['data'] = $stringData;
        }

        return ['message' => $messagePayload];
    }

    /**
     * Obtiene el token OAuth2 de Google Bearer mediante JWT RS256 con cacheo.
     */
    public function getAccessToken(): string
    {
        $cache = service('cache');
        $cacheKey = 'ext_fcm_token_' . md5($this->projectId);

        $cached = $cache->get($cacheKey);
        if (is_string($cached) && !empty($cached)) {
            return $cached;
        }

        $credentials = $this->loadServiceAccountCredentials();
        $clientEmail = $credentials['client_email'] ?? '';
        $privateKey  = $credentials['private_key'] ?? '';

        if (empty($clientEmail) || empty($privateKey)) {
            throw new \RuntimeException('Credenciales de Firebase Service Account inválidas (faltan client_email o private_key)');
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = $this->encodeJwt($header, $claims, $privateKey);

        $response = service('curlrequest')->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
            'timeout'     => 10,
            'http_errors' => false,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if (empty($body['access_token'])) {
            $err = $body['error_description'] ?? ($body['error'] ?? 'No se pudo obtener access_token de Google OAuth2');
            throw new \RuntimeException('Google OAuth2 error: ' . $err);
        }

        $token = (string) $body['access_token'];
        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        $cache->save($cacheKey, $token, max(60, $expiresIn - 120));

        return $token;
    }

    private function encodeJwt(array $header, array $claims, string $privateKey): string
    {
        $b64Header = $this->base64UrlEncode(json_encode($header));
        $b64Claims = $this->base64UrlEncode(json_encode($claims));
        $payload   = "{$b64Header}.{$b64Claims}";

        $signature = '';
        $keyResource = openssl_pkey_get_private($privateKey);
        if ($keyResource === false) {
            throw new \RuntimeException('Private key de Firebase Service Account no es un PEM RS256 válido');
        }

        $success = openssl_sign($payload, $signature, $keyResource, OPENSSL_ALGO_SHA256);
        if (!$success) {
            throw new \RuntimeException('Fallo al firmar JWT con RS256: ' . openssl_error_string());
        }

        return "{$payload}." . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function loadServiceAccountCredentials(): array
    {
        /** @var Comms $comms */
        $comms = config('Comms');

        $filePath = ROOTPATH . $comms->firebaseCredentialsFile;
        if (file_exists($filePath)) {
            $content = (string) file_get_contents($filePath);
            $json = json_decode($content, true);
            if (is_array($json) && !empty($json['client_email'])) {
                return $json;
            }
        }

        $jsonString = $comms->firebaseCredentialsJson;
        if (!empty($jsonString)) {
            $json = json_decode($jsonString, true);
            if (is_array($json) && !empty($json['client_email'])) {
                return $json;
            }
        }

        throw new \RuntimeException(
            "No se encontraron credenciales de Firebase en [{$filePath}] ni en config('Comms')->firebaseCredentialsJson"
        );
    }
}

<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Filters\BaseTraits;

class ActionTokenFilter implements FilterInterface {
    use BaseTraits;

    public function __construct() {
        $this->setting = [
            'action' => null,
        ];
    }

    public function before(RequestInterface $request, $arguments = null) {
        if ($request->is('options')) {
            return null;
        }

        $this->initSetting($arguments);

        if (empty($this->setting['action'])) {
            return $this->fail(lang('ActionToken.action_not_found'), 400, 'ACTION_TOKEN_ACTION_REQUIRED');
        }

        $rawToken = $this->extractToken($request);
        if (empty($rawToken)) {
            return $this->fail(lang('ActionToken.token_required'), 400, 'ACTION_TOKEN_REQUIRED');
        }

        try {
            $tokenService = service('actionToken');
            $tokenService->decode($rawToken);
        } catch (\Throwable) {
            return $this->fail(lang('ActionToken.invalid'), 401, 'ACTION_TOKEN_INVALID');
        }

        $exp = (int) $tokenService->getPayloadValue('exp');
        if ($exp > 0 && time() > $exp) {
            return $this->fail(lang('ActionToken.expired'), 401, 'ACTION_TOKEN_EXPIRED');
        }

        if ($tokenService->isRevoked(false)) {
            return $this->fail(lang('ActionToken.used'), 410, 'ACTION_TOKEN_USED');
        }

        if ($tokenService->getPayloadValue('act') !== $this->setting['action']) {
            return $this->fail(lang('ActionToken.invalid'), 401, 'ACTION_TOKEN_INVALID');
        }

        service('context')->set('actionToken:payload', $tokenService->getPayload());
        service('context')->set('actionToken:raw', $rawToken);
        service('context')->set('actionToken:instance', $tokenService);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}

    private function extractToken(RequestInterface $request): ?string {
        $header = $request->getHeaderLine('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            $bearer = trim(substr($header, 7));
            if (!empty($bearer)) {
                return $bearer;
            }
        }

        $body = $request->getJSON(true);
        if (is_array($body)) {
            $val = $body['action_token'] ?? ($body['token'] ?? null);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        $get = $request->getGet('token') ?? $request->getGet('action_token');
        if (is_string($get) && trim($get) !== '') {
            return trim($get);
        }

        return null;
    }
}

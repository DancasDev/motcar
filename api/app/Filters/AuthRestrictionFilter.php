<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Filters\BaseTraits;

class AuthRestrictionFilter implements FilterInterface {
    use BaseTraits;

    public function __construct() {
        $this->setting = [
            'restrict_date'   => true,
            'restrict_ip'     => true,
            'restrict_domain' => true,
            'scope'           => null,
        ];
    }

    public function before(RequestInterface $request, $arguments = null) {
        if ($request->is('options')) {
            return null;
        }

        $this->initSetting($arguments);
        $authToken = service('authToken');
        $authAccess = service('authAccess');
        $subt = $authToken->getPayloadValue('subt');
        $sub = $authToken->getPayloadValue('sub');

        if (empty($subt) || empty($sub)) {
            return null;
        }

        try {
            $scopePath = service('context')->get('auth:scopePath', '*');
            $authAccess->setEntity($subt, $sub)->setScope($scopePath);
            $externalData = $this->getExternalData($request);
            $result = $authAccess->getRestrictionResult($externalData, true);
        } catch (\Exception $e) {
            return $this->fail(lang('AuthFilter.restriction.load_exception'), 500, 'AUTH_RESTRICTION_LOAD_EXCEPTION');
        }

        if (!$result->passed) {
            $key = 'AuthFilter.restriction.by_' . $result->type . '_fail.' . $result->rule;
            $params = $this->getMessageParams($result);
            return $this->fail(lang($key, $params), 403, 'AUTH_RESTRICTION_' . strtoupper($result->type) . '_' . strtoupper($result->rule) . '_FAIL');
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}

    private function getExternalData(RequestInterface $request): array {
        $data = [];

        if ($this->setting['restrict_date']) {
            $data['date'] = ['timestamp' => time()];
        }

        if ($this->setting['restrict_ip']) {
            $ip = $request->getIPAddress();
            if ($ip !== null && $ip !== '') {
                $data['ip'] = ['ip' => $ip];
            }
        }

        if ($this->setting['restrict_domain']) {
            $host = $request->getServer('HTTP_HOST');
            if ($host !== null && $host !== '') {
                $data['domain'] = ['host' => explode(':', $host)[0]];
            }
        }

        return $data;
    }

    private function getMessageParams($result): array {
        if ($result->type !== 'date' || !is_array($result->ruleConfig)) {
            return [];
        }

        if (in_array($result->rule, ['before', 'after'], true)) {
            $d = $result->ruleConfig['d'] ?? null;
            return ['d' => is_int($d) ? date('Y-m-d', $d) : (string) $d];
        }

        if (in_array($result->rule, ['in_range', 'out_range'], true)) {
            $sd = $result->ruleConfig['sd'] ?? null;
            $ed = $result->ruleConfig['ed'] ?? null;
            return [
                'sd' => is_int($sd) ? date('Y-m-d', $sd) : (string) $sd,
                'ed' => is_int($ed) ? date('Y-m-d', $ed) : (string) $ed,
            ];
        }

        return [];
    }
}

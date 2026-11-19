<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Filters\BaseTraits;

class OtpFilter implements FilterInterface {
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
            return $this->fail(lang('Otp.action_not_found'), 400, 'OTP_ACTION_REQUIRED');
        }

        $input = $request->getJSON(true);
        $verificationId = $input['verification_id'] ?? null;
        $otpCode = $input['otp_code'] ?? null;

        if (empty($verificationId) || empty($otpCode)) {
            return $this->fail(lang('Otp.input_required'), 400, 'OTP_INPUT_REQUIRED');
        }

        try {
            service('otpManager')->verify(
                (int) $verificationId,
                $otpCode,
                $this->setting['action']
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 400, 'OTP_VERIFICATION_FAILED');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}

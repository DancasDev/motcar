<?php

namespace App\Controllers\V1\System\Services\Email;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Models\Gac\UserModel;
use App\Models\System\BinnacleModel;
use App\Libraries\Otp\OtpPendingException;

/**
 * Verificación de correo electrónico de usuario mediante OTP.
 *
 * Flujo en dos pasos:
 * 1. create() — Envía código OTP al correo del usuario autenticado.
 * 2. update() — Valida código OTP, actualiza email_verified_date y remueve banderas force_to.
 */
class Verification extends BaseController {
    protected $modelName = 'App\Models\Gac\UserModel';
    protected $format = null;

    public function create(): Response {
        $authToken = service('authToken');
        $sub = (int) $authToken->getPayloadValue('sub');

        $userData = $this->model->validateIntegrity('user_id', $sub, true);
        if (empty($userData)) {
            return $this->fail(
                lang('Service.email_verification.error_user_not_found'),
                400,
                'SERVICE_EMAIL_VERIFICATION_USER_NOT_FOUND'
            );
        }

        if (empty($userData['email'])) {
            return $this->fail(
                lang('Service.email_verification.email_not_found'),
                404,
                'SERVICE_EMAIL_VERIFICATION_EMAIL_NOT_FOUND'
            );
        }

        if ($userData['email_verified_date'] !== null) {
            return $this->fail(
                lang('Service.email_verification.already_verified'),
                400,
                'SERVICE_EMAIL_VERIFICATION_ALREADY_VERIFIED'
            );
        }

        $db = db_connect();
        $db->transBegin();

        $verificationId = null;

        try {
            $otpResult = service('otpManager')->request(
                $sub,
                'email_verification'
            );

            $verificationId = $otpResult['verification_id'];

            $userLocale = (new \App\Models\Global\LanguageModel())->resolveLocale($userData['language_id'] ?? null, $this->request->getLocale());
            $subject    = lang('Service.email_verification.subject', [], $userLocale);
            $body       = view('emails/services/otp', [
                'locale'     => $userLocale,
                'actionCode' => 'email_verification',
                'otpCode'    => $otpResult['plain_code'],
                'expiresAt'  => $otpResult['expires_at'],
            ]);

            service('messaging')->send('email', $userData['email'], $subject, $body);

            $db->transCommit();

            $binnacle = new BinnacleModel();
            $binnacle->log(
                'user',
                $sub,
                'email_verification',
                (string) $otpResult['verification_id'],
                'create',
                date('Y-m-d H:i:s'),
                $this->request->getIPAddress(),
                ['email' => $userData['email']]
            );
        } catch (OtpPendingException) {
            $db->transRollback();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Email verification error: ' . $e->getMessage());
            return $this->fail(
                lang('Service.email_verification.error_send_email'),
                500,
                'SERVICE_EMAIL_VERIFICATION_ERROR_SEND_EMAIL'
            );
        }

        return $this->respond([
            'status'   => 201,
            'data'     => [
                'verification_id' => $verificationId,
            ],
            'messages' => [
                'success' => lang('Service.email_verification.sent'),
            ],
        ], 201);
    }

    public function update($id = null): Response {
        $input = $this->getInput();
        if ($input instanceof Response) return $input;

        $verificationId = (int) ($input['verification_id'] ?? 0);
        if (empty($verificationId)) {
            return $this->fail(lang('Otp.input_required'), 400, 'OTP_INPUT_REQUIRED');
        }

        $authToken = service('authToken');
        $sub = (int) $authToken->getPayloadValue('sub');

        $userData = $this->model->validateIntegrity('user_id', $sub, true);
        if (empty($userData)) {
            return $this->fail(
                lang('Service.email_verification.error_user_not_found'),
                400,
                'SERVICE_EMAIL_VERIFICATION_USER_NOT_FOUND'
            );
        }

        if ($userData['email_verified_date'] !== null) {
            return $this->fail(
                lang('Service.email_verification.already_verified'),
                400,
                'SERVICE_EMAIL_VERIFICATION_ALREADY_VERIFIED'
            );
        }

        $record = service('otpManager')->getRecord($verificationId);
        if (empty($record)) {
            return $this->fail(lang('Otp.record_not_found'), 400, 'OTP_RECORD_NOT_FOUND');
        }

        if ((int) $record['user_id'] !== $sub) {
            return $this->fail(lang('Otp.user_mismatch'), 400, 'OTP_USER_MISMATCH');
        }

        $newForceTo = $this->model->removeForceToFlag($sub, ['1'], $userData['force_to']);

        $this->model->skipValidation(true)->protect(false)
            ->set('email_verified_date', date('Y-m-d H:i:s'))
            ->set('force_to', $newForceTo)
            ->where('id', $sub)
            ->limit(1)
            ->update();

        service('otpManager')->invalidate($verificationId);

        $binnacle = new BinnacleModel();
        $binnacle->log(
            'user',
            $sub,
            'email_verification',
            (string) $verificationId,
            'update',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            ['email' => $userData['email']]
        );

        return $this->respond([
            'status'   => 200,
            'data'     => [
                'user_id'          => $sub,
                'refresh_required' => true,
            ],
            'messages' => [
                'success' => lang('Service.email_verification.success'),
            ],
        ], 200);
    }
}

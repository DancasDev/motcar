<?php

namespace App\Controllers\V1\System\Services\Password;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Models\Gac\UserModel;
use App\Models\System\BinnacleModel;
use App\Controllers\Traits\TimingAttack;
use App\Libraries\Otp\OtpPendingException;
use App\Libraries\JWT\Token;

/**
 * Recuperación de contraseña mediante OTP.
 *
 * Flujo en dos pasos:
 * 1. create() — Solicita OTP por correo con protección contra timing attack y anti-enumeración.
 * 2. update() — Valida OTP y actualiza la contraseña del usuario, revocando sesiones activas.
 */
class Recovery extends BaseController {
    use TimingAttack;

    protected $modelName = 'App\Models\Gac\UserModel';
    protected $format = null;

    public function create(): Response {
        $this->startTimingAttack(1200, 100);
        $input = $this->getInput();
        if ($input instanceof Response) return $input;

        $validator = service('validation');
        $validator->setRules([
            'email' => ['rules' => 'required|string|valid_email'],
        ]);
        if (!$validator->run($input)) {
            return $this->fail($validator->getErrors(), 400, 'FAIL_VALIDATION');
        }

        $email = $validator->getValidated()['email'];
        $userData = $this->model->validateIntegrity('email', $email);

        $emailFailed = false;
        $verificationId = null;

        if ($userData) {
            $db = db_connect();
            $db->transBegin();

            try {
                $otpResult = service('otpManager')->request(
                    (int) $userData['user_id'],
                    'password_recovery'
                );

                $verificationId = $otpResult['verification_id'];

                $userLocale = (new \App\Models\Global\LanguageModel())->resolveLocale($userData['language_id'] ?? null, $this->request->getLocale());
                $subject    = lang('Service.password_recovery.subject', [], $userLocale);
                $body       = view('emails/services/otp', [
                    'locale'     => $userLocale,
                    'actionCode' => 'password_recovery',
                    'otpCode'    => $otpResult['plain_code'],
                    'expiresAt'  => $otpResult['expires_at'],
                ]);

                service('messaging')->send('email', $email, $subject, $body);

                $db->transCommit();

                $binnacle = new BinnacleModel();
                $binnacle->log(
                    'user',
                    (int) $userData['user_id'],
                    'password_recovery',
                    (string) $verificationId,
                    'create',
                    date('Y-m-d H:i:s'),
                    $this->request->getIPAddress(),
                    ['email' => $email]
                );
            } catch (OtpPendingException) {
                $db->transRollback();
            } catch (\Throwable $e) {
                $db->transRollback();
                log_message('error', 'Password recovery email error: ' . $e->getMessage());
                $emailFailed = true;
            }
        }

        $this->endTimingAttack();

        if ($emailFailed) {
            return $this->fail(
                lang('Service.password_recovery.error_send_email'),
                500,
                'SERVICE_PASSWORD_RECOVERY_ERROR_SEND_EMAIL'
            );
        }

        $responseData = ['verification_id' => $verificationId ?? $this->fakeVerificationId()];

        return $this->respond([
            'status'   => 201,
            'data'     => $responseData,
            'messages' => [
                'success' => lang('Service.password_recovery.sent'),
            ],
        ], 201);
    }

    /**
     * Genera un verification_id falso cuando el correo no pertenece a ningún usuario.
     * La respuesta es indistinguible de un caso real (anti-enumeración); al intentar
     * usarlo en la confirmación fallará con OTP_RECORD_NOT_FOUND.
     */
    private function fakeVerificationId(): int {
        return random_int(1000000, 999999999);
    }

    public function update($id = null): Response {
        $input = $this->getInput();
        if ($input instanceof Response) return $input;

        $verificationId = (int) ($input['verification_id'] ?? 0);
        if (empty($verificationId)) {
            return $this->fail(lang('Otp.input_required'), 400, 'OTP_INPUT_REQUIRED');
        }

        $passwordRules = $this->model->getValidationRules()['password'];

        $validator = service('validation');
        $validator->setRules([
            'password' => $passwordRules,
        ]);
        if (!$validator->run($input)) {
            return $this->fail($validator->getErrors(), 400, 'FAIL_VALIDATION');
        }

        $password = $validator->getValidated()['password'];
        $record = service('otpManager')->getRecord($verificationId);

        if (empty($record)) {
            return $this->fail(lang('Otp.record_not_found'), 400, 'OTP_RECORD_NOT_FOUND');
        }

        $userData = $this->model->validateIntegrity('user_id', $record['user_id'], true);
        if (empty($userData)) {
            service('otpManager')->invalidate($verificationId);
            return $this->fail(
                lang('Service.password_recovery.error_user_not_found'),
                400,
                'SERVICE_PASSWORD_RECOVERY_USER_NOT_FOUND'
            );
        }

        $this->model->skipValidation(true)->protect(false)->update($userData['user_id'], [
            'password' => $password,
        ]);

        Token::revokeGlobal($userData['user_id'], '1');

        service('otpManager')->invalidate($verificationId);

        $binnacle = new BinnacleModel();
        $binnacle->log(
            'user',
            (int) $userData['user_id'],
            'password_recovery',
            (string) $verificationId,
            'update',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            [
                'password' => str_repeat('*', strlen($password)),
                'email'    => $userData['email'] ?? null,
            ]
        );

        return $this->respond([
            'status'   => 200,
            'data'     => [
                'user_id' => (int) $userData['user_id'],
            ],
            'messages' => [
                'success' => lang('Service.password_recovery.success'),
            ],
        ], 200);
    }
}

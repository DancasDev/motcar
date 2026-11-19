<?php

namespace App\Controllers\V1\System\Services\Invitations;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Models\Global\LanguageModel;
use App\Models\System\BinnacleModel;

/**
 * Servicio de invitaciones de usuario y configuración de credenciales (onboarding).
 *
 * Flujo:
 * - create() — Genera y despacha por correo un action_token con expiración configurable.
 * - index()  — Consulta datos básicos del usuario para la pantalla de bienvenida usando el token.
 * - update() — Establece la contraseña, activa la cuenta y consume el token.
 */
class Home extends BaseController {
    protected $modelName = 'App\Models\Gac\UserModel';

    /**
     * Consulta datos básicos no sensibles del usuario para la pantalla de bienvenida / setup.
     */
    public function index(): Response {
        $userId = (int) (service('context')->get('actionToken:payload')['sub'] ?? 0);
        if ($userId <= 0) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $user = $this->model
            ->select('first_name, last_name, photo_url')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->first();

        if (empty($user)) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $this->respond([
            'status' => 200,
            'data'   => $user,
        ], 200);
    }

    /**
     * Envía una invitación a un usuario del sistema para configurar sus credenciales.
     */
    public function create($id = null): Response {
        $userId = (int) ($id ?? $this->getInput(false)['user_id'] ?? 0);

        if ($userId <= 0) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        // Obtener usuario activo, no eliminado y que nunca haya iniciado sesión
        $user = $this->model
            ->select('id, username, email, first_name, is_disabled, last_login_date, deleted_at, language_id')
            ->where('id', $userId)
            ->where('is_disabled', '0')
            ->where('last_login_date', null)
            ->where('deleted_at', null)
            ->first();

        if (!$user) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $ttl = config('Jwt')->getActionTtl('user_invitation');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $setupBaseUrl = rtrim(config('App')->webBaseURL ?: base_url(), '/');

        $tokenService = service('actionToken', false);
        $tokenService->setExpirationTime($ttl);
        $tokenService->setPayloadValue([
            'sub'   => (int) $user['id'],
            'act'   => 'user_invitation',
            'email' => $user['email'],
        ]);
        $jwt = $tokenService->encode();
        $setupUrl = "{$setupBaseUrl}/setup?token={$jwt}";

        // Preparar correo en el idioma configurado del usuario (o idioma de la solicitud si no tiene)
        $userLocale = (new LanguageModel())->resolveLocale($user['language_id'] ?? null, $this->request->getLocale());
        $subject    = lang('Service.user_invitation.subject', [], $userLocale);
        $body       = view('emails/services/invitation', [
            'locale'    => $userLocale,
            'name'      => $user['first_name'] ?: $user['username'],
            'username'  => $user['username'],
            'setupUrl'  => $setupUrl,
            'expiresAt' => $expiresAt,
        ]);

        // Despacho de correo
        try {
            service('messaging')->send('email', $user['email'], $subject, $body);
        } catch (\Throwable $e) {
            log_message('error', "[Invitations] Error sending email to user {$userId}: " . $e->getMessage());
            return $this->fail(lang('Service.user_invitation.error_send_email'), 500, 'INVITATION_SEND_ERROR');
        }

        // Registro en bitácora
        (new BinnacleModel())->log(
            'user',
            service('context')->get('auth:userId'),
            'gac_user',
            (string) $user['id'],
            'invitation',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            [
                'email'      => $user['email'],
                'expires_at' => $expiresAt,
            ]
        );

        return $this->respond([
            'status'   => 201,
            'data'     => [
                'user_id'    => (int) $user['id'],
                'expires_at' => $expiresAt,
            ],
            'messages' => [
                'success' => lang('Service.user_invitation.sent'),
            ],
        ], 201);
    }

    /**
     * Establece la contraseña y activa la cuenta del usuario mediante el token de invitación.
     */
    public function update($id = null): Response {
        $userId = (int) (service('context')->get('actionToken:payload')['sub'] ?? 0);
        if ($userId <= 0) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $input = $this->getInput();
        if ($input instanceof Response) return $input;

        $passwordRules = $this->model->getValidationRules()['password'] ?? 'required|min_length[8]';

        $validator = service('validation');
        $validator->setRules([
            'password' => $passwordRules,
            'username' => 'permit_empty|string',
        ]);
        if (!$validator->run($input)) {
            return $this->fail($validator->getErrors(), 400, 'FAIL_VALIDATION');
        }

        $validated = $validator->getValidated();

        $user = $this->model
            ->select('id, username, email, force_to')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->first();

        if (empty($user)) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        if (!empty($validated['username']) && $validated['username'] !== $user['username']) {
            return $this->fail(lang('Service.password_setup.username_mismatch'), 400, 'SETUP_USERNAME_MISMATCH');
        }

        $password   = $validated['password'];
        $newForceTo = $this->model->removeForceToFlag($userId, ['0', '1'], $user['force_to'] ?? null);

        $this->model->skipValidation(true)->protect(false)->update($userId, [
            'password'            => $password,
            'force_to'            => $newForceTo,
            'is_disabled'         => '0',
            'email_verified_date' => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        // Invalidar el token de acción usando la instancia ya decodificada por ActionTokenFilter
        service('context')->get('actionToken:instance')?->revoke(true);

        (new BinnacleModel())->log(
            'user',
            $userId,
            'gac_user',
            (string) $userId,
            'password_setup',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            [
                'password' => str_repeat('*', strlen($password)),
                'email'    => $user['email'] ?? null,
            ]
        );

        return $this->respond([
            'status'   => 200,
            'data'     => [
                'user_id' => $userId,
            ],
            'messages' => [
                'success' => lang('Service.password_setup.success'),
            ],
        ], 200);
    }
}

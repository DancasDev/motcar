<?php

namespace App\Controllers\V1\My\Profile;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Libraries\JWT\Token;
use App\Models\System\BinnacleModel;

/**
 * Cambio de contraseña del usuario autenticado (autoservicio).
 *
 * Ruta base: v1/my/profile/password
 *
 * - Actualizar: valida contraseña actual, verifica que la nueva contraseña
 *   cumpla complejidad y no sea idéntica a la anterior, actualiza con BCRYPT,
 *   limpia bandera force_to='0', revoca sesiones globales y registra bitácora.
 */
class Password extends BaseController {
    protected $modelName = 'App\Models\Gac\UserModel';

    public function update($id = null): Response {
        $userId = (int) service('authToken')->getPayloadValue('sub');
        if (empty($userId)) {
            return $this->failUnauthorized();
        }

        $input = $this->getInput();
        if ($input instanceof Response) {
            return $input;
        }

        $rules = [
            'password_current' => [
                'label' => 'lang:Auth.user.password',
                'rules' => 'required|string',
            ],
            'password_new' => [
                'label' => 'lang:Auth.user.new_password',
                'rules' => 'required|string|min_length[8]|max_length[30]|regex_match[/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_+\[\]{}|;:,.<>?\-])[A-Za-z\d!@#$%^&*()_+\[\]{}|;:,.<>?\-]+$/]',
            ],
        ];

        if (!$this->validateData($input, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $validated = $this->validator->getValidated();
        $currentPassword = $validated['password_current'];
        $newPassword     = $validated['password_new'];

        $user = $this->model->select('id, password, email, force_to, is_disabled')
            ->where('id', $userId)
            ->where('is_disabled', '0')
            ->where('deleted_at', null)
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($user === null) {
            return $this->fail(lang('My.profile.not_found'), 404, 'RECORD_NOT_FOUND');
        }

        // 1. Validar que la contraseña actual sea correcta
        if (!password_verify($currentPassword, $user['password'])) {
            return $this->failValidationErrors([
                'password_current' => lang('My.password.current_invalid'),
            ]);
        }

        // 2. Validar que la nueva contraseña no sea igual a la actual
        if (password_verify($newPassword, $user['password'])) {
            return $this->failValidationErrors([
                'password_new' => lang('My.password.same_as_current'),
            ]);
        }

        // 3. Actualizar contraseña (UserModel aplica hash, limpia force_to '0' y resetea failed_attempts)
        $this->model->update($userId, [
            'password' => $newPassword,
        ]);

        // Revocar sesiones activas previas de este usuario
        Token::revokeGlobal($userId, '1');

        // 4. Bitácora de auditoría con contraseña enmascarada
        $binnacleId = (new BinnacleModel())->log(
            'user',
            $userId,
            'password',
            (string) $userId,
            'update',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            [
                'type'     => 'manual',
                'password' => str_repeat('*', strlen($newPassword)),
                'agent'    => $this->request->getUserAgent()->getAgentString(),
            ],
            service('authToken')->getPayloadValue('jti')
        );

        // 5. Enviar notificación de cambio de contraseña
        service('notification')->send(
            $userId,
            'password_updated',
            [
                'binnacle_id' => $binnacleId ?: '',
                'ip'          => $this->request->getIPAddress(),
            ],
            true
        );

        return $this->respond([
            'status'   => 200,
            'data'     => [
                'id'            => $userId,
                'affected'      => true,
                'require_login' => true,
            ],
            'messages' => [
                'success' => lang('My.password.updated_successfully'),
            ],
        ], 200);
    }
}
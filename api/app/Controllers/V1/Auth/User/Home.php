<?php

namespace App\Controllers\V1\Auth\User;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Models\System\BinnacleModel;
use App\Models\Global\BranchModel;
use App\Libraries\JWT\AudGenerator;
use App\Libraries\JWT\Token;
use App\Controllers\Traits\TimingAttack;

/**
 * Autenticación y sesión de usuarios humanos (gac_user).
 *
 * Ruta base: v1/auth/user
 *
 * - Crear: inicio de sesión manual con credenciales (username/password),
 *   mitigación de timing attack, emisión de JWT y registro en bitácora.
 * - Leer: perfil activo, roles asignados, matriz GAC calculada y mapa de sucursales.
 * - Actualizar: renovación (refresh) de token JWT tras umbral de actualización.
 * - Eliminar: cierre de sesión activo o revocación global de todas las sesiones.
 * - Firma: registro de URL de firma digital para el usuario autenticado.
 */
class Home extends BaseController {
    use TimingAttack;

    protected $modelName = 'App\Models\Gac\UserModel';
    protected $format = null;

    public function create(): Response {
        $validator = service('validation');
        $authToken = service('authToken');
        $binnacleModel = new BinnacleModel();

        $authConfig = config('Auth');
        $jwtConfig  = config('Jwt');
        $failedAttemptLimit = $authConfig->failedAttemptLimit;
        $failedAttemptLapse = $authConfig->failedAttemptLapse;
        $tokenDuration = $jwtConfig->userTokenDuration;
        $isCaseSensitive = $authConfig->userCaseSensitive;

        if (!$failedAttemptLimit || !$failedAttemptLapse || !$tokenDuration) {
            log_message('critical', '[CONFIG] Variables de configuración auth/jwt no definidas.');
            return $this->fail(lang('Auth.env_variables_not_defined'), 500, 'AUTH_VARIABLES_NOT_DEFINED');
        }

        // Entrada
        $input = $this->getInput();
        if ($input instanceof Response) return $input;

        // Validar existencia de keys
        $validator->setRules([
            'username' => ['rules' => 'required|string'],
            'password' => ['rules' => 'required|string'],
        ]);
        if (!$validator->run($input)) {
            return $this->fail($validator->getErrors(), 400, 'FAIL_VALIDATION');
        }
        $input = $validator->getValidated();

        // ─── Timing attack: respuestas constantes desde aquí ───
        $this->startTimingAttack(200, 20);
        try {

            // Validar integridad de valores
            $validator->reset();
            $validator->setRules([
                'username' => ['rules' => 'min_length[5]|max_length[120]'],
                'password' => ['rules' => 'min_length[8]|max_length[30]'],
            ]);
            if (!$validator->run($input)) {
                return $this->getDefaultResponse();
            }

            // Buscar usuario
            $userData = $this->model->validateIntegrity('username', $input['username'], true);
            if (empty($userData)) {
                return $this->getDefaultResponse();
            }

            if ($isCaseSensitive && $userData['username'] !== $input['username']) {
                return $this->getDefaultResponse();
            }

            // Límite de intentos
            if ($userData['failed_attempt_count'] >= $failedAttemptLimit) {
                return $this->fail(
                    lang('Auth.user.faulted_attempts_exceeded', [$failedAttemptLimit]),
                    403,
                    'AUTH_USER_ATTEMPTS_EXCEEDED'
                );
            }

            // Validar contraseña
            if (!password_verify($input['password'], $userData['password'])) {
                $lapse = empty($userData['failed_attempt_date'])
                    ? 0
                    : time() - strtotime($userData['failed_attempt_date']);

                $userData['failed_attempt_count'] = $lapse >= $failedAttemptLapse
                    ? 1
                    : $userData['failed_attempt_count'] + 1;

                $this->model->skipValidation(true)->protect(false)
                    ->set('failed_attempt_count', $userData['failed_attempt_count'])
                    ->set('failed_attempt_date', date('Y-m-d H:i:s'))
                    ->where('id', $userData['user_id'])
                    ->limit(1)
                    ->update();

                $binnacleModel->log(
                    'user',
                    $userData['user_id'],
                    'login',
                    null,
                    'failed',
                    date('Y-m-d H:i:s'),
                    $this->request->getIPAddress(),
                    [
                        'type' => 'manual',
                        'attempt_count' => $userData['failed_attempt_count'] . '/' . $failedAttemptLimit,
                        'agent' => $this->request->getUserAgent()->getAgentString(),
                    ]
                );

                $remaining = $failedAttemptLimit - $userData['failed_attempt_count'];

                if ($remaining <= 0) {
                    Token::revokeGlobal($userData['user_id'], '1');
                    service('notification')->send(
                        (int) $userData['user_id'],
                        'user_locked',
                        [
                            'ip' => $this->request->getIPAddress(),
                        ],
                        true
                    );
                }

                return ($remaining > 0) ? $this->getDefaultResponse() : $this->fail(
                    lang('Auth.user.faulted_attempts_exceeded', [$failedAttemptLimit]),
                    403,
                    'AUTH_USER_ATTEMPTS_EXCEEDED'
                );
            }

            // Generar token
            $audValue = AudGenerator::userAud();

            $authToken->setExpirationTime($tokenDuration);
            $authToken->setPayloadValue([
                'mec'  => 'manual',
                'subt' => '1',
                'sub'  => $userData['user_id'],
                'fct'  => $userData['force_to'],
                'aud'  => $audValue,
                'rat'  => time() + $jwtConfig->userTokenUpdateInterval,
            ]);

            $token = $authToken->encode();

            // Post-login
            $this->model->skipValidation(true)->protect(false)
                ->set('failed_attempt_count', 0)
                ->set('failed_attempt_date', null)
                ->set('last_login_date', date('Y-m-d H:i:s'))
                ->set('last_login_ip', $this->request->getIPAddress())
                ->where('id', $userData['user_id'])
                ->limit(1)
                ->update();

            $binnacleModel->log(
                'user',
                $userData['user_id'],
                'login',
                $authToken->getPayloadValue('jti'),
                'create',
                date('Y-m-d H:i:s'),
                $this->request->getIPAddress(),
                [
                    'type' => 'manual',
                    'agent' => $this->request->getUserAgent()->getAgentString(),
                ]
            );

            // Respuesta
            $shortName = strtok($userData['first_name'] ?? '', ' ');

            return $this->respond([
                'status' => 201,
                'data' => [
                    'token'      => $token,
                    'expires_at' => $authToken->getPayloadValue('exp'),
                    'refresh_at' => $authToken->getPayloadValue('rat'),
                    'force_to'   => $userData['force_to'],
                ],
                'messages' => [
                    'success' => lang('Auth.user.welcome', [
                        'sex' => $userData['sex'] ?? '0',
                        'name' => $shortName,
                    ]),
                ],
            ], 201);

        } finally {
            $this->endTimingAttack();
        }
    }

    public function index(): Response {
        $response = ['data' => []];
        $authToken = service('authToken');
        $sub = $authToken->getPayloadValue('sub');

        $all = 'true';
        $entities = ['user', 'role', 'permission', 'branch'];
        foreach ($entities as $entity) {
            if ($this->request->getGet($entity) === 'true') {
                $all = null;
                break;
            }
        }

        $isRequested = $all ?? $this->request->getGet('user');
        if ($isRequested === 'true') {
            $user = $this->model->builder('gac_user AS a')
                ->select('
                    a.id, a.username, a.first_name, a.last_name, a.sex, a.email, a.photo_url, a.signature_url,
                    c.code AS country_code,
                    c.name AS country_name,
                    tz.code AS time_zone_code,
                    l.code AS language_code,
                    l.name AS language_name
                ')
                ->join('glb_country AS c', 'a.country_id = c.id', 'left')
                ->join('glb_time_zone AS tz', 'a.time_zone_id = tz.id', 'left')
                ->join('glb_language AS l', 'a.language_id = l.id', 'left')
                ->where('a.id', $sub)
                ->limit(1)
                ->get()
                ->getResultArray()[0] ?? [];

            $response['data']['user'] = $user;
        }

        $isRequested = $all ?? $this->request->getGet('role');
        if ($isRequested === 'true') {
            $roles = $this->model->builder('gac_role_entity AS a')
                ->select('b.id, b.code, b.name')
                ->join('gac_role AS b', 'a.role_id = b.id', 'inner')
                ->where('a.entity_id', $sub)
                ->where('a.entity_type', '1')
                ->where('a.is_disabled', '0')
                ->where('a.deleted_at', null)
                ->where('b.is_disabled', '0')
                ->where('b.deleted_at', null)
                ->orderBy('a.priority', 'ASC')
                ->get()
                ->getResultArray();

            $response['data']['role'] = $roles;
        }

        $isRequested = $all ?? $this->request->getGet('permission');
        if ($isRequested === 'true') {
            $authAccess = service('authAccess');
            $authAccess->setEntity('user', $sub);
            $response['data']['permission'] = $authAccess->exportPermissions();
        }

        $isRequested = $all ?? $this->request->getGet('branch');
        if ($isRequested === 'true') {
            $response['data']['branch'] = (new BranchModel())->getMapByCache();
        }

        return $this->respond($response, 200);
    }

    public function update($id = null): Response {
        $authToken = service('authToken');
        $binnacleModel = new BinnacleModel();
        $jwtConfig = config('Jwt');

        $fctToken = $authToken->getPayloadValue('fct') ?? [];
        if (!is_array($fctToken)) {
            $fctToken = !empty($fctToken) ? explode(',', (string) $fctToken) : [];
        }

        $rat = (int) ($authToken->getPayloadValue('rat') ?? 0);
        if ($rat === 0) {
            $rat = ((int) $authToken->getPayloadValue('iat')) + $jwtConfig->userTokenUpdateInterval;
        }
        $tooEarly = time() < $rat;

        // Acciones cuya resolución permite bypass del umbral rat
        // ponytail: array literal; si crece, moverlo a Config/Jwt.php
        $allowedBypassActions = ['1']; // '1' = email_verification

        if ($tooEarly) {
            if (empty($fctToken)) {
                // Sin fct y muy temprano: rechazo puro en memoria (cero SQL)
                $x = round(($rat - time()) / 60, 2);
                return $this->fail(
                    lang('Auth.token_update_invalid', [$x]),
                    400,
                    'AUTH_TOKEN_UPDATE_INVALID'
                );
            }

            // Tiene fct: consultar BD solo para verificar si resolvió alguna acción permitida
            $fctDB = $this->model->builder('gac_user')
                ->select('force_to')
                ->where('id', $authToken->getPayloadValue('sub'))
                ->limit(1)
                ->get()
                ->getRow()->force_to ?? null;
            $fctDB = !empty($fctDB) ? explode(',', $fctDB) : [];

            $resolved = array_intersect($allowedBypassActions, array_diff($fctToken, $fctDB));
            if (empty($resolved)) {
                $x = round(($rat - time()) / 60, 2);
                return $this->fail(
                    lang('Auth.token_update_invalid', [$x]),
                    400,
                    'AUTH_TOKEN_UPDATE_INVALID'
                );
            }
            // Resolvió una acción permitida: bypass exitoso
        } else {
            // Umbral superado: leer fct de BD
            $fctDB = $this->model->builder('gac_user')
                ->select('force_to')
                ->where('id', $authToken->getPayloadValue('sub'))
                ->limit(1)
                ->get()
                ->getRow()->force_to ?? null;
            $fctDB = !empty($fctDB) ? explode(',', $fctDB) : [];
        }

        // Invalidar token actual
        $authToken->revoke();

        // Generar nuevo
        $authToken->setExpirationTime($jwtConfig->userTokenDuration);
        $authToken->setPayloadValue('fct', $fctDB);
        $authToken->setPayloadValue('aud', AudGenerator::userAud());
        $authToken->setPayloadValue('rat', time() + $jwtConfig->userTokenUpdateInterval);

        $token = $authToken->encode(true);

        $binnacleModel->log(
            'user',
            $authToken->getPayloadValue('sub'),
            'login',
            $authToken->getPayloadValue('jti'),
            'update',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            null
        );

        return $this->respond([
            'status' => 200,
            'data' => [
                'token'      => $token,
                'expires_at' => $authToken->getPayloadValue('exp'),
                'refresh_at' => $authToken->getPayloadValue('rat'),
            ],
        ], 200);
    }

    public function delete($id = null): Response {
        $authToken = service('authToken');
        $binnacleModel = new BinnacleModel();

        $sub = $authToken->getPayloadValue('sub');

        $all = $this->request->getGet('all') === 'true';

        if ($all) {
            Token::revokeGlobal($sub, '1');
        } else {
            $authToken->revoke(true);
        }

        $binnacleModel->log(
            'user',
            $sub,
            'login',
            $authToken->getPayloadValue('jti'),
            'delete',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            $all ? ['all_sessions' => true] : null
        );

        // Datos del usuario para mensaje
        $user = $this->model->builder('gac_user')
            ->select('first_name')
            ->where('id', $sub)
            ->limit(1)
            ->get()
            ->getResultArray()[0] ?? ['first_name' => '...'];

        $shortName = strtok($user['first_name'] ?? '', ' ');

        return $this->respond([
            'status' => 200,
            'data'   => [],
            'messages' => [
                'success' => lang('Auth.user.farewell', ['name' => $shortName]),
            ],
        ], 200);
    }

    public function saveSignature(): Response {
        $authToken = service('authToken');
        $sub = $authToken->getPayloadValue('sub');
        if (!$sub) {
            return $this->failUnauthorized();
        }

        $input = $this->getInput();
        if ($input instanceof Response) return $input;

        $signatureUrl = $input['signature_url'] ?? null;
        if (empty($signatureUrl)) {
            return $this->fail('La URL de firma es requerida.', 400, 'SIGNATURE_URL_REQUIRED');
        }

        $this->model->skipValidation(true)->protect(false)
            ->set('signature_url', $signatureUrl)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->where('id', $sub)
            ->limit(1)
            ->update();

        return $this->respond([
            'status' => 200,
            'data' => [
                'signature_url' => $signatureUrl,
            ],
            'messages' => [
                'success' => 'Firma digital registrada exitosamente en tu perfil.',
            ],
        ], 200);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function getDefaultResponse(int $httpStatus = 401): Response {
        return $this->fail(
            lang('Auth.user.combination_not_found'),
            $httpStatus,
            'AUTH_USER_COMBINATION_NOT_FOUND'
        );
    }
}

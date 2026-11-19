<?php

namespace App\Controllers\V1\Auth\Client;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Models\System\BinnacleModel;
use App\Libraries\JWT\AudGenerator;
use App\Libraries\JWT\Token;
use App\Controllers\Traits\TimingAttack;

/**
 * Autenticación y sesión de clientes API M2M (gac_client).
 *
 * Ruta base: v1/auth/client
 *
 * - Crear: inicio de sesión automático con credenciales de cliente (client_id/client_secret),
 *   mitigación de timing attack, emisión de JWT y registro en bitácora.
 * - Leer: información de la aplicación cliente, roles asignados y matriz GAC calculada.
 * - Actualizar: renovación de token JWT tras umbral de actualización.
 * - Eliminar: cierre de sesión y revocación del token activo.
 */
class Home extends BaseController {
    use TimingAttack;

    protected $modelName = 'App\Models\Gac\ClientModel';
    protected $format = null;

    public function create(): Response {
        $validator = service('validation');
        $authToken = service('authToken');
        $binnacleModel = new BinnacleModel();

        $authConfig = config('Auth');
        $jwtConfig  = config('Jwt');
        $failedAttemptLimit = $authConfig->failedAttemptLimit;
        $failedAttemptLapse = $authConfig->failedAttemptLapse;
        $tokenDuration = $jwtConfig->clientTokenDuration;

        if (!$failedAttemptLimit || !$failedAttemptLapse || !$tokenDuration) {
            log_message('critical', '[CONFIG] Variables de configuración auth/jwt no definidas.');
            return $this->fail(lang('Auth.env_variables_not_defined'), 500, 'AUTH_VARIABLES_NOT_DEFINED');
        }

        $input = $this->getInput();
        if ($input instanceof Response) return $input;

        $validator->setRules([
            'client_id'     => ['rules' => 'required|string'],
            'client_secret' => ['rules' => 'required|string'],
        ]);
        if (!$validator->run($input)) {
            return $this->fail($validator->getErrors(), 400, 'FAIL_VALIDATION');
        }
        $input = $validator->getValidated();

        $this->startTimingAttack(200, 20);
        try {

            $validator->reset();
            $validator->setRules([
                'client_id'     => ['rules' => 'min_length[32]|max_length[255]'],
                'client_secret' => ['rules' => 'min_length[32]|max_length[255]'],
            ]);
            if (!$validator->run($input)) {
                return $this->getDefaultResponse();
            }

            $clientData = $this->model->validateIntegrity('client_id', $input['client_id'], true);
            if (empty($clientData)) {
                return $this->getDefaultResponse();
            }

            if ($clientData['failed_attempt_count'] >= $failedAttemptLimit) {
                return $this->fail(
                    lang('Auth.client.faulted_attempts_exceeded', [$failedAttemptLimit]),
                    403,
                    'AUTH_CLIENT_ATTEMPTS_EXCEEDED'
                );
            }

            if (!password_verify($input['client_secret'], $clientData['client_secret'])) {
                $lapse = empty($clientData['failed_attempt_date'])
                    ? 0
                    : time() - strtotime($clientData['failed_attempt_date']);

                $clientData['failed_attempt_count'] = $lapse >= $failedAttemptLapse
                    ? 1
                    : $clientData['failed_attempt_count'] + 1;

                $this->model->skipValidation(true)->protect(false)
                    ->set('failed_attempt_count', $clientData['failed_attempt_count'])
                    ->set('failed_attempt_date', date('Y-m-d H:i:s'))
                    ->where('id', $clientData['id'])
                    ->limit(1)
                    ->update();

                $binnacleModel->log(
                    'client',
                    (int) $clientData['id'],
                    'login',
                    null,
                    'failed',
                    date('Y-m-d H:i:s'),
                    $this->request->getIPAddress(),
                    [
                        'attempt_count' => $clientData['failed_attempt_count'] . '/' . $failedAttemptLimit,
                        'agent'         => $this->request->getUserAgent()->getAgentString(),
                    ]
                );

                $remaining = $failedAttemptLimit - $clientData['failed_attempt_count'];
                if ($remaining <= 0) {
                    Token::revokeGlobal($clientData['id'], '2');
                }

                return ($remaining > 0) ? $this->getDefaultResponse() : $this->fail(
                    lang('Auth.client.faulted_attempts_exceeded', [$failedAttemptLimit]),
                    403,
                    'AUTH_CLIENT_ATTEMPTS_EXCEEDED'
                );
            }

            $audValue = AudGenerator::clientAud();

            $authToken->setExpirationTime($tokenDuration);
            $authToken->setPayloadValue([
                'mec'  => 'manual',
                'subt' => '2',
                'sub'  => (int) $clientData['id'],
                'aud'  => $audValue,
                'rat'  => time() + $jwtConfig->clientTokenUpdateInterval,
            ]);

            $token = $authToken->encode();

            $this->model->skipValidation(true)->protect(false)
                ->set('failed_attempt_count', 0)
                ->set('failed_attempt_date', null)
                ->set('last_login_date', date('Y-m-d H:i:s'))
                ->set('last_login_ip', $this->request->getIPAddress())
                ->where('id', $clientData['id'])
                ->limit(1)
                ->update();

            $binnacleModel->log(
                'client',
                (int) $clientData['id'],
                'login',
                $authToken->getPayloadValue('jti'),
                'create',
                date('Y-m-d H:i:s'),
                $this->request->getIPAddress(),
                [
                    'agent' => $this->request->getUserAgent()->getAgentString(),
                ]
            );

            $clientName = $clientData['name'] ?? '';

            return $this->respond([
                'status' => 201,
                'data'   => [
                    'token'      => $token,
                    'expires_at' => $authToken->getPayloadValue('exp'),
                    'refresh_at' => $authToken->getPayloadValue('rat'),
                ],
                'messages' => [
                    'success' => lang('Auth.client.welcome', ['name' => $clientName]),
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
        $entities = ['client','role', 'permission'];
        foreach ($entities as $entity) {
            if ($this->request->getGet($entity) === 'true') {
                $all = null;
                break;
            }
        }

        $isRequested = $all ?? $this->request->getGet('client');
        if ($isRequested === 'true') {
            $client = $this->model->builder('gac_client')
                ->select('id, name, description')
                ->where('id', $sub)
                ->limit(1)
                ->get()
                ->getResultArray()[0] ?? [];

            $response['data']['client'] = $client;
        }

        $isRequested = $all ?? $this->request->getGet('role');
        if ($isRequested === 'true') {
            $roles = $this->model->builder('gac_role_entity AS a')
                ->select('b.id, b.code, b.name')
                ->join('gac_role AS b', 'a.role_id = b.id', 'inner')
                ->where('a.entity_id', $sub)
                ->where('a.entity_type', '2')
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
            $authAccess->setEntity('client', $sub);
            $response['data']['permission'] = $authAccess->exportPermissions();
        }

        return $this->respond($response, 200);
    }

    public function update($id = null): Response {
        $authToken = service('authToken');
        $binnacleModel = new BinnacleModel();

        $jwtConfig = config('Jwt');
        $rat = (int) ($authToken->getPayloadValue('rat') ?? 0);
        if ($rat === 0) {
            $rat = ((int) $authToken->getPayloadValue('iat')) + $jwtConfig->clientTokenUpdateInterval;
        }

        if (time() < $rat) {
            $x = round(($rat - time()) / 60, 2);
            return $this->fail(
                lang('Auth.token_update_invalid', [$x]),
                400,
                'AUTH_TOKEN_UPDATE_INVALID'
            );
        }

        // Invalidar token actual
        $authToken->revoke();

        $tokenDuration = $jwtConfig->clientTokenDuration;
        $authToken->setExpirationTime($tokenDuration);
        $authToken->setPayloadValue('aud', AudGenerator::clientAud());
        $authToken->setPayloadValue('rat', time() + $jwtConfig->clientTokenUpdateInterval);

        $token = $authToken->encode(true);

        $binnacleModel->log(
            'client',
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
            'data'   => [
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

        // Invalidar sesión completa
        $authToken->revoke(true);

        $binnacleModel->log(
            'client',
            $sub,
            'login',
            $authToken->getPayloadValue('jti'),
            'delete',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            null
        );

        $client = $this->model->builder('gac_client')
            ->select('name')
            ->where('id', $sub)
            ->limit(1)
            ->get()
            ->getResultArray()[0] ?? ['name' => '...'];

        $clientName = $client['name'] ?? '...';

        return $this->respond([
            'status'   => 200,
            'data'     => [],
            'messages' => [
                'success' => lang('Auth.client.farewell', ['name' => $clientName]),
            ],
        ], 200);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function getDefaultResponse(int $httpStatus = 401): Response {
        return $this->fail(
            lang('Auth.client.combination_not_found'),
            $httpStatus,
            'AUTH_CLIENT_COMBINATION_NOT_FOUND'
        );
    }
}

<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Filters\BaseTraits;
use App\Libraries\JWT\AudGenerator;
use App\Libraries\JWT\AudRefusedException;
use App\Models\Global\BranchModel;

class AuthFilter implements FilterInterface {
    use BaseTraits;

    protected array $forceToKeys = [
        '0' => 'password_change',
        '1' => 'email_verification',
    ];

    public function __construct() {
        $this->setting = [
            'only_user'    => false,
            'only_client'  => false,
            'allow_forced' => null,
            'scope'        => null,
        ];
    }

    public function before(RequestInterface $request, $arguments = null) {
        if ($request->is('options')) {
            return null;
        }

        // Estado compartido por petición: reset para no arrastrar el scope de
        // una request anterior en workers persistentes.
        service('context')->reset();

        $this->initSetting($arguments);
        $authToken = service('authToken');

        // Extraer token del header Authorization
        $token = $request->getHeaderLine('Authorization');
        if (empty($token)) {
            return $this->fail(lang('AuthFilter.auth.token_is_required'), 401, 'AUTH_TOKEN_IS_REQUIRED');
        }

        $token = str_replace('Bearer ', '', $token);

        // Decodificar y validar firma
        try {
            $authToken->decode($token);
        } catch (\Throwable $th) {
            return $this->fail(lang('AuthFilter.auth.token_is_invalid'), 401, 'AUTH_TOKEN_IS_INVALID');
        }

        // Verificar expiración
        $exp = $authToken->getPayloadValue('exp');
        if ($exp !== null && time() > $exp) {
            return $this->fail(lang('AuthFilter.auth.token_is_expired'), 401, 'AUTH_TOKEN_IS_EXPIRED');
        }

        // Revocación
        if ($authToken->isRevoked()) {
            return $this->fail(lang('AuthFilter.auth.token_is_disabled'), 401, 'AUTH_TOKEN_IS_DISABLED');
        }

        // Resolver scope compartido (se ejecuta 1 vez en toda la cadena)
        $scopePath = null;
        if ($this->setting['scope'] !== null) {
            $result = $this->resolveScope($this->setting['scope'], $request, $authToken);
            if ($result instanceof ResponseInterface) {
                return $result;
            }
            $scopePath = $result;
        }

        // Validaciones por tipo de entidad
        $subt = $authToken->getPayloadValue('subt');
        $sub  = $authToken->getPayloadValue('sub');

        if ($subt === '1') {
            $result = $this->handleUser($authToken, $sub);
            if ($result === null) {
                service('context')
                    ->set('auth:scopePath', $scopePath ?? '*')
                    ->set('auth:userId', (int) $sub)
                    ->set('auth:jwtPayload', $authToken->getPayload());
            }
            return $result;
        }

        if ($subt === '2') {
            $result = $this->handleClient($authToken, $sub);
            if ($result === null) {
                service('context')
                    ->set('auth:scopePath', $scopePath ?? '*')
                    ->set('auth:clientId', (int) $sub)
                    ->set('auth:jwtPayload', $authToken->getPayload());
            }
            return $result;
        }

        return $this->fail(lang('AuthFilter.auth.token_is_invalid'), 401, 'AUTH_TOKEN_IS_INVALID');
    }

    private function handleUser($authToken, int|string $sub): ResponseInterface|RequestInterface|null {
        $fct = $authToken->getPayloadValue('fct');

        // Auditar token
        try {
            $authToken->audit(AudGenerator::userAud());
        } catch (AudRefusedException $e) {
            return $this->fail(lang('AuthFilter.auth.token_is_refused'), 401, 'AUTH_TOKEN_IS_REFUSED');
        } catch (\Throwable $th) {
            return $this->fail(lang('AuthFilter.auth.token_is_invalid'), 401, 'AUTH_TOKEN_IS_INVALID');
        }

        // Exclusividad
        if ($this->setting['only_client']) {
            return $this->fail(lang('AuthFilter.auth.only_client'), 401, 'AUTH_ONLY_CLIENT');
        }

        $allowForced = $this->setting['allow_forced'];

        // Forzado a (email_verification, password_change, etc.)
        if (!empty($fct)) {
            if ($allowForced === '*') {
                return null;
            }

            $allowedList = !empty($allowForced)
                ? explode(';', $allowForced)
                : [];

            $userActions = array_map(
                fn($k) => $this->forceToKeys[$k] ?? $k,
                $fct
            );

            if (!empty(array_intersect($userActions, $allowedList))) {
                return null;
            }

            $firstLabel = reset($userActions);
            return $this->fail(
                lang('AuthFilter.auth.' . $firstLabel . '_required'),
                401,
                'AUTH_' . strtoupper($firstLabel) . '_REQUIRED'
            );
        }

        return null;
    }

    private function handleClient($authToken, int|string $sub): ResponseInterface|RequestInterface|null {
        // Auditar token
        try {
            $authToken->audit(AudGenerator::clientAud());
        } catch (AudRefusedException $e) {
            return $this->fail(lang('AuthFilter.auth.token_is_refused'), 401, 'AUTH_TOKEN_IS_REFUSED');
        } catch (\Throwable $th) {
            return $this->fail(lang('AuthFilter.auth.token_is_invalid'), 401, 'AUTH_TOKEN_IS_INVALID');
        }

        // Exclusividad
        if ($this->setting['only_user']) {
            return $this->fail(lang('AuthFilter.auth.only_user'), 401, 'AUTH_ONLY_USER');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}

    /**
     * Resuelve el scope_path de la petición a partir del argumento `scope`
     * de la ruta (ej: 'scope=param|branch' o 'scope=uri|3').
     *
     * - scope vacío / 'global' → '*'
     * - source 'uri' | 'param' | 'jwt' → extrae un único id de sucursal y
     *   construye el alcance a partir del mapa completo de sucursales
     *   (`BranchModel::getMapByCache()`, cacheado en `db_brn_map`).
     * - Sucursal inexistente o no disponible (deshabilitada / heredada) → error.
     */
    private function resolveScope(?string $scope, RequestInterface $request, $authToken): string|ResponseInterface {
        if (empty($scope) || $scope === 'global') {
            return '*';
        }

        [$source, $keys] = explode('|', $scope, 2);
        $keys = array_map('trim', explode(';', $keys));
        $parts = [];

        if ($source === 'uri') {
            $total = $request->getUri()->getTotalSegments();
            foreach ($keys as $key) {
                $key = (int) $key;
                if ($key < 1 || $key > $total) continue;
                $parts[] = $request->getUri()->getSegment($key);
            }
        } elseif ($source === 'param') {
            foreach ($keys as $key) {
                $value = $request->getGet($key);
                if ($value !== null && $value !== '') {
                    $parts[] = $value;
                }
            }
        } elseif ($source === 'jwt') {
            foreach ($keys as $key) {
                $value = $authToken->getPayloadValue($key);
                if ($value !== null && $value !== '') {
                    $parts[] = $value;
                }
            }
        }

        if (empty($parts)) {
            return '*';
        }

        $branchId = (int) $parts[0];
        $branchMap = (new BranchModel())->getMapByCache();

        if (!isset($branchMap[$branchId]) ||
            $branchMap[$branchId]['is_disabled'] ||
            $branchMap[$branchId]['deleted_at'] !== null ||
            $branchMap[$branchId]['is_inherited_disabled']) {
            return $this->fail(
                lang('AuthFilter.auth.branch_not_available', [$branchId]),
                401,
                'AUTH_BRANCH_NOT_AVAILABLE'
            );
        }

        return $branchMap[$branchId]['scope'];
    }
}

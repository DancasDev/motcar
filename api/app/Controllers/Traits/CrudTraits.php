<?php

namespace App\Controllers\Traits;

use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use CodeIgniter\Validation\Validation;
use DancasDev\DQB\DQB;
use DancasDev\DQB\Schema;
use DancasDev\DQB\Exceptions\SchemaException;
use DancasDev\DQB\Exceptions\SchemaTableConfigBuildException;
use DancasDev\DQB\Exceptions\SchemaFieldConfigBuildException;
use DancasDev\DQB\Exceptions\FieldsProcessorException;
use DancasDev\DQB\Exceptions\FiltersProcessorException;
use DancasDev\DQB\Exceptions\GroupByProcessorException;
use DancasDev\DQB\Exceptions\HavingProcessorException;
use DancasDev\DQB\Exceptions\OrderProcessorException;
use DancasDev\DQB\Exceptions\PaginationProcessorException;

/**
 * Este trait proporciona métodos base para realizar operaciones CRUD en un modelo de base de datos.
 * Esta pensada para ser utilizada en controladores que necesiten realizar operaciones CRUD de manera estandarizada.
 */
trait CrudTraits {
    // protected $schemaName = null; // No descomentar, para evitar error con la declaración
    protected $schema = null;

    use HookConfigTraits;
    use BinnacleTraits;
    
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger){
        parent::initController($request, $response, $logger);
        $this ->setSchema($this ->schemaName ?? null);
    }

    private function __registerHookConfig_crud(): void {
        $this->registerCallbacks(__TRAIT__, [
            'before',
            'setRules',
            'itemSetValues',
            'itemSetRules',
            'itemAfterValidation',
            'itemSetBinnacleData',
            'itemSuccess',
            'after',
            'read:params',
            'read:schema',
            'read:fieldsCalculated',
        ]);

        $this->registerConfig(__TRAIT__, [
            'limit'                 => ['types' => ['integer'], 'default' => 100],
            'messageSuccess'        => ['types' => ['string'], 'default' => null],
            'messageSuccessTrash'   => ['types' => ['string'], 'default' => null],
            'inTrash'               => ['types' => ['boolean'], 'default' => null],
            'read:limit'            => ['types' => ['integer'], 'default' => 255],
            'read:default'          => ['types' => ['integer'], 'default' => 25],
            'read:groupByDisabled'  => ['types' => ['boolean'], 'default' => true],
            'read:accessLevel'      => ['types' => ['integer'], 'default' => null],
            'read:fields'           => ['types' => ['string'], 'default' => null],
            'read:filters'          => ['types' => ['string','array'], 'default' => null],
            'read:groupBy'          => ['types' => ['string','array'], 'default' => null],
            'read:having'           => ['types' => ['string','array'], 'default' => null],
            'read:order'            => ['types' => ['string','array'], 'default' => null],
            'read:page'             => ['types' => ['integer'], 'default' => null],
            'read:itemsPerPage'     => ['types' => ['integer'], 'default' => null],
            'read:filtersDefault'   => ['types' => ['array'], 'default' => null],
            'read:havingDefault'    => ['types' => ['array'], 'default' => null],
            'read:fieldsCalculated' => ['types' => ['array'], 'default' => null],
            'create:isBatch'        => ['types' => ['boolean', 'NULL'], 'default' => null],
        ]);
    }

    /**
     * Base para crear registros.
     * Soporta inserción simple y por lotes (batch).
     *
     * @param ?array $input - Entrada a procesar
     *
     * @return Response
     */
    public function baseCreate(?array $input = null) : Response {
        $config = $this->baseInit('create', null, $input);
        if ($config instanceof Response) return $config;

        $response = ['status' => null,'data' => [], 'limit' => $config['limit'], 'affected' => 0];

        $addCreatedAt = $config['useTimestamps'] && $this ->model ->createdField != '';

        # Procesamiento
        $statusList = [];
        $this->model->setUseTimestamps(false);
        $this->model->db->transStart();
        foreach ($config['input'] as $index => $item) {
            try {
                // Validación
                $validItem = $this ->baseItemValidation($config, $index, $item, $response, $statusList);
                if ($validItem instanceof Response) {
                    $this->model->db->transRollback();
                    return $validItem;
                }
                elseif ($validItem === false) {
                    continue;
                }

                // Ejecución
                if ($addCreatedAt) {
                    $this ->model ->set($this ->model ->createdField, $config['timestamp']);
                }

                $insertId = $this->model->insert($validItem);

                // Resultado
                if ($insertId) {
                    $validItem[$this ->model ->primaryKey] = $insertId;

                    $result = $this->baseItemSuccess($config, $index, $validItem, $response, $statusList);
                    if ($result instanceof Response) {
                        $this->model->db->transRollback();
                        return $result;
                    }
                }
                else {
                    throw new \Exception(lang('CrudTraits.create.error'));
                }
            }
            catch (\Throwable $e) {
                $this->model->db->transRollback();
                return $this ->baseItemError($config, $index, $e);
            }
        }

        $result = $this->baseEnding($config, $response, $statusList);
        if ($result instanceof Response) {
            $this->model->db->transRollback();
            return $result;
        }

        $this->model->db->transComplete();
        if ($this->model->db->transStatus() === false) {
            return $this ->fail(lang('CrudTraits.create.error'), 500, 'CREATE_TRANSACTION_ERROR');
        }

        return $this ->respond($result['data'], $result['status']);
    }

    /**
     * Base para leer registros
     *
     * @param ?int $id - (Opcional) ID del registro a actualizar
     * @param bool $returnArray - Retornar array
     *
     * @todo
     *
     * @return Response|array
     */
    public function baseRead($id = null, bool $returnArray = false) : Response|array {
        $authPermission = service('context')->get('auth:permission');

        if ($this ->schema === null) {
            return $this ->fail(lang('CrudTraits.schema.is_undefined'), 500, 'SCHEMA_IS_UNDEFINED');
        }

        if (!isset($this ->model)) {
            return $this ->fail(lang('CrudTraits.model.is_undefined'), 500, 'MODEL_IS_UNDEFINED');
        }

        # Configuración
        $config = [];
        $config['action'] = 'read';
        $config['onlyOne'] = is_numeric($id);
        $config['groupByDisabled'] = $this ->getConfig('read:groupByDisabled');
        $config['limit'] = $this->getConfig('read:limit');
        $authLevel = $this->getConfig('read:accessLevel');
        if ($authLevel === null) {
            $authLevel = $authPermission?->getLevel() ?? 0;
        }
        $config['accessLevel'] = $authLevel;

        $authInTrash = $this->getConfig('inTrash');
        if ($authInTrash === null) {
            $authInTrash = service('context')->get('request:inTrash', false);
        }
        $config['inTrash'] = $authInTrash;


        # Parametros de consulta
        $params = [];
        $params['fields'] = $this ->getConfig('read:fields') ?? $this ->request ->getGet('fields') ?? '*';
        $params['filters'] = $config['onlyOne'] ? [$this ->model ->primaryKey, $id] : ($this ->getConfig('read:filters') ?? $this ->request ->getGet('filters') ?? null);
        $params['groupBy'] = ($config['onlyOne'] || $config['groupByDisabled']) ? null : ($this ->getConfig('read:groupBy') ?? $this ->request ->getGet('groupBy') ?? null);
        $params['having'] = ($config['onlyOne'] || $config['groupByDisabled']) ? null : ($this ->getConfig('read:having') ?? $this ->request ->getGet('having') ?? null);
        $params['order'] = $config['onlyOne'] ? null : ($this ->getConfig('read:order') ?? $this ->request ->getGet('order') ?? null);
        $params['page'] =  $config['onlyOne'] ? 1 : ($this ->getConfig('read:page') ?? $this ->request ->getGet('page') ?? null);
        $params['itemsPerPage'] =  $config['onlyOne'] ? 1 : ($this ->getConfig('read:itemsPerPage') ?? $this ->request ->getGet('itemsPerPage') ?? $this ->getConfig('read:default'));
        $params['filtersDefault'] = $this ->getConfig('read:filtersDefault') ?? null;
        $params['havingDefault'] = $this ->getConfig('read:havingDefault') ?? null;

        // Formatear
        $fromJson = ['filters','having','order'];
        foreach ($fromJson as $field) {
            if (is_string($params[$field])) {
                $params[$field] = json_decode($params[$field], true);
            }
        }

        if (is_string($params['groupBy'])) {
            $params['groupBy'] = explode(',', $params['groupBy']);
        }

        // Hook: Redefinir parametros de consulta
        if ($this ->hasCallback('read:params')) {
            $result = $this->runCallback('read:params', $params, $config);
            if ($result instanceof Response) {
                return $result;
            }
            elseif (is_array($result)) {
                $params = $result;
            }
        }

        if ($params['itemsPerPage'] > $config['limit']) {
            return $this ->fail(lang('CrudTraits.input.limit_exceeded', [$config['limit']]), 400, 'READ_LIMIT_EXCEEDED');
        }

        if ($this ->model ->useSoftDeletes && !empty($this ->model ->deletedField)) {
            $result = $config['inTrash'] ? [$this ->model ->deletedField, null, '!='] : [$this ->model ->deletedField, null];
            $params['filtersDefault'] = empty($params['filtersDefault']) ? $result : array_merge([$params['filtersDefault']], [$result]);
        }

        // Campos calculados
        $fieldsToRemove = [];
        $fieldsCalculatedToAdd = [];
        if ($this ->hasConfig('read:fieldsCalculated')) {
            $list = $this ->getConfig('read:fieldsCalculated');
            if ($params['fields'] == '*') {
                foreach ($list as $fieldCalculated => $dependency) {
                    $fieldsCalculatedToAdd[] = $fieldCalculated;
                }
            }
            // validar posibles campos de dependencias
            else {
                $params['fields'] = explode(',', $params['fields']);
                foreach ($list as $fieldCalculated => $dependency) {
                    if (!in_array($fieldCalculated, $params['fields'])) {
                        continue;
                    }
                    $fieldsCalculatedToAdd[] = $fieldCalculated;

                    // dependecia
                    if (empty($dependency)) {
                        continue;
                    }

                    $dependency = !is_array($dependency) ? [$dependency] : $dependency;
                    foreach ($dependency as $dependencyField) {
                        $dependencyField = (string) $dependencyField;
                        if (!in_array($dependencyField, $params['fields'])) {
                            $params['fields'][] = $dependencyField;
                            $fieldsToRemove[] = $dependencyField;
                        }
                    }
                }

                $params['fields'] = array_diff($params['fields'], array_keys($list));
                $params['fields'] = implode(',', $params['fields']);
            }
        }

        # Esquema
        $this ->schema ->setAccessLevel($config['accessLevel']);
        // Hook: Esquema
        if ($this ->hasCallback('read:schema')) {
            $result = $this->runCallback('read:schema', $this ->schema, $config);
            if ($result instanceof Response) {
                return $result;
            }
        }

        $isDeveloping = false;
        try { $isDeveloping = $authPermission->moduleIsDeveloping(); } catch (\Throwable) {}
        if ($isDeveloping) {
            // Testear configuración
            try {
                $this ->schema->validateConfig();
            } catch (SchemaTableConfigBuildException $e) {
                return $this ->fail($e->getMessage(), 500, 'SCHEMA_TABLE_CONFIG_ERROR');
            } catch (SchemaFieldConfigBuildException $e) {
                return $this ->fail($e->getMessage(), 500, 'SCHEMA_FIELD_CONFIG_ERROR');
            } catch (\Throwable $th) {
                return $this ->fail($th->getMessage(), 500, 'SCHEMA_UNEXPECTED_ERROR');
            }
        }

        # Consulta
        $dqb = new DQB($this ->schema);
        // Preparar
        try {
            $dqb->prepare(
                fields: $params['fields'],
                filters: $params['filters'],
                filtersDefault: $params['filtersDefault'],
                groupBy: $params['groupBy'],
                having: $params['having'],
                havingDefault: $params['havingDefault'],
                order: $params['order'],
                page: $params['page'],
                itemsPerPage: $params['itemsPerPage'],
            );
        } catch (FieldsProcessorException $e) {
            return $this ->fail($e->getMessage(), 400, 'READ_FIELDS_ERROR');
        } catch (FiltersProcessorException $e) {
            return $this ->fail($e->getMessage(), 400, 'READ_FILTERS_ERROR');
        } catch (GroupByProcessorException $e) {
            return $this ->fail($e->getMessage(), 400, 'READ_GROUP_BY_ERROR');
        } catch (HavingProcessorException $e) {
            return $this ->fail($e->getMessage(), 400, 'READ_HAVING_ERROR');
        } catch (OrderProcessorException $e) {
            return $this ->fail($e->getMessage(), 400, 'READ_ORDER_ERROR');
        } catch (PaginationProcessorException $e) {
            return $this ->fail($e->getMessage(), 400, 'READ_PAGINATION_ERROR');
        } catch (\Throwable $th) {
            return $this ->fail($th->getMessage(), 500, 'DQB_ERROR');
        }

        // Ejecutar acción
        $response = ['data' => [], 'count' => 0, 'limit' => $config['limit']];
        try {
            if ($config['onlyOne']) {
                $sqlData = $dqb ->getSql();
                $response['data'] = $this ->model ->db ->query($sqlData['query'],  $sqlData['params']) ->getResultArray() ?? null;
                $response['count'] = 1;
                if (empty($response['data'])) {
                    return $this ->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
                }
            }
            else {
                if (!empty($params['groupBy'])) {
                    $innerSql = $dqb->getSql(['SELECT', 'FROM', 'JOIN', 'WHERE', 'GROUP BY', 'HAVING'], ['SELECT' => '1']);
                    $response['count'] = (int) $this->model->db->query("SELECT COUNT(*) AS n FROM ({$innerSql['query']}) AS t", $innerSql['params'])->getRow()->n ?? 0;
                }
                elseif ($dqb ->hasAggregationFields()) {
                    if ($config['groupByDisabled']) {
                        return $this ->fail(lang('CrudTraits.read.fields_by_aggregation_disabled'), 400, 'READ_FIELDS_BY_AGGREGATION_DISABLED');
                    }
                    $response['count'] = 1;
                }
                else {
                    $result = $dqb ->getSql(['SELECT','FROM','JOIN','WHERE'], ['SELECT' => 'COUNT(*) AS n']);
                    $response['count'] = (int) $this ->model ->db ->query($result['query'],  $result['params']) ->getRow()->n ?? 0;
                }

                if ($response['count'] > 0) {
                    $result = $dqb ->getSql();
                    $response['data'] = $this ->model ->db ->query($result['query'],  $result['params']) ->getResultArray() ?? [];
                }
            }
        } catch (\Throwable $th) {
            return $this ->fail($th->getMessage(), 500, 'DQB_EXECUTE_ERROR');
        }

        // Hook: Campos calculados
        if ($this ->hasCallback('read:fieldsCalculated') && !empty($fieldsCalculatedToAdd) && $response['count'] > 0) {
            $removeFieldsFunction = function(array $item) use ($fieldsToRemove) {
                foreach ($fieldsToRemove as $field) {
                    unset($item[$field]);
                }
                return $item;
            };

            $result = $this->runCallback('read:fieldsCalculated', $response['data'], $fieldsCalculatedToAdd, $removeFieldsFunction);
            if ($result instanceof Response) {
                return $result;
            }
            elseif (is_array($result)) {
                $response['data'] = $result;
            }
        }

        # Respuesta
        $response = $this ->formatResponse($config, $response, [200 => true]);
        $response['data']['in_trash'] = $config['inTrash'];

        return $returnArray ? $response['data'] : $this ->respond($response['data'], $response['status']);
    }

    /**
     * Base para actualizar registros
     *
     * @param ?int $id - (Opcional) ID del registro a actualizar
     * @param ?array $input - Entrada a procesar
     *
     * @return Response
     */
    public function baseUpdate($id = null, ?array $input = null) : Response {
        $config = $this->baseInit('update', $id, $input);
        if ($config instanceof Response) return $config;

        $response = ['status' => null,'data' => [], 'limit' => $config['limit'], 'affected' => 0];

        # Procesamiento
        $statusList = [];
        $itemsUpdated = [];
        $this->model->setUseTimestamps(false);
        $this->model->db->transStart();
        foreach ($config['input'] as $index => $item) {
            try {
                // Validación
                $validItem = $this ->baseItemValidation($config, $index, $item, $response, $statusList);
                if ($validItem instanceof Response) {
                    $this->model->db->transRollback();
                    return $validItem;
                }
                elseif ($validItem === false) {
                    continue;
                }

                $itemId = $validItem[$this ->model ->primaryKey];
                if (count($validItem) == 1) {
                    $response['data'][$index] = $this->formatItemError(400, 'UPDATE_EMPTY_ITEM', lang('CrudTraits.update.empty_item'));
                    $statusList[400] = true;
                    continue;
                }

                // Ejecución
                if (isset($this ->model ->deletedField)) {
                    $this ->model ->where($this->model->deletedField, null);
                }
                $this ->model ->set($validItem)
                    ->limit(1)
                    ->update($itemId);

                // Resultado
                if ($this->model->db->affectedRows() >= 1) {
                    $result = $this->baseItemSuccess($config, $index, $validItem, $response, $statusList);
                    if ($result instanceof Response) {
                        $this->model->db->transRollback();
                        return $result;
                    }

                    $itemsUpdated[] = $itemId;
                }
                else {
                    $statusList[200] = true;
                    $response['data'][$index] = $this->formatItemSuccess(
                        200,
                        [$this ->model ->primaryKey => $itemId, 'affected' => false],
                        $config['onlyOne'] ? lang('CrudTraits.update.no_changes') : null);
                }
            }
            catch (\Throwable $e) {
                $this->model->db->transRollback();
                return $this ->baseItemError($config, $index, $e);
            }
        }

        // Actualizar timestamp
        try {
            if ($response['affected'] > 0 && $config['useTimestamps'] && $this ->model ->updatedField !== '') {
                $result = $this ->model ->set($this ->model ->updatedField,  $config['timestamp'])
                    ->whereIn($this ->model ->primaryKey, $itemsUpdated)
                    ->limit($response['affected'])
                    ->update();
                if (!$result) {
                    throw new \Exception(lang('CrudTraits.update.error'));
                }
            }
        } catch (\Throwable $e) {
            $this->model->db->transRollback();
            log_message('error', "[UPDATE_TIMESTAMP_ERROR] {exception}\nData:\t{data}\nStack trace:\n{stackTrace}", [
                'exception' => $e,
                'stackTrace' => $e->getTraceAsString(),
                'data' => json_encode($itemsUpdated)
            ]);
            return $this ->fail($e ->getMessage(), 500, 'UPDATE_TIMESTAMP_ERROR');
        }

        $result = $this->baseEnding($config, $response, $statusList);
        if ($result instanceof Response) {
            $this->model->db->transRollback();
            return $result;
        }

        $this->model->db->transComplete();
        if ($this->model->db->transStatus() === false) {
            return $this ->fail(lang('CrudTraits.update.error'), 500, 'UPDATE_TRANSACTION_ERROR');
        }

        return $this ->respond($result['data'], $result['status']);
    }

    /**
     * Base para eliminar registros
     *
     * @param ?int $id - (Opcional) ID del registro a eliminar
     * @param ?array $input - Entrada a procesar
     *
     * @return Response
     */
    public function baseDelete($id = null, ?array $input = null) : Response {
        $config = $this->baseInit('delete', $id, $input);
        if ($config instanceof Response) return $config;

        $response = ['status' => null,'data' => [], 'limit' => $config['limit'], 'affected' => 0];

        $config['inTrash'] = true; // Borrado fisico
        if ($config['useTimestamps']) {
            $config['inTrash'] = $this ->getConfig('inTrash') ?? service('context')->get('request:inTrash', true);
        }

        if (!$config['inTrash'] && empty($this ->model ->deletedField)) {
            return $this ->fail(lang('CrudTraits.model.delete_field_is_required'), 500, 'MODEL_DELETED_FIELD_IS_UNDEFINED');
        }

        # Procesamiento
        $statusList = [];
        $this->model->db->transStart();
        foreach ($config['input'] as $index => $item) {
            try {
                // Validación
                $validItem = $this ->baseItemValidation($config, $index, $item, $response, $statusList);
                if ($validItem instanceof Response) {
                    $this->model->db->transRollback();
                    return $validItem;
                }
                elseif ($validItem === false) {
                    continue;
                }

                // Ejecución
                $itemId = $validItem[$this ->model ->primaryKey];
                $this ->model ->limit(1);
                if ($config['inTrash']) {
                    $this ->model ->delete($itemId, true);
                }
                else {
                    $this ->model ->set($validItem) ->set($this->model->deletedField, $config['timestamp'])
                        ->where($this->model->deletedField, null)
                        ->update($itemId);
                }

                // Resultado
                if ($this->model->db->affectedRows() >= 1) {
                    $result = $this->baseItemSuccess($config, $index, $validItem, $response, $statusList);
                    if ($result instanceof Response) {
                        $this->model->db->transRollback();
                        return $result;
                    }
                }
                else {
                    $statusList[200] = true;
                    $response['data'][$index] = $this->formatItemSuccess(200, [$this ->model ->primaryKey => $itemId, 'affected' => false], lang('CrudTraits.update.no_changes'));
                }
            }
            catch (\Throwable $e) {
                $this->model->db->transRollback();
                return $this ->baseItemError($config, $index, $e);
            }
        }

        $result = $this->baseEnding($config, $response, $statusList);
        if ($result instanceof Response) {
            $this->model->db->transRollback();
            return $result;
        }

        $this->model->db->transComplete();
        if ($this->model->db->transStatus() === false) {
            return $this ->fail(lang('CrudTraits.delete.error'), 500, 'DELETE_TRANSACTION_ERROR');
        }

        $result['data']['in_trash'] = $config['inTrash'];

        return $this ->respond($result['data'], $result['status']);
    }

    /**
     * Base para restaurar registros
     *
     * @param ?int $id - (Opcional) ID del registro a restaurar
     * @param ?array $input - Entrada a procesar
     *
     * @return Response
     */
    public function baseRestore($id = null, ?array $input = null) : Response {
        $config = $this->baseInit('restore', $id, $input);
        if ($config instanceof Response) return $config;

        $response = ['status' => null,'data' => [], 'limit' => $config['limit'], 'affected' => 0];

        if (empty($this ->model ->deletedField)) {
            return $this ->fail(lang('CrudTraits.model.delete_field_is_required'), 500, 'MODEL_DELETED_FIELD_IS_UNDEFINED');
        }

        # Procesamiento
        $statusList = [];
        $this->model->db->transStart();
        foreach ($config['input'] as $index => $item) {
            try {
                $validItem = $this ->baseItemValidation($config, $index, $item, $response, $statusList);
                if ($validItem instanceof Response) {
                    $this->model->db->transRollback();
                    return $validItem;
                }
                elseif ($validItem === false) {
                    continue;
                }

                $itemId = $validItem[$this ->model ->primaryKey];
                $this ->model ->set($validItem)
                    ->set($this->model->deletedField, null)
                    ->where($this->model->deletedField . ' !=', null)
                    ->limit(1)
                    ->update($itemId);

                $result = $this->model->db->affectedRows();
                if ($result >= 1) {
                    $result = $this->baseItemSuccess($config, $index, $validItem, $response, $statusList);
                    if ($result instanceof Response) {
                        $this->model->db->transRollback();
                        return $result;
                    }
                }
                else {
                    $statusList[200] = true;
                    $response['data'][$index] = $this->formatItemSuccess(200, [$this ->model ->primaryKey => $itemId, 'affected' => false], lang('CrudTraits.update.no_changes'));
                }
            }
            catch (\Throwable $e) {
                $this->model->db->transRollback();
                return $this ->baseItemError($config, $index, $e);
            }
        }

        $result = $this->baseEnding($config, $response, $statusList);
        if ($result instanceof Response) {
            $this->model->db->transRollback();
            return $result;
        }

        $this->model->db->transComplete();
        if ($this->model->db->transStatus() === false) {
            return $this ->fail(lang('CrudTraits.restore.error'), 500, 'RESTORE_TRANSACTION_ERROR');
        }

        return $this ->respond($result['data'], $result['status']);
    }

    ### Funciones que se repiten al procesar los items
    /**
     * Proceso que se repiten al inicio de:
     *  - baseCreate
     *  - baseUpdate
     *  - baseDelete
     *  - BaseRestore
     *
     * @param string $action - Tipo de acción ('create', 'update', 'delete' o 'restore')
     * @param ?int $id - Id del registro que sera afectado (aplica en todos menos en 'create')
     * @param ?array $input - Entrada
     *
     * @return array|Response - Response en caso de error
     */
    private function baseInit(string $action, $id = null, ?array $input = null) : array|Response {
        $isDeleteOrRestore = $action == 'delete' || $action == 'restore';

        # Modelo
        if (!isset($this ->model)) {
            return $this ->fail(lang('CrudTraits.model.is_undefined'), 500, 'MODEL_IS_UNDEFINED');
        }

        $useTimestamps = $this->model->getUseTimestamps();
        $timestamp = $this->model->getTimestamp();

        $authToken = service('authToken');
        $subt = $authToken ->getPayloadValue('subt');
        $sub = $authToken ->getPayloadValue('sub');
        if (!empty($subt) && !empty($sub)) {
            $this ->initBinnacle(
                toEntityType:   $this ->model ->table,
                action:         $action,
                fromEntityType: $subt,
                fromEntityId:   (int) $sub,
                jti:            $authToken ->getPayloadValue('jti') ?? '',
                ip:             $this ->request ->getIPAddress(),
            );
        }

        # Entrada
        if ($isDeleteOrRestore) {
            $input ??= is_numeric($id) ? [] : null;
        }

        $input ??= $this->getInput();
        if ($input instanceof Response) return $input;

        //  Hook: Antes de procesar
        if ($this ->hasCallback('before')) {
            $result = $this->runCallback('before', $input);
            if ($result instanceof Response) return $result;
            $input = is_array($result) ? $result : $input;
        }
        if ($action == 'create') {
            $isBatch = $this->getConfig('create:isBatch');
            if ($isBatch === null) {
                $isBatch = $this->request->getGet('is_batch') === 'true';
            }
            $onlyOne = !$isBatch;
            $input = $onlyOne ? [$input] : $input;
        }
        else {
            $onlyOne = is_numeric($id);
            $input = $onlyOne ? [array_merge($input, [$this ->model ->primaryKey => $id])] : $input;
        }
        $inputCount = $onlyOne ? 1 : count($input);
        $limit = $this->getConfig('limit');

        if ($inputCount > $limit) {
            return $this ->fail(lang('CrudTraits.input.limit_exceeded', [$limit]), 400, 'INPUT_LIMIT_EXCEEDED');
        }

        //  Evitar PK duplicadas en operaciones por lote (delete/update/restore)
        if (!$onlyOne && $action !== 'create') {
            $seen = [];
            foreach ($input as $item) {
                $pk = $item[$this->model->primaryKey] ?? null;
                if ($pk === null) {
                    continue;
                }
                if (isset($seen[$pk])) {
                    return $this->fail(
                        lang('CrudTraits.input.duplicate_pk', [$pk]),
                        400,
                        'DUPLICATE_PRIMARY_KEY'
                    );
                }
                $seen[$pk] = true;
            }
        }

        $rules = $isDeleteOrRestore ? [] : $this->model->getValidationRules();

        if ($action !== 'create') {
            $rules[$this->model->primaryKey] = 'required|is_natural_no_zero|less_than_equal_to[' . PHP_INT_MAX . ']';
        }

        //  Hook: Establecer reglas generales
        if ($this->hasCallback('setRules')) {
            $result = $this->runCallback('setRules', $rules);
            if ($result instanceof Response) return $result;
            $rules = is_array($result) ? $result : $rules;
        }

        $validation = service('validation');

        $this->model->skipValidation(true)->protect(false);

        return [
            'action' => $action,
            'onlyOne' => $onlyOne,
            'input' => $input,
            'inputCount' => $inputCount,
            'rules' => $rules,
            'validation' => $validation,
            'limit' => $limit,
            'useTimestamps' => $useTimestamps,
            'timestamp' => $timestamp,
            'remove_primary_key' => ($action === 'create'),
        ];
    }

    /**
     * Proceso que se repite al validar un item en:
     *  - baseCreate
     *  - baseUpdate
     *  - baseDelete
     *  - BaseRestore
     *
     * @param array $actionConfig - Configuración de la acción (datos devueltos en "baseInit")
     * @param int $index - indice actual
     * @param mixed $item - Item que se esta procesando
     * @param array $response - Respuesta
     * @param array $statusList - Lista de estados
     *
     * @return array|bool|Response - array con el item validado (campos limpios), false en caso de incidencia pero sin para el proceso, Response en caso de incidencia
     */
    private function baseItemValidation(array $actionConfig, int|string $index, mixed $item, array &$response, array &$statusList) : array|bool|Response {
        $rules = $actionConfig['rules'];
        $item = !is_array($item) ? [$item] : $item;

        if (!empty($actionConfig['remove_primary_key'])) {
            unset($item[$this->model->primaryKey]);
        }

        // Hook: Valores del Item
        if ($this ->hasCallback('itemSetValues')) {
            $result = $this->runCallback('itemSetValues', $item, $actionConfig);
            if ($result instanceof Response) {
                return $result;
            }
            elseif (is_array($result)) {
                if ($this ->addIfError($index, $result, $response, $statusList)) {
                    return false;
                }
                $item = $result;
            }
        }

        // Hook: Reglas del Item
        if ($actionConfig['action'] === 'update') {
            foreach ($rules as $field => $rule) {
                if ($field != $this->model->primaryKey && !array_key_exists($field, $item)) {
                    unset($rules[$field]);
                }
            }
        }

        if ($this ->hasCallback('itemSetRules')) {
            $result = $this ->runCallback('itemSetRules', $rules, $item, $actionConfig);
            if ($result instanceof Response) {
                return $result;
            }
            elseif(is_array($result)) {
                if ($this ->addIfError($index, $result, $response, $statusList)) {
                    return false;
                }
                $rules = $result;
            }
        }

        // Validación por Registro
        $actionConfig['validation']->reset();
        $actionConfig['validation']->setRules($rules);
        if (!$actionConfig['validation']->run($item)) {
            $response['data'][$index] = $this->formatItemError(400, 'FAIL_VALIDATION', $actionConfig['validation']->getErrors());
            $statusList[400] = true;
            return false;
        }

        $validItem = $actionConfig['validation']->getValidated();

        // Hook: Antes de procesar
        if ($this ->hasCallback('itemAfterValidation')) {
            $result = $this ->runCallback('itemAfterValidation', $validItem, $item, $actionConfig);
            if ($result instanceof Response) {
                return $result;
            }
            elseif (is_array($result)) {
                if ($this ->addIfError($index, $result, $response, $statusList)) {
                   return false;
                }
                $validItem = $result;
            }
        }

        return $validItem;
    }

    /**
     * Proceso que se repite al tener exito al processar un item en:
     *  - baseCreate
     *  - baseUpdate
     *  - baseDelete
     *  - BaseRestore
     *
     * @param array $actionConfig - Configuración de la acción (datos devueltos en "baseInit")
     * @param int $index - indice actual
     * @param array $item - Item afectado
     * @param array $response - Respuesta
     * @param array $statusList - Lista de estados
     *
     * @return bool|Response - true en caso de exito, Response en caso de insidencia
     */
    private function baseItemSuccess(array $actionConfig, int|string $index, array $item, array &$response, array &$statusList) : bool|Response {
        $itemId = $item[$this ->model ->primaryKey];
        $action = $actionConfig['action'];

        # Bitácora
        if ($this ->binnacleRecordTemplate !== null) {
            $binnacleData = [];
            if (!$actionConfig['onlyOne']) {
                $binnacleData['count'] = ($index + 1) . '/' . $actionConfig['inputCount'];
            }
            $binnacleData['data'] = $item;
            unset($binnacleData['data'][$this ->model ->primaryKey]);

            if ($action == 'delete') {
                $binnacleData['in_trash'] = $actionConfig['inTrash'] ?? null;
            }

            if ($this ->hasCallback('itemSetBinnacleData')) {
                $result = $this ->runCallback('itemSetBinnacleData', $itemId, $binnacleData, $actionConfig);
                if ($result instanceof Response) {
                    return $result;
                }
                $binnacleData = is_array($result) ? $result : $binnacleData;
            }

            if (empty($binnacleData['data'])) {
                unset($binnacleData['data']);
            }

            $this ->addRecord($itemId, $binnacleData);
        }

        # Respuesta
        $status = $action == 'create' ? 201 : 200;
        $data = [$this ->model ->primaryKey => $itemId];
        $messages = null;
        if ($actionConfig['onlyOne']) {
            $key = 'success';
            if ($action == 'delete' && !$actionConfig['inTrash']) {
                $key .= '_trash';
            }
            $configKey = $key === 'success' ? 'messageSuccess' : 'messageSuccessTrash';
            $messages = $this->getConfig($configKey) ?? lang('CrudTraits.' . $action . '.' . $key);
        }

        if ($action != 'create') {
            $data['affected'] = true;
        }

        $statusList[$status] = true;
        $response['affected']++;
        $response['data'][$index] = $this->formatItemSuccess($status, $data, $messages);

        # Hook: Éxito al procesar
        if ($this ->hasCallback('itemSuccess')) {
            $result = $this ->runCallback('itemSuccess', $itemId, $item, $response['data'][$index], $actionConfig);
            if ($result instanceof Response) {
                return $result;
            }
            $response['data'][$index] = is_array($result) ? $result : $response['data'][$index];
        }

        return true;
    }

    /**
     * Proceso que se repite al tener un error al procesar un item en:
     *  - baseCreate
     *  - baseUpdate
     *  - baseDelete
     *  - BaseRestore
     *
     * @param array $actionConfig - Configuración de la acción (datos devueltos en "baseInit")
     * @param int $index - indice actual
     * @param Throwable $e - Error
     *
     * @return Response
     */
    private function baseItemError(array $actionConfig, int|string $index, \Throwable $e) : Response {
        $this->model->setUseTimestamps($actionConfig['useTimestamps']);
        $action = strtoupper($actionConfig['action']);
        log_message('error', "[{$action}_ITEM_ERROR] {exception}\nItem Index:{index}\nInput:\t{input}\nStack trace:\n{stackTrace}", [
            'exception' => $e,
            'stackTrace' => $e->getTraceAsString(),
            'input' => json_encode($actionConfig['input']),
            'index' => $index
        ]);
        return $this ->fail($e->getMessage(), 500, $action . '_ERROR');
    }

    /**
     * Proceso que se repite al finalizar:
     *  - baseCreate
     *  - baseUpdate
     *  - baseDelete
     *  - BaseRestore
     *
     * @param array $actionConfig - Configuración de la acción (datos devueltos en "baseInit")
     * @param array $response - Respuesta
     * @param array $statusList - Lista de estados
     *
     * @return array|Response
     */
    private function baseEnding(array $actionConfig, array $response, array $statusList) : array|Response {
        $this->model->setUseTimestamps($actionConfig['useTimestamps']);
        # Hook: Despues de procesar
        if ($this ->hasCallback('after')) {
            $result = $this->runCallback('after', $response, $actionConfig);
            if ($result instanceof Response) {
                return $result;
            };
            $response = is_array($result) ? $result : $response;
        }


        if (!$this ->flushBinnacle()) {
            $action = strtoupper($actionConfig['action']);
            return $this ->fail(lang('CrudTraits.binnacle.error'), 500, $action . '_BINNACLE_ERROR');
        }

        return $this->formatResponse($actionConfig, $response, $statusList);
    }

    ### Utilidades
    /**
     * Establece o cambia el esquema al que está vinculado el controlador.
     * Dado el nombre o el objeto, determina el otro.
     *
     * @param object|string|null $which El esquema o el nombre del esquema.
     *
     * @return void
     */
    public function setSchema($which = null) {
        if ($which !== null) {
            if (is_object($which)) {
                $this->schema = $which;
                $this->schemaName = $which::class;
            } else {
                $this->schemaName = str_contains($which, '\\') ? $which : "App\\Schemas\\" . $which;
            }
        }

        if (empty($this->schema) && !empty($this->schemaName)) {
            if (class_exists($this->schemaName)) {
                $this->schema = new $this->schemaName();
            }
        }
    }

    /**
     * Formatea errores individuales de items para mantener consistencia.
     */
    private function formatItemError(int $status, string $error, array|string $messages): array {
        return [
            'status'   => $status,
            'error'    => $error,
            'messages' => is_string($messages) ? ['error' => $messages] : $messages
        ];
    }

    /**
     * Formatea complentado individuales de items para mantener consistencia.
     */
    private function formatItemSuccess(int $status, ?array $data = null, array|string|null $messages = null): array {
        $response = [
            'status'   => $status
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        if (!empty($messages)) {
            $response['messages'] = is_string($messages) ? ['success' => $messages] : $messages;
        }

        return $response;
    }

    /**
     * Formater respuesta
     */
    private function formatResponse(array $actionConfig, array $response, array $statusList)  : array {
        $status = null;

        if ($actionConfig['onlyOne']) {
            $status = array_key_first($statusList) ?? 200;
            $response = $response['data'][array_key_first($response['data'])] ?? [];
            if ($actionConfig['action'] == 'read') {
                $response = [
                    'data' => $response
                ];
            }
        } else {
            $status = (count($statusList) == 1) ? array_key_first($statusList) : 207;
        }

        if ($actionConfig['action'] != 'read') {
            $response['status'] = $status;
        }

        return ['data' => $response, 'status' => $status];
    }

    /**
     * Agregar si es un error, sucede cuando desde un callback se devuelve un array
     */
    private function  addIfError(string|int $index, array $callbackResult, array &$response, array &$statusList) : bool {
        if (isset($callbackResult['status'], $callbackResult['error'], $callbackResult['messages'])) {
            $statusList[$callbackResult['status']] = true;
            $response['data'][$index] = $this ->formatItemError($callbackResult['status'], $callbackResult['error'], $callbackResult['messages']);
            return true;
        }

        return false;
    }

}

<?php

namespace App\Schemas\System;

use DancasDev\DQB\Schema;

class BinnacleSchema extends Schema {
    protected array $tablesConfig = [
        'binnacle' => [
            'name' => 'sys_binnacle'
        ]
    ];
    protected array $fieldsConfig = [
        'id'                => [],
        'from_entity_type'  => [],
        'from_entity_id'    => [],
        'to_entity_type'    => [],
        'to_entity_id'      => [],
        'action'            => [],
        'date'              => [],
        'ip'                => [],
        'data'              => [],
        'session_id'        => [],
    ];

    public const FIELDS_CALCULATED = [
        'description' => ['from_entity_type', 'from_entity_id', 'to_entity_type', 'to_entity_id', 'action', 'ip', 'data'],
    ];

    public static function fieldsCalculatedCallback($records, $fieldsCalculated, $removeFieldsFunction): array {
        helper('number');

        $entityLabels = model('App\Models\System\BinnacleModel')->getEntityLabels($records);
        $specificActions = lang('Binnacle.specific_actions');
        $generalActions = lang('Binnacle.general_actions');

        foreach ($records as $key => $item) {
            $context = self::buildContext($item, $entityLabels);

            $messageKey = $item['to_entity_type'] . '_' . $item['action'];
            if (isset($specificActions[$messageKey])) {
                $messageKey = 'Binnacle.specific_actions.' . $messageKey;
            } elseif (isset($generalActions[$item['action']])) {
                $messageKey = 'Binnacle.general_actions.' . $item['action'];
            } else {
                $messageKey = 'Binnacle.general_actions.unknown';
            }

            $records[$key]['description'] = lang($messageKey, $context);
            $records[$key] = $removeFieldsFunction($records[$key]);
        }

        return $records;
    }

    private static function buildContext(array $item, array $entityLabels): array {
        return [
            'from_entity'         => lang(
                'Binnacle.from_entities.' . $item['from_entity_type'] . '.connector',
                ['label' => self::resolveFromEntityLabel($item, $entityLabels)]
            ),
            'to_entity'           => lang(
                'Binnacle.to_entities.' . $item['to_entity_type'] . '.connector',
                ['label' => self::resolveToEntityLabel($item, $entityLabels)]
            ),
            'ip'                  => $item['ip'],
            'from_entity_type'    => $item['from_entity_type'],
            'from_entity_id'      => $item['from_entity_id'],
            'to_entity_type_code' => $item['to_entity_type'],
            'to_entity_id'        => $item['to_entity_id'],
            'action'              => $item['action'],
        ];
    }

    private static function resolveFromEntityLabel(array $item, array $entityLabels): ?string {
        $tables = ['1' => 'gac_user', '2' => 'gac_client'];
        $table = $tables[$item['from_entity_type']] ?? null;

        if ($table === null) {
            return null;
        }

        return $entityLabels[$table][$item['from_entity_id']] ?? $item['from_entity_id'];
    }

    private static function resolveToEntityLabel(array $item, array $entityLabels): ?string {
        $table = $item['to_entity_type'];
        $label = $entityLabels[$table][$item['to_entity_id']] ?? $item['to_entity_id'];

        $fileData = self::extractFileData($item);
        if ($fileData !== null) {
            $label .= ' (' . $fileData['mime_type'] . ', ' . number_to_size((int) $fileData['size'], 2) . ')';
        }

        return $label;
    }

    private static function extractFileData(array $item): ?array {
        if (!isset($item['data']) || !in_array($item['action'], ['create', 'delete'], true)) {
            return null;
        }

        $decoded = json_decode($item['data'], true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['mime_type'], $decoded['size'])) {
            return null;
        }

        return $decoded;
    }
}

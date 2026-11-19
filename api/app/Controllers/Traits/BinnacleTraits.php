<?php

namespace App\Controllers\Traits;

use App\Models\System\BinnacleModel;

trait BinnacleTraits {
    private array $binnacleRecords = [];
    private ?array $binnacleRecordTemplate = null;

    protected function initBinnacle(
        string $toEntityType,
        string $action,
        string $fromEntityType,
        ?int $fromEntityId,
        string $jti,
        string $ip
    ): void {
        $this->binnacleRecordTemplate = [
            'from_entity_type' => $fromEntityType,
            'from_entity_id'   => $fromEntityId,
            'to_entity_type'   => $toEntityType,
            'to_entity_id'     => null,
            'action'           => $action,
            'date'             => date('Y-m-d H:i:s'),
            'ip'               => $ip,
            'data'             => null,
            'session_id'       => $jti,
        ];
    }

    protected function addRecord(string $itemId, mixed $data = null): void {
        if ($this->binnacleRecordTemplate === null) {
            return;
        }

        $record = $this->binnacleRecordTemplate;
        $record['to_entity_id'] = (string) $itemId;
        $record['data'] = $data;

        $this->binnacleRecords[] = $record;
    }

    protected function flushBinnacle(): bool {
        try {
            if (!empty($this->binnacleRecords)) {
                $model = new BinnacleModel();

                if (!$model->insertBatch($this->binnacleRecords)) {
                    return false;
                }
            }
        } catch (\Throwable $e) {
            log_message('error', "[BINNACLE_ERROR] {exception}\nData:\t{data}", [
                'exception' => $e,
                'data' => json_encode($this->binnacleRecords),
            ]);
            return false;
        }

        return true;
    }
}

<?php

namespace App\Models;

use CodeIgniter\Model;

class BaseModel extends Model {
    protected $skipValidation = false;
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';

    public function getUseTimestamps(): bool {
        return $this->useTimestamps;
    }

    public function setUseTimestamps(bool $value) {
        $this->useTimestamps = $value;
        return $this;
    }

    public function getTimestamp() {
        return $this->dateFormat === 'int' ? time() : date('Y-m-d H:i:s');
    }

    /**
     * Campos a codificar como JSON en inserción y actualización si son arreglos.
     *
     * @var array<string>
     */
    protected array $jsonFields = [];

    /**
     * Callback para codificar a JSON los campos especificados en $jsonFields.
     */
    protected function encodeJson(array $data): array {
        if (empty($this->jsonFields) || !isset($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        foreach ($this->jsonFields as $field) {
            if (isset($data['data'][$field]) && is_array($data['data'][$field])) {
                $data['data'][$field] = json_encode($data['data'][$field], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $data;
    }

    /**
     * Callback para codificar a JSON los campos en inserciones/actualizaciones por lote.
     */
    protected function encodeJsonBatch(array $data): array {
        if (empty($this->jsonFields) || !isset($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        foreach ($data['data'] as $idx => $row) {
            if (is_array($row)) {
                foreach ($this->jsonFields as $field) {
                    if (isset($row[$field]) && is_array($row[$field])) {
                        $data['data'][$idx][$field] = json_encode($row[$field], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                }
            }
        }

        return $data;
    }
}


<?php

namespace App\Controllers\Traits;

use CodeIgniter\HTTP\Response;


trait RequestTraits {
    public function getInput(bool $isRequired = true) : array|null|Response {
        try {
            $input = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            return $this->fail(lang('ControllerTraits.request.input_invalid'), 400, 'INPUT_INVALID');
        }
        if ($isRequired && empty($input)) {
            return $this->fail(lang('ControllerTraits.request.input_is_required'), 400, 'INPUT_IS_REQUIRED');
        }
        return $input;
    }
}
<?php

require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

// Inyectar conexión activa en instancia 'tests'
$db = \Config\Database::connect('default');
$ref = new \ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
$instances = $ref->getValue();
$instances['tests'] = $db;
$ref->setValue(null, $instances);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertCondition(string $name, bool $condition, string $details = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [PASS] $name\n";
    } else {
        $failedTests++;
        echo " [FAIL] $name: $details\n";
    }
}

echo "========================================================\n";
echo " MOTCAR — PRUEBAS HEADLESS DE CONTRATOS SAM (CLI)\n";
echo "========================================================\n\n";

use App\Models\Finance\ContractDocumentModel;
use App\Models\Finance\ContractTypeModel;
use App\Libraries\Finance\ContractRenderer;
use App\Controllers\V1\Branches\Financing\Groups\Participants\Contract as ContractController;

$contractModel = model(ContractDocumentModel::class);
$contractTypeModel = model(ContractTypeModel::class);

// -------------------------------------------------------------------
// 1. Verificación de Modelos y Catálogos
// -------------------------------------------------------------------
echo "--- 1. Modelos y Catálogos Base ---\n";

$types = $contractTypeModel->getByCache(true);
$motorcycleType = null;
foreach ($types as $id => $type) {
    if ($type['code'] === 'san_motorcycles_v1') {
        $motorcycleType = $type;
        $motorcycleTypeId = $id;
        break;
    }
}

assertCondition('Tipo de contrato san_motorcycles_v1 existe en caché', $motorcycleType !== null);
assertCondition('Tipo de contrato tiene metadata_schema_id configurado', !empty($motorcycleType['metadata_schema_id']));

// -------------------------------------------------------------------
// 2. Reglas de Validación de ContractDocumentModel
// -------------------------------------------------------------------
echo "\n--- 2. Validaciones declarativas de ContractDocumentModel ---\n";

// Rechazo de branch_id inexistente
$res = $contractModel->insert([
    'branch_id'        => 999999,
    'contract_type_id' => $motorcycleTypeId,
    'origin_type'      => '0',
    'origin_id'        => 1,
    'code'             => 'CONTRACT-TEST-001',
    'issue_date'       => date('Y-m-d H:i:s'),
]);
assertCondition('Rechaza branch_id inexistente', $res === false, json_encode($contractModel->errors()));

// Rechazo de contract_type_id inexistente
$res = $contractModel->insert([
    'branch_id'        => 1,
    'contract_type_id' => 999999,
    'origin_type'      => '0',
    'origin_id'        => 1,
    'code'             => 'CONTRACT-TEST-002',
    'issue_date'       => date('Y-m-d H:i:s'),
]);
assertCondition('Rechaza contract_type_id inexistente', $res === false, json_encode($contractModel->errors()));

// Rechazo de sign_status inválido
$res = $contractModel->insert([
    'branch_id'        => 1,
    'contract_type_id' => $motorcycleTypeId,
    'origin_type'      => '0',
    'origin_id'        => 1,
    'code'             => 'CONTRACT-TEST-003',
    'issue_date'       => date('Y-m-d H:i:s'),
    'sign_status'      => '9',
]);
assertCondition('Rechaza sign_status fuera de catálogo (in_list[0,1,2])', $res === false, json_encode($contractModel->errors()));

// Rechazo de URL de archivo inválida
$res = $contractModel->insert([
    'branch_id'        => 1,
    'contract_type_id' => $motorcycleTypeId,
    'origin_type'      => '0',
    'origin_id'        => 1,
    'code'             => 'CONTRACT-TEST-004',
    'issue_date'       => date('Y-m-d H:i:s'),
    'file_url'         => 'not_a_valid_url',
]);
assertCondition('Rechaza file_url con formato no válido (valid_url_strict)', $res === false, json_encode($contractModel->errors()));

// -------------------------------------------------------------------
// 3. Preparación de Fixtures para Pruebas Operativas
// -------------------------------------------------------------------
echo "\n--- 3. Preparación de Fixtures Operativos ---\n";

// Limpiar previos si existieran
$oldPerson = $db->table('glb_person')->where('identification_value', '30747985')->get()->getRowArray();
if ($oldPerson) {
    $oldParts = $db->table('fnc_san_participant')->select('id')->where('client_id', $oldPerson['id'])->get()->getResultArray();
    if (!empty($oldParts)) {
        $oldPartIds = array_column($oldParts, 'id');
        $db->table('fnc_contract_document')->where('origin_type', '0')->whereIn('origin_id', $oldPartIds)->delete();
        $db->table('fnc_san_participant')->whereIn('id', $oldPartIds)->delete();
    }
    $db->table('glb_person')->where('id', $oldPerson['id'])->delete();
}

$identVal = '30747985';
// Crear persona cliente
$db->table('glb_person')->insert([
    'identification_type'  => '0',
    'identification_value' => $identVal,
    'first_name'           => 'NICOLAS',
    'last_name'            => 'HERNANDEZ',
    'phone_primary'        => '04121234567',
    'address'              => 'AV. PRINCIPAL CASA 17C-11',
    'city'                 => 'MARACAIBO',
    'is_disabled'          => '0',
    'created_at'           => date('Y-m-d H:i:s'),
]);
$clientId = (int) $db->insertID();

// Crear grupo SAM con contract_type_id = $motorcycleTypeId
$db->table('fnc_san_group')->insert([
    'branch_id'           => 1,
    'frequency_id'        => 2, // biweekly
    'contract_type_id'    => $motorcycleTypeId,
    'name'                => 'Grupo Test Contratos ' . bin2hex(random_bytes(2)),
    'amount'              => 1150.00,
    'installment_amount'  => 60.00,
    'installment_count'   => 22,
    'participant_max'     => 10,
    'participant_count'   => 1,
    'frequency_base_date' => date('Y-m-d'),
    'status'              => '1',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$groupId = (int) $db->insertID();

// Crear participante activo con beneficio configurado
$benefitData = [
    'brand'           => 'Bera',
    'model'           => 'SBR 150',
    'year'            => '2026',
    'reference_value' => 1150.00,
    'plate'           => 'AE1B23C',
    'chassis_serial'  => '8XAJF920381029384',
    'engine_serial'   => '162FMJ2938102',
    'color'           => 'Azul',
];

$db->table('fnc_san_participant')->insert([
    'branch_id'           => 1,
    'san_group_id'        => $groupId,
    'client_id'           => $clientId,
    'position'            => 1,
    'frequency_base_date' => date('Y-m-d'),
    'status'              => '1',
    'benefit_metadata'    => json_encode($benefitData),
    'is_overdue'          => '0',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$participantId = (int) $db->insertID();

assertCondition('Fixture de participante creado con éxito', $participantId > 0);

// Crear grupo sin contract_type_id para probar error 409
$db->table('fnc_san_group')->insert([
    'branch_id'           => 1,
    'frequency_id'        => 3,
    'contract_type_id'    => null,
    'name'                => 'Grupo Sin Tipo ' . bin2hex(random_bytes(2)),
    'amount'              => 1000.00,
    'installment_amount'  => 100.00,
    'installment_count'   => 10,
    'participant_max'     => 10,
    'participant_count'   => 1,
    'frequency_base_date' => date('Y-m-d'),
    'status'              => '1',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$groupWithoutTypeId = (int) $db->insertID();

$db->table('fnc_san_participant')->insert([
    'branch_id'           => 1,
    'san_group_id'        => $groupWithoutTypeId,
    'client_id'           => $clientId,
    'position'            => 1,
    'status'              => '1',
    'is_overdue'          => '0',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$participantWithoutTypeId = (int) $db->insertID();

// -------------------------------------------------------------------
// 4. Pruebas Operativas de Emisión y Códigos en Inglés
// -------------------------------------------------------------------
echo "\n--- 4. Emisión de Contratos y Códigos en Inglés (CNTR-) ---\n";

$controller = new ContractController();
$controller->initController(
    service('request'),
    service('response'),
    service('logger')
);

// Rechazo por grupo sin contract_type_id
$response = $controller->create(1, $groupWithoutTypeId, $participantWithoutTypeId);
assertCondition('Rechaza creación si el grupo no tiene contract_type_id (409)', $response->getStatusCode() === 409);

// Emisión exitosa de contrato para participante válido
$response = $controller->create(1, $groupId, $participantId);
$statusCode = $response->getStatusCode();
$body = json_decode($response->getBody(), true);

assertCondition('Emisión de contrato exitosa (201)', $statusCode === 201);
assertCondition('Respuesta de creación contiene data.id', !empty($body['data']['id']));

$contractId = (int) ($body['data']['id'] ?? 0);
$dbContract = $contractModel->find($contractId);

assertCondition('Código de contrato en inglés (comienza con CNTR-)', str_starts_with($dbContract['code'] ?? '', 'CNTR-'));
assertCondition('Contrato emitido con sign_status = 0 (Pendiente)', ($dbContract['sign_status'] ?? null) === '0');

// Rechazo por contrato ya existente para el mismo participante
$responseDup = $controller->create(1, $groupId, $participantId);
assertCondition('Rechaza emisión si ya existe contrato activo o firmado (409)', $responseDup->getStatusCode() === 409);

// -------------------------------------------------------------------
// 5. Consulta y Visualización (show)
// -------------------------------------------------------------------
echo "\n--- 5. Consulta del Contrato (show) ---\n";

$showResponse = $controller->show(1, $groupId, $participantId);
$showBody = json_decode($showResponse->getBody(), true);

assertCondition('Consulta de contrato responde 200', $showResponse->getStatusCode() === 200);
assertCondition('Consulta retorna el código correcto en inglés', ($showBody['data']['code'] ?? '') === ($dbContract['code'] ?? ''));
assertCondition('Consulta resuelve contract_type_code = san_motorcycles_v1', ($showBody['data']['contract_type_code'] ?? '') === 'san_motorcycles_v1');

// -------------------------------------------------------------------
// 6. Renderizado de PDF con TCPDF
// -------------------------------------------------------------------
echo "\n--- 6. Renderizado de PDF con TCPDF ---\n";

$renderer = new ContractRenderer($db);
$pdfBinary = $renderer->render($contractId);

assertCondition('Generación de PDF produce contenido no vacío', !empty($pdfBinary));
assertCondition('Cabecera de binario corresponde a formato PDF (%PDF-)', str_starts_with($pdfBinary, '%PDF-'));

$pdfResponse = $controller->pdf(1, $groupId, $participantId);
assertCondition('Endpoint PDF responde con status 200', $pdfResponse->getStatusCode() === 200);
assertCondition('Endpoint PDF devuelve Content-Type application/pdf', str_contains($pdfResponse->getHeaderLine('Content-Type'), 'application/pdf'));

// -------------------------------------------------------------------
// 7. Registro de Firma Digitalizada (sign)
// -------------------------------------------------------------------
echo "\n--- 7. Registro de Firma Física Digitalizada (sign) ---\n";

// Petición sin archivo URL
$_SERVER['REQUEST_METHOD'] = 'PUT';
service('request')->setBody(json_encode([]));
$badSignResponse = $controller->sign(1, $groupId, $participantId);
assertCondition('Rechaza firma si faltan file_url o signed_date (400)', $badSignResponse->getStatusCode() === 400);

// Firma exitosa con URL válida de documento digitalizado
$validSignData = [
    'file_url'    => 'https://storage.motcar.com/lockers/documents/contracts/signed/contract_01_signed.pdf',
    'signed_date' => date('Y-m-d'),
];
service('request')->setBody(json_encode($validSignData));

$signResponse = $controller->sign(1, $groupId, $participantId);
$signBody = json_decode($signResponse->getBody(), true);

assertCondition('Firma registrada exitosamente (200)', $signResponse->getStatusCode() === 200);

$updatedContract = $contractModel->find($contractId);
assertCondition('sign_status transicionado a 1 (Firmado)', ($updatedContract['sign_status'] ?? null) === '1');
assertCondition('file_url almacenado correctamente', ($updatedContract['file_url'] ?? null) === $validSignData['file_url']);
assertCondition('signed_date almacenado correctamente', ($updatedContract['signed_date'] ?? null) === $validSignData['signed_date']);

// Rechazo al intentar firmar un contrato ya firmado
$secondSignResponse = $controller->sign(1, $groupId, $participantId);
assertCondition('Rechaza volver a firmar un contrato ya firmado (409)', $secondSignResponse->getStatusCode() === 409);

// -------------------------------------------------------------------
// 8. Limpieza de Fixtures
// -------------------------------------------------------------------
echo "\n--- 8. Limpieza de datos de prueba ---\n";

$db->table('fnc_contract_document')->where('id', $contractId)->delete();
$db->table('fnc_san_participant')->whereIn('id', [$participantId, $participantWithoutTypeId])->delete();
$db->table('fnc_san_group')->whereIn('id', [$groupId, $groupWithoutTypeId])->delete();
$db->table('glb_person')->where('id', $clientId)->delete();

echo "\n========================================================\n";
echo " RESULTADOS: {$passedTests}/{$totalTests} pruebas exitosas\n";
if ($failedTests > 0) {
    echo " ATENCIÓN: Hubo {$failedTests} pruebas fallidas.\n";
} else {
    echo " EXCELENTE: 100% de pruebas pasadas exitosamente.\n";
}
echo "========================================================\n";

exit($failedTests === 0 ? 0 : 1);

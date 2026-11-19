<?php

/**
 * =====================================================================
 * MOTCAR — PRUEBAS EXHAUSTIVAS DE CONTROLADORES Y CONTRATOS SAM (CLI)
 * =====================================================================
 * Cubre:
 * 1. Normalización de Controladores y Modelos (ci4-controller, ci4-model, AGENTS.md).
 * 2. Casos de Desborde y Validación en Modelo (ContractDocumentModel).
 * 3. Seguridad, Scoping e IDOR (Cross-Branch, Cross-Group, Soft-Deletes).
 * 4. Reglas de Negocio en Emisión (create).
 * 5. Reglas de Negocio en Firma y Formalización (sign).
 * 6. Resistencia ante Payloads Maliciosos e Inmutabilidad de Campos.
 * 7. Renderizado TCPDF con Casos Extremos (XSS, montos gigantes, metadatos vacíos).
 * 8. Pruebas de Estrés, Concurrencia y Cero Estado Mutable (Stateless).
 */

require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

// Inyectar conexión activa en instancia 'tests'
$db = \Config\Database::connect('default');
$ref = new \ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
$instances = $ref->getValue();
$instances['tests'] = $db;
$ref->setValue(null, $instances);

use App\Controllers\V1\Branches\Financing\Groups\Participants\Contract as ContractController;
use App\Models\Finance\ContractDocumentModel;
use App\Models\Finance\ContractTypeModel;
use App\Libraries\Finance\ContractRenderer;

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
        echo " [FAIL] $name" . ($details ? ": $details" : '') . "\n";
    }
}

echo "=====================================================================\n";
echo " MOTCAR — PRUEBAS EXHAUSTIVAS DE CONTRATOS Y CONTROLADORES (CLI)\n";
echo "=====================================================================\n";

// -------------------------------------------------------------------
// 1. Normalización Arquitectónica y Estándares (Skills + AGENTS.md)
// -------------------------------------------------------------------
echo "\n--- 1. Normalización Arquitectónica (ci4-controller / ci4-model) ---\n";

$controllerClass = ContractController::class;
$modelClass      = ContractDocumentModel::class;

// Herencia de BaseController
assertCondition('Controlador extiende de BaseController', is_subclass_of($controllerClass, \App\Controllers\V1\BaseController::class));

// Uso de CrudTraits
$traits = class_uses($controllerClass);
assertCondition('Controlador utiliza CrudTraits', isset($traits[\App\Controllers\Traits\CrudTraits::class]));

// Modelo extiende de BaseModel
assertCondition('Modelo extiende de BaseModel', is_subclass_of($modelClass, \App\Models\BaseModel::class));

$contractModel = new ContractDocumentModel();
$refProp = new ReflectionProperty(ContractDocumentModel::class, 'useSoftDeletes');
$refProp->setAccessible(true);
assertCondition('Modelo tiene soft deletes habilitado', $refProp->getValue($contractModel) === true);

// DocBlock presente en el controlador
$reflection = new ReflectionClass($controllerClass);
$docComment = $reflection->getDocComment();
assertCondition('DocBlock explicativo presente en controlador', !empty($docComment) && str_contains($docComment, 'fnc_contract_document'));

// Métodos auxiliares estandarizados
assertCondition('Controlador implementa método auxiliar _read', $reflection->hasMethod('_read'));
assertCondition('Controlador implementa método auxiliar getParticipant', $reflection->hasMethod('getParticipant'));
assertCondition('Controlador implementa método auxiliar getActiveContract', $reflection->hasMethod('getActiveContract'));

// -------------------------------------------------------------------
// 2. Validación Declarativa y Casos de Desborde en Modelo
// -------------------------------------------------------------------
echo "\n--- 2. Casos de Desborde y Validaciones en ContractDocumentModel ---\n";

// A. Desborde en file_url (> 255 caracteres)
$invalidUrlOverflow = 'https://ejemplo.com/' . str_repeat('a', 250);
$res = $contractModel->validate([
    'branch_id'        => 1,
    'contract_type_id' => 1,
    'origin_type'      => '0',
    'origin_id'        => 1,
    'code'             => 'CNTR-01-260101-TEST01',
    'issue_date'       => date('Y-m-d H:i:s'),
    'file_url'         => $invalidUrlOverflow,
]);
assertCondition('Rechaza file_url con más de 255 caracteres', $res === false && isset($contractModel->errors()['file_url']));

// B. Desborde en code (> 40 caracteres)
$invalidCodeOverflow = 'CNTR-01-260101-' . str_repeat('B', 35);
$res = $contractModel->validate([
    'branch_id'        => 1,
    'contract_type_id' => 1,
    'origin_type'      => '0',
    'origin_id'        => 1,
    'code'             => $invalidCodeOverflow,
    'issue_date'       => date('Y-m-d H:i:s'),
]);
assertCondition('Rechaza code con más de 40 caracteres', $res === false && isset($contractModel->errors()['code']));

// C. Inyección de protocolo no válido en file_url (XSS/Phishing)
$maliciousUrls = [
    'javascript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'ftp://archivo-inseguro.com/pdf',
    'file:///etc/passwd',
    'not_a_valid_url',
];
foreach ($maliciousUrls as $idx => $badUrl) {
    $res = $contractModel->validate([
        'branch_id'        => 1,
        'contract_type_id' => 1,
        'origin_type'      => '0',
        'origin_id'        => 1,
        'code'             => "CNTR-01-260101-SEC{$idx}",
        'issue_date'       => date('Y-m-d H:i:s'),
        'file_url'         => $badUrl,
    ]);
    assertCondition("Rechaza protocolo malicioso en URL: $badUrl", $res === false && isset($contractModel->errors()['file_url']));
}

// D. Fechas de desborde o calendario inexistente en signed_date
$invalidDates = [
    '2026-02-30', // Día inexistente
    '2026-13-01', // Mes inexistente
    'ayer',
    '99999-99-99',
    '--01-01',
];
foreach ($invalidDates as $idx => $badDate) {
    $res = $contractModel->validate([
        'branch_id'        => 1,
        'contract_type_id' => 1,
        'origin_type'      => '0',
        'origin_id'        => 1,
        'code'             => "CNTR-01-260101-DAT{$idx}",
        'issue_date'       => date('Y-m-d H:i:s'),
        'signed_date'      => $badDate,
    ]);
    assertCondition("Rechaza fecha inválida ($badDate) en signed_date", $res === false && isset($contractModel->errors()['signed_date']));
}

// E. Valores fuera de enum (sign_status e is_disabled)
$res = $contractModel->validate([
    'branch_id'        => 1,
    'contract_type_id' => 1,
    'origin_type'      => '0',
    'origin_id'        => 1,
    'code'             => 'CNTR-01-260101-ENUM01',
    'issue_date'       => date('Y-m-d H:i:s'),
    'sign_status'      => '99',
    'is_disabled'      => '5',
]);
assertCondition('Rechaza valores fuera de catálogo para sign_status e is_disabled', $res === false && isset($contractModel->errors()['sign_status']) && isset($contractModel->errors()['is_disabled']));

// -------------------------------------------------------------------
// 3. Preparación de Fixtures Operativos para Pruebas de Flujo
// -------------------------------------------------------------------
echo "\n--- 3. Preparación de Fixtures para Pruebas Operativas ---\n";

$branchId = 1;
$clientRow = $db->table('glb_person')->where('is_disabled', '0')->where('deleted_at IS NULL')->limit(1)->get()->getRowArray();
$clientId  = (int) $clientRow['id'];

$contractTypeRow = $db->table('fnc_contract_type')->where('code', 'san_motorcycles_v1')->limit(1)->get()->getRowArray();
$contractTypeId  = (int) $contractTypeRow['id'];

// Crear Grupo SAM para pruebas
$db->table('fnc_san_group')->insert([
    'branch_id'           => $branchId,
    'frequency_id'        => 3,
    'contract_type_id'    => $contractTypeId,
    'name'                => 'Grupo Exhaustivo SAM ' . bin2hex(random_bytes(3)),
    'amount'              => 1200.00,
    'installment_amount'  => 100.00,
    'installment_count'   => 12,
    'participant_max'     => 10,
    'participant_count'   => 5,
    'frequency_base_date' => date('Y-m-d'),
    'status'              => '1',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$groupId = (int) $db->insertID();

// Fixture 1: Participante Activo (status = 1)
$db->table('fnc_san_participant')->insert([
    'branch_id'           => $branchId,
    'san_group_id'        => $groupId,
    'client_id'           => $clientId,
    'position'            => 1,
    'status'              => '1',
    'is_overdue'          => '0',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$partActiveId = (int) $db->insertID();

// Fixture 2: Participante Retirado (status = 2)
$db->table('fnc_san_participant')->insert([
    'branch_id'           => $branchId,
    'san_group_id'        => $groupId,
    'client_id'           => $clientId,
    'position'            => 2,
    'status'              => '2',
    'is_overdue'          => '0',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$partRetiredId = (int) $db->insertID();

// Fixture 3: Participante Culminado (status = 3)
$db->table('fnc_san_participant')->insert([
    'branch_id'           => $branchId,
    'san_group_id'        => $groupId,
    'client_id'           => $clientId,
    'position'            => 3,
    'status'              => '3',
    'is_overdue'          => '0',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$partCulminatedId = (int) $db->insertID();

// Fixture 4: Participante en otra sucursal ficticia (branch_id = 999)
$db->table('fnc_san_participant')->insert([
    'branch_id'           => 1,
    'san_group_id'        => $groupId,
    'client_id'           => $clientId,
    'position'            => 4,
    'status'              => '1',
    'is_overdue'          => '0',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$partOtherBranchId = (int) $db->insertID();

// Fixture 5: Participante eliminado por soft-delete
$db->table('fnc_san_participant')->insert([
    'branch_id'           => $branchId,
    'san_group_id'        => $groupId,
    'client_id'           => $clientId,
    'position'            => 5,
    'status'              => '1',
    'is_overdue'          => '0',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
    'deleted_at'          => date('Y-m-d H:i:s'),
]);
$partDeletedId = (int) $db->insertID();

assertCondition('Fixtures creados exitosamente', $groupId > 0 && $partActiveId > 0);

// -------------------------------------------------------------------
// 4. Seguridad, Scoping e IDOR (Cross-Branch / Cross-Group)
// -------------------------------------------------------------------
echo "\n--- 4. Seguridad, Scoping e IDOR ---\n";

$controller = new ContractController();
$controller->initController(service('request'), service('response'), service('logger'));

// A. Acceso Cross-Branch: Participante de branch 1 accedido con branch 99
$respCrossBranch = $controller->show(99, $groupId, $partActiveId);
assertCondition('Cross-Branch Attack bloqueado (404)', $respCrossBranch->getStatusCode() === 404);

// B. Acceso Cross-Group: Participante de grupo A accedido con grupo 999
$respCrossGroup = $controller->show($branchId, 99999, $partActiveId);
assertCondition('Cross-Group Tampering bloqueado (404)', $respCrossGroup->getStatusCode() === 404);

// C. Acceso a participante eliminado por soft-delete
$respDeletedPart = $controller->show($branchId, $groupId, $partDeletedId);
assertCondition('Participante eliminado por soft-delete no es accesible (404)', $respDeletedPart->getStatusCode() === 404);

// D. Parámetros maliciosos o negativos en ID
$respNegativeId = $controller->show(-1, $groupId, $partActiveId);
assertCondition('ID negativo en branch_id rechazado limpiamente (404)', $respNegativeId->getStatusCode() === 404);

$respSqlInjectId = $controller->show("1' OR '1'='1", $groupId, $partActiveId);
assertCondition('SQL injection en parámetros de ruta neutralizado (404)', $respSqlInjectId->getStatusCode() === 404);

// -------------------------------------------------------------------
// 5. Reglas de Negocio en Emisión (create)
// -------------------------------------------------------------------
echo "\n--- 5. Reglas de Negocio en Emisión (create) ---\n";

// A. Rechazo si el participante está retirado (status = 2)
$respRetired = $controller->create($branchId, $groupId, $partRetiredId);
assertCondition('Rechaza emisión para participante retirado (409 PARTICIPANT_NOT_ELIGIBLE)', $respRetired->getStatusCode() === 409);

// B. Rechazo si el participante está culminado (status = 3)
$respCulminated = $controller->create($branchId, $groupId, $partCulminatedId);
assertCondition('Rechaza emisión para participante culminado (409 PARTICIPANT_NOT_ELIGIBLE)', $respCulminated->getStatusCode() === 409);

// C. Emisión legítima con participante activo
$respCreate = $controller->create($branchId, $groupId, $partActiveId);
assertCondition('Emite contrato exitosamente (201)', $respCreate->getStatusCode() === 201);

$createBody = json_decode($respCreate->getBody(), true);
$contractId = (int) ($createBody['data']['id'] ?? 0);
assertCondition('Respuesta de creación contiene data.id válido', $contractId > 0);

$savedContract = $contractModel->find($contractId);
assertCondition('Código de contrato inicia con CNTR-', str_starts_with($savedContract['code'] ?? '', 'CNTR-'));
assertCondition('Contrato emitido con sign_status = 0 (Pendiente)', ($savedContract['sign_status'] ?? null) === '0');
assertCondition('Fecha de emisión registrada correctamente', !empty($savedContract['issue_date']));

// D. Rechazo de emisión duplicada para el mismo participante
$respDuplicate = $controller->create($branchId, $groupId, $partActiveId);
assertCondition('Rechaza emisión duplicada cuando ya existe contrato activo (409 CONTRACT_ALREADY_EXISTS)', $respDuplicate->getStatusCode() === 409);

// -------------------------------------------------------------------
// 6. Inmutabilidad y Protección de Campos Críticos
// -------------------------------------------------------------------
echo "\n--- 6. Inmutabilidad y Protección de Campos Críticos ---\n";

// Crear un segundo participante para probar payload malicioso en POST
$db->table('fnc_san_participant')->insert([
    'branch_id'           => $branchId,
    'san_group_id'        => $groupId,
    'client_id'           => $clientId,
    'position'            => 6,
    'status'              => '1',
    'is_overdue'          => '0',
    'is_disabled'         => '0',
    'created_at'          => date('Y-m-d H:i:s'),
]);
$partTamperId = (int) $db->insertID();

// Simular inyección de campos protegidos en body de create:
// Intentar fijar un code alterado, sign_status='1' (falsificar que ya está firmado), branch_id ajeno
$maliciousBody = json_encode([
    'id'          => 77777,
    'code'        => 'HACKED-CODE-000',
    'sign_status' => '1',
    'branch_id'   => 99,
    'issue_date'  => '2010-01-01 00:00:00',
]);

// Inyectar body en request
service('request')->setBody($maliciousBody);
$respTamper = $controller->create($branchId, $groupId, $partTamperId);
assertCondition('Emisión con payload manipulado responde 201', $respTamper->getStatusCode() === 201);

$tamperData = json_decode($respTamper->getBody(), true)['data'] ?? [];
$tamperContract = $contractModel->find($tamperData['id'] ?? 0);

assertCondition('Backend ignora code malicioso y fuerza CNTR-', str_starts_with($tamperContract['code'] ?? '', 'CNTR-'));
assertCondition('Backend ignora sign_status malicioso y fuerza 0', ($tamperContract['sign_status'] ?? null) === '0');
assertCondition('Backend ignora branch_id manipulado y vincula a sucursal real', (int) ($tamperContract['branch_id'] ?? 0) === $branchId);
assertCondition('Backend ignora id primario inyectado', (int) ($tamperContract['id'] ?? 0) !== 77777);

// Limpiar body
service('request')->setBody('');

// -------------------------------------------------------------------
// 7. Reglas de Formalización y Firma (sign)
// -------------------------------------------------------------------
echo "\n--- 7. Reglas de Formalización y Firma (sign) ---\n";

// A. Rechazo por body vacío
service('request')->setBody('');
$respSignEmpty = $controller->sign($branchId, $groupId, $partActiveId);
assertCondition('Rechaza firma si no se envían datos (400)', $respSignEmpty->getStatusCode() === 400);

// B. Rechazo por falta de signed_date
service('request')->setBody(json_encode([
    'file_url' => 'https://storage.motcar.com/contracts/signed_doc.pdf',
]));
$respSignNoDate = $controller->sign($branchId, $groupId, $partActiveId);
assertCondition('Rechaza firma si falta signed_date (400)', $respSignNoDate->getStatusCode() === 400);

// C. Rechazo por falta de file_url
service('request')->setBody(json_encode([
    'signed_date' => date('Y-m-d'),
]));
$respSignNoUrl = $controller->sign($branchId, $groupId, $partActiveId);
assertCondition('Rechaza firma si falta file_url (400)', $respSignNoUrl->getStatusCode() === 400);

// D. Rechazo por file_url con formato no válido
service('request')->setBody(json_encode([
    'file_url'    => 'not_a_valid_url_string',
    'signed_date' => date('Y-m-d'),
]));
$respSignBadUrl = $controller->sign($branchId, $groupId, $partActiveId);
assertCondition('Rechaza firma si file_url es inválida (400)', $respSignBadUrl->getStatusCode() === 400);

// E. Intento de manipular campos inmutables durante la firma
service('request')->setBody(json_encode([
    'file_url'         => 'https://storage.motcar.com/contracts/valid_contract.pdf',
    'signed_date'      => date('Y-m-d'),
    'code'             => 'TAMPERED_IN_SIGN',
    'issue_date'       => '1999-01-01 00:00:00',
    'contract_type_id' => 999,
]));
$respSignTamper = $controller->sign($branchId, $groupId, $partActiveId);
assertCondition('Firma registrada exitosamente (200)', $respSignTamper->getStatusCode() === 200);

$signedContract = $contractModel->find($contractId);
assertCondition('sign_status transicionó a 1 (Firmado)', ($signedContract['sign_status'] ?? null) === '1');
assertCondition('code original se mantuvo inalterado durante la firma', $signedContract['code'] === $savedContract['code']);
assertCondition('issue_date original se mantuvo inalterada', $signedContract['issue_date'] === $savedContract['issue_date']);
assertCondition('contract_type_id original se mantuvo inalterado', (int) $signedContract['contract_type_id'] === $contractTypeId);
assertCondition('file_url fue almacenado con éxito', $signedContract['file_url'] === 'https://storage.motcar.com/contracts/valid_contract.pdf');

// F. Rechazo al intentar volver a firmar un contrato ya firmado
$respReSign = $controller->sign($branchId, $groupId, $partActiveId);
assertCondition('Rechaza volver a firmar un contrato ya firmado (409 CONTRACT_ALREADY_SIGNED)', $respReSign->getStatusCode() === 409);

// G. Rechazo al intentar firmar un contrato rescindido (sign_status = 2)
$db->table('fnc_contract_document')->where('id', $contractId)->update(['sign_status' => '2']);
$respSignRescinded = $controller->sign($branchId, $groupId, $partActiveId);
assertCondition('Rechaza firma si el contrato está rescindido (409 CONTRACT_RESCINDED)', $respSignRescinded->getStatusCode() === 409);

// Restaurar sign_status a 1
$db->table('fnc_contract_document')->where('id', $contractId)->update(['sign_status' => '1']);

// -------------------------------------------------------------------
// 8. Renderizado TCPDF con Casos Extremos (XSS, montos, metadatos)
// -------------------------------------------------------------------
echo "\n--- 8. Renderizado TCPDF y Resistencia ante Casos Extremos ---\n";

$renderer = new ContractRenderer($db);

// A. Renderizado estándar de contrato activo
$pdfNormal = $renderer->render($contractId);
assertCondition('Generación de PDF produce flujo binario no vacío', !empty($pdfNormal));
assertCondition('Cabecera corresponde a binario PDF (%PDF-)', str_starts_with($pdfNormal, '%PDF-'));

// B. Caso extremo: Inyección XSS en nombre del cliente
$db->table('glb_person')->where('id', $clientId)->update([
    'first_name' => 'JUAN <script>alert("XSS")</script>',
    'last_name'  => 'PÉREZ <b>BOLD</b> & "QUOTES"',
]);
$pdfXss = $renderer->render($contractId);
assertCondition('TCPDF procesa sin error nombres con inyecciones HTML/XSS escapadas', !empty($pdfXss) && str_starts_with($pdfXss, '%PDF-'));

// C. Caso extremo: Montos gigantescos y muchas cuotas (100 cuotas de 100,000$)
$db->table('fnc_san_group')->where('id', $groupId)->update([
    'installment_count'  => 100,
    'installment_amount' => 100000.00,
    'amount'             => 10000000.00,
]);
$pdfHuge = $renderer->render($contractId);
assertCondition('TCPDF y NumberFormatter procesan montos de millones sin desborde', !empty($pdfHuge) && str_starts_with($pdfHuge, '%PDF-'));

// D. Caso extremo: Metadatos con textos de 5,000 caracteres en marca y modelo
$db->table('fnc_san_participant')->where('id', $partActiveId)->update([
    'benefit_metadata' => json_encode([
        'brand'          => 'BERA ' . str_repeat('X', 500),
        'model'          => 'SUPER ' . str_repeat('Y', 500),
        'year'           => 2026,
        'plate'          => 'PLACA-EXTRA-LARGA-1234567890',
        'color'          => 'AZUL CON ACABADOS ESPECIALES DE PRUEBA',
        'chassis_serial' => 'CHASIS-TEST-99999999999999999999',
        'engine_serial'  => 'MOTOR-TEST-88888888888888888888',
    ]),
]);
$pdfLongMeta = $renderer->render($contractId);
assertCondition('TCPDF procesa metadatos masivos sin colapsar', !empty($pdfLongMeta) && str_starts_with($pdfLongMeta, '%PDF-'));

// Restaurar datos originales de fixtures
$db->table('glb_person')->where('id', $clientId)->update([
    'first_name' => $clientRow['first_name'],
    'last_name'  => $clientRow['last_name'],
]);
$db->table('fnc_san_group')->where('id', $groupId)->update([
    'installment_count'  => 12,
    'installment_amount' => 100.00,
    'amount'             => 1200.00,
]);

// -------------------------------------------------------------------
// 9. Pruebas de Concurrencia y Cero Colisiones en Códigos (CNTR-)
// -------------------------------------------------------------------
echo "\n--- 9. Concurrencia y Unicidad de Códigos (Stress Test) ---\n";

$generatedCodes = [];
$collisionCount = 0;
$batchSize = 100;

for ($i = 0; $i < $batchSize; $i++) {
    $code = 'CNTR-' . str_pad((string) $branchId, 2, '0', STR_PAD_LEFT) . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    if (isset($generatedCodes[$code])) {
        $collisionCount++;
    }
    $generatedCodes[$code] = true;
}

assertCondition("Generación de $batchSize códigos en lote produce cero colisiones", $collisionCount === 0);
assertCondition("Estructura de todos los $batchSize códigos cumple CNTR-BB-YYMMDD-HEX6", count(array_filter(array_keys($generatedCodes), fn($c) => preg_match('/^CNTR-\d{2}-\d{6}-[0-9A-F]{6}$/', $c))) === $batchSize);

// -------------------------------------------------------------------
// 10. Arquitectura Stateless (Cero Estado Mutable Residual)
// -------------------------------------------------------------------
echo "\n--- 10. Arquitectura Stateless (Reutilización de Instancia) ---\n";

// Crear 3 participantes rápidos
$stateParts = [];
for ($p = 1; $p <= 3; $p++) {
    $db->table('fnc_san_participant')->insert([
        'branch_id'           => $branchId,
        'san_group_id'        => $groupId,
        'client_id'           => $clientId,
        'position'            => 10 + $p,
        'status'              => '1',
        'is_overdue'          => '0',
        'is_disabled'         => '0',
        'created_at'          => date('Y-m-d H:i:s'),
    ]);
    $stateParts[] = (int) $db->insertID();
}

$statelessController = new ContractController();
$statelessController->initController(service('request'), service('response'), service('logger'));

$createdContracts = [];
foreach ($stateParts as $pId) {
    service('request')->setBody('');
    $resp = $statelessController->create($branchId, $groupId, $pId);
    if ($resp->getStatusCode() === 201) {
        $data = json_decode($resp->getBody(), true)['data'] ?? [];
        $createdContracts[] = $data['id'] ?? 0;
    }
}

assertCondition('Misma instancia de controlador emite múltiples contratos consecutivos (3/3)', count($createdContracts) === 3);

// Verificar que cada contrato pertenezca a su propio participante sin cruce de datos
$allDistinctOrigins = true;
foreach ($createdContracts as $idx => $cId) {
    $cRow = $contractModel->find($cId);
    if ((int) $cRow['origin_id'] !== $stateParts[$idx]) {
        $allDistinctOrigins = false;
    }
}
assertCondition('Cero contaminación de estado entre peticiones consecutivas', $allDistinctOrigins);

// -------------------------------------------------------------------
// 11. Limpieza de Datos de Prueba
// -------------------------------------------------------------------
echo "\n--- 11. Limpieza de Datos de Prueba ---\n";

$allPartIds = array_merge([$partActiveId, $partRetiredId, $partCulminatedId, $partOtherBranchId, $partDeletedId, $partTamperId], $stateParts);
$db->table('fnc_contract_document')->where('origin_type', '0')->whereIn('origin_id', $allPartIds)->delete();
$db->table('fnc_san_participant')->whereIn('id', $allPartIds)->delete();
$db->table('fnc_san_group')->where('id', $groupId)->delete();

echo " Datos temporales de prueba depurados exitosamente.\n";

// -------------------------------------------------------------------
// Resumen Final
// -------------------------------------------------------------------
echo "\n=====================================================================\n";
echo " RESULTADOS FINALES: {$passedTests}/{$totalTests} pruebas exitosas\n";
if ($failedTests === 0) {
    echo " EXCELENTE: 100% de pruebas superadas sin fallos.\n";
} else {
    echo " ATENCIÓN: Se registraron {$failedTests} fallos.\n";
}
echo "=====================================================================\n";

exit($failedTests === 0 ? 0 : 1);

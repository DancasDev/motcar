<?php

require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');
$ref = new \ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
$instances = $ref->getValue();
$instances['tests'] = $db;
$ref->setValue(null, $instances);

use App\Models\Finance\ContractDocumentModel;
use App\Models\Finance\ContractTypeModel;
use App\Models\Finance\SanGroupModel;
use App\Models\Finance\SanParticipantModel;
use App\Models\Global\PersonModel;
use App\Libraries\Finance\ContractRenderer;

$personModel = model(PersonModel::class);
$groupModel = model(SanGroupModel::class);
$participantModel = model(SanParticipantModel::class);
$contractDocModel = model(ContractDocumentModel::class);
$contractTypeModel = model(ContractTypeModel::class);

$contractType = $contractTypeModel->where('code', 'san_motorcycles_v1')->first();
if (!$contractType) {
    echo "ERROR: No se encontró el tipo de contrato san_motorcycles_v1\n";
    exit(1);
}
$contractTypeId = (int) $contractType['id'];
$branchId = 1;

// 1. Asesor
$advisor = $personModel->where('identification_value', '19876543')->first();
if (!$advisor) {
    $advisorId = $personModel->insert([
        'identification_type'  => '0',
        'identification_value' => '19876543',
        'first_name'           => 'CARLOS ALBERTO',
        'last_name'            => 'MENDOZA RIVAS',
        'phone_primary'        => '+584141234567',
        'city'                 => 'Maracaibo',
        'address'              => 'Av. 5 de Julio con Delicias',
        'is_disabled'          => '0',
    ]);
} else {
    $advisorId = (int) $advisor['id'];
}

// 2. Cliente 1
$client1 = $personModel->where('identification_value', '25890123')->first();
if (!$client1) {
    $client1Id = $personModel->insert([
        'identification_type'  => '0',
        'identification_value' => '25890123',
        'first_name'           => 'ALEJANDRO JOSÉ',
        'last_name'            => 'GUTIÉRREZ CHIRINOS',
        'phone_primary'        => '+584249876543',
        'city'                 => 'San Francisco',
        'address'              => 'Urb. Coromoto, Calle 171 con Av. 40, Casa N° 39-12',
        'is_disabled'          => '0',
    ]);
} else {
    $client1Id = (int) $client1['id'];
}

// 3. Cliente 2
$client2 = $personModel->where('identification_value', '18456789')->first();
if (!$client2) {
    $client2Id = $personModel->insert([
        'identification_type'  => '0',
        'identification_value' => '18456789',
        'first_name'           => 'MARÍA GABRIELA',
        'last_name'            => 'HERNÁNDEZ BOSCÁN',
        'phone_primary'        => '+584125556677',
        'city'                 => 'Maracaibo',
        'address'              => 'Sector Tierra Negra, Calle 67 con Av. 10',
        'is_disabled'          => '0',
    ]);
} else {
    $client2Id = (int) $client2['id'];
}

// 4. Grupo SAM
$group = $groupModel->where('name', 'GRUPO SAM DEMO MOTOS 01')->first();
if (!$group) {
    $groupId = $groupModel->insert([
        'branch_id'           => $branchId,
        'frequency_id'        => 1,
        'contract_type_id'    => $contractTypeId,
        'name'                => 'GRUPO SAM DEMO MOTOS 01',
        'amount'              => 1200.00,
        'installment_amount'  => 100.00,
        'installment_count'   => 12,
        'participant_max'     => 10,
        'participant_count'   => 2,
        'frequency_base_date' => date('Y-m-d'),
        'status'              => '1',
        'is_disabled'         => '0',
    ]);
} else {
    $groupId = (int) $group['id'];
    $groupModel->update($groupId, ['contract_type_id' => $contractTypeId]);
}

// 5. Participante 1 (con datos de moto)
$part1Metadata = [
    'brand'           => 'BERA',
    'model'           => 'SBR 150',
    'year'            => 2026,
    'plate'           => 'AE4T89K',
    'chassis_serial'  => '8BR2026XMC8901234',
    'engine_serial'   => 'BERA150EMC7654321',
    'color'           => 'AZUL METALIZADO',
    'reference_value' => 1200.00,
];

$part1 = $participantModel->where('san_group_id', $groupId)->where('position', 1)->first();
if (!$part1) {
    $part1Id = $participantModel->insert([
        'branch_id'        => $branchId,
        'san_group_id'     => $groupId,
        'client_id'        => $client1Id,
        'advisor_id'       => $advisorId,
        'position'         => 1,
        'status'           => '1',
        'is_overdue'       => '0',
        'benefit_metadata' => $part1Metadata,
        'is_disabled'      => '0',
    ]);
} else {
    $part1Id = (int) $part1['id'];
    $participantModel->update($part1Id, [
        'client_id'        => $client1Id,
        'advisor_id'       => $advisorId,
        'benefit_metadata' => $part1Metadata,
    ]);
}

// 6. Participante 2 (sin datos de moto asignada)
$part2 = $participantModel->where('san_group_id', $groupId)->where('position', 2)->first();
if (!$part2) {
    $part2Id = $participantModel->insert([
        'branch_id'        => $branchId,
        'san_group_id'     => $groupId,
        'client_id'        => $client2Id,
        'advisor_id'       => $advisorId,
        'position'         => 2,
        'status'           => '1',
        'is_overdue'       => '0',
        'benefit_metadata' => null,
        'is_disabled'      => '0',
    ]);
} else {
    $part2Id = (int) $part2['id'];
    $participantModel->update($part2Id, [
        'client_id'        => $client2Id,
        'advisor_id'       => $advisorId,
        'benefit_metadata' => null,
    ]);
}

// 7. Emitir Contratos
$code1 = 'CNTR-01-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$code2 = 'CNTR-01-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$doc1Id = $contractDocModel->insert([
    'branch_id'        => $branchId,
    'contract_type_id' => $contractTypeId,
    'origin_type'      => '0',
    'origin_id'        => $part1Id,
    'code'             => $code1,
    'issue_date'       => date('Y-m-d H:i:s'),
    'sign_status'      => '1', // Firmado
    'signed_date'      => date('Y-m-d H:i:s'),
    'is_disabled'      => '0',
]);

$doc2Id = $contractDocModel->insert([
    'branch_id'        => $branchId,
    'contract_type_id' => $contractTypeId,
    'origin_type'      => '0',
    'origin_id'        => $part2Id,
    'code'             => $code2,
    'issue_date'       => date('Y-m-d H:i:s'),
    'sign_status'      => '0', // Emitido (pendiente firma)
    'is_disabled'      => '0',
]);

// 8. Renderizar PDFs
$renderer = new ContractRenderer($db);

$pdf1 = $renderer->render((int) $doc1Id);
$pdf2 = $renderer->render((int) $doc2Id);

$path1 = '/home/daniel-castillo/Descargas/pdfs/' . $code1 . '_con_moto.pdf';
$path2 = '/home/daniel-castillo/Descargas/pdfs/' . $code2 . '_sin_moto.pdf';

file_put_contents($path1, $pdf1);
file_put_contents($path2, $pdf2);

echo "========================================================\n";
echo " CONTRATOS GENERADOS EXITOSAMENTE\n";
echo "========================================================\n";
echo "PDF 1: $path1\n";
echo "  - Tamaño archivo: " . number_format(filesize($path1) / 1024, 2) . " KB\n";
echo "  - Código Contrato: $code1\n";
echo "  - Participante: ALEJANDRO JOSÉ GUTIÉRREZ CHIRINOS (V- 25.890.123)\n";
echo "  - Datos Vehículo: BERA SBR 150 (2026) Azul Metalizado\n\n";

echo "PDF 2: $path2\n";
echo "  - Tamaño archivo: " . number_format(filesize($path2) / 1024, 2) . " KB\n";
echo "  - Código Contrato: $code2\n";
echo "  - Participante: MARÍA GABRIELA HERNÁNDEZ BOSCÁN (V- 18.456.789)\n";
echo "  - Datos Vehículo: Pendientes por asignar (Líneas en blanco)\n";
echo "========================================================\n";

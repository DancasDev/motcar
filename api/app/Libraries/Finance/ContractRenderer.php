<?php

namespace App\Libraries\Finance;

use Config\Database;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use NumberFormatter;
use TCPDF;

/**
 * Renderizador de contratos físicos y visualización de documentos en PDF.
 *
 * Carga la plantilla modular en app/Views/Contracts/{code}.php y genera
 * la salida PDF mediante TCPDF preservando el diseño y redacción original.
 */
class ContractRenderer
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Renderiza el contrato en binario PDF.
     *
     * @param int $contractId Identificador en fnc_contract_document.
     * @return string Contenido binario del PDF.
     */
    public function render(int $contractId): string
    {
        $contract = $this->getContractData($contractId);
        if (!$contract) {
            throw new RuntimeException('Contrato no encontrado.');
        }

        $viewData = $this->buildViewData($contract);
        $typeCode = $contract['contract_type_code'] ?? 'san_motorcycles_v1';

        // Resolución de la vista por código de tipo de contrato
        $viewFile = 'Contracts/' . $typeCode;
        $html = view($viewFile, $viewData);

        return $this->generatePdf(
            $html,
            $viewData['contract_code'],
            $viewData['header_date_html'],
            $viewData['logo_path']
        );
    }

    /**
     * Consulta los datos consolidados del contrato en una sola consulta.
     */
    public function getContractData(int $contractId): ?array
    {
        return $this->db->table('fnc_contract_document as cd')
            ->select('
                cd.id, cd.branch_id, cd.contract_type_id, cd.origin_type, cd.origin_id,
                cd.code as contract_code, cd.issue_date, cd.signed_date, cd.sign_status, cd.file_url,
                ct.code as contract_type_code, ct.name as contract_type_name,
                sp.position as participant_position, sp.status as participant_status, sp.benefit_metadata,
                sp.referrer_id, sp.frequency_id as participant_frequency_id,
                sp.installment_amount as participant_installment_amount, sp.installment_count as participant_installment_count,
                sg.id as group_id, sg.name as group_name,
                sg.installment_amount, sg.installment_count, sg.participant_max,
                sg.frequency_id as group_frequency_id,
                p.first_name, p.last_name, p.identification_type, p.identification_value, p.address, p.city,
                ref.first_name as referrer_first_name, ref.last_name as referrer_last_name,
                b.name as branch_name
            ')
            ->join('fnc_contract_type as ct', 'cd.contract_type_id = ct.id', 'left')
            ->join('fnc_san_participant as sp', "cd.origin_type = '0' AND cd.origin_id = sp.id", 'left')
            ->join('fnc_san_group as sg', 'sp.san_group_id = sg.id', 'left')
            ->join('glb_person as p', 'sp.client_id = p.id', 'left')
            ->join('glb_person as ref', 'sp.referrer_id = ref.id', 'left')
            ->join('glb_branch as b', 'cd.branch_id = b.id', 'left')
            ->where('cd.id', $contractId)
            ->where('cd.deleted_at IS NULL')
            ->limit(1)
            ->get()
            ->getRowArray();
    }

    /**
     * Prepara los datos formateados para la plantilla respetando la redacción original.
     */
    private function buildViewData(array $contract): array
    {
        $branchCity = 'San Francisco';

        // 1. Fechas para encabezado
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $ts = !empty($contract['issue_date']) ? strtotime($contract['issue_date']) : time();
        $day = date('j', $ts);
        $month = $months[(int) date('n', $ts)] ?? date('F', $ts);
        $year = date('Y', $ts);
        $headerDateHtml = "{$branchCity}, <b>{$day}</b> de <b>{$month}</b> de <b>{$year}</b>";

        // 2. Datos del cliente
        helper('text');
        $clientFullName       = mb_strtoupper(trim(($contract['first_name'] ?? '') . ' ' . ($contract['last_name'] ?? '')), 'UTF-8');
        $idType               = $contract['identification_type'] ?? '0';
        $idVal                = (string) ($contract['identification_value'] ?? '');
        $clientIdFormatted    = format_identification($idType, $idVal);
        $clientIdentification = $clientIdFormatted;
        $clientNationality    = ($idType === '0' || $idType === 'V') ? 'venezolano' : 'extranjero';
        $clientCity           = !empty($contract['city']) ? mb_strtoupper($contract['city'], 'UTF-8') : 'MARACAIBO';

        // 3. Beneficio / Datos de la moto (Obligatorios: marca, modelo y año con XXX si no están asignados; opcionales condicionados si están definidos)
        $benefitRaw = $contract['benefit_metadata'] ?? null;
        $benefit = is_array($benefitRaw) ? $benefitRaw : (json_decode($benefitRaw ?? '', true) ?: []);
        $vehicleBrand   = !empty($benefit['brand']) ? mb_strtoupper($benefit['brand'], 'UTF-8') : 'XXX';
        $vehicleModel   = !empty($benefit['model']) ? mb_strtoupper($benefit['model'], 'UTF-8') : 'XXX';
        $vehicleYear    = !empty($benefit['year']) ? (string) $benefit['year'] : 'XXX';
        $vehiclePlate   = !empty($benefit['plate']) ? mb_strtoupper($benefit['plate'], 'UTF-8') : null;
        $vehicleColor   = !empty($benefit['color']) ? mb_strtoupper($benefit['color'], 'UTF-8') : null;
        $vehicleChassis = !empty($benefit['chassis_serial']) ? mb_strtoupper($benefit['chassis_serial'], 'UTF-8') : null;
        $vehicleEngine  = !empty($benefit['engine_serial']) ? mb_strtoupper($benefit['engine_serial'], 'UTF-8') : null;

        // 4. Cuotas y frecuencia
        $installmentCount  = (int) ($contract['participant_installment_count'] ?? $contract['installment_count'] ?? 1);
        $installmentAmount = (float) ($contract['participant_installment_amount'] ?? $contract['installment_amount'] ?? 0.0);
        $totalAmount       = round($installmentCount * $installmentAmount, 2);
        $refValue          = !empty($benefit['reference_value']) ? (float) $benefit['reference_value'] : $totalAmount;

        $freqId = $contract['participant_frequency_id'] ?? $contract['group_frequency_id'] ?? null;
        $freqCode = 'monthly';
        if ($freqId) {
            $freqRow = $this->db->table('fnc_frequency')->select('code')->where('id', $freqId)->limit(1)->get()->getRowArray();
            if ($freqRow) {
                $freqCode = $freqRow['code'];
            }
        }

        $frequencyNames = [
            'weekly'   => 'semanal',
            'biweekly' => 'quincenal',
            'monthly'  => 'mensual',
        ];
        $frequencyName = $frequencyNames[$freqCode] ?? 'mensual';

        switch ($freqCode) {
            case 'weekly':
                $periodMonths = (int) ceil($installmentCount / 4);
                break;
            case 'biweekly':
                $periodMonths = (int) ceil($installmentCount / 2);
                break;
            case 'monthly':
            default:
                $periodMonths = $installmentCount;
                break;
        }

        $groupMax = !empty($contract['participant_max']) ? (int) $contract['participant_max'] : 10;

        // 5. Formateo de números y montos en palabras
        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $installmentCountWords  = $formatter->format($installmentCount);
        $periodMonthsWords      = $formatter->format($periodMonths);
        $installmentAmountWords = mb_strtoupper($formatter->format((int) $installmentAmount), 'UTF-8');
        $totalAmountWords       = mb_strtoupper($formatter->format((int) $totalAmount), 'UTF-8');

        $installmentAmountFormatted = (fmod($installmentAmount, 1.0) == 0.0) ? (string) ((int) $installmentAmount) : number_format($installmentAmount, 2, '.', '');
        $totalAmountFormatted       = (fmod($totalAmount, 1.0) == 0.0) ? (string) ((int) $totalAmount) : number_format($totalAmount, 2, '.', '');
        $referenceValueFormatted    = (fmod($refValue, 1.0) == 0.0) ? (string) ((int) $refValue) : number_format($refValue, 2, '.', '');

        $logoPath = APPPATH . 'Views/Contracts/assets/motcar_logo_clean.png';
        if (!file_exists($logoPath)) {
            $logoPath = APPPATH . 'Views/Contracts/assets/motcar_logo.jpeg';
        }

        return [
            'contract_code'               => $contract['contract_code'],
            'header_date_html'            => $headerDateHtml,
            'branch_city'                 => $branchCity,
            'client_full_name'            => $clientFullName,
            'client_nationality'          => $clientNationality,
            'client_identification'       => $clientIdentification,
            'client_id_formatted'         => $clientIdFormatted,
            'client_city'                 => $clientCity,
            'vehicle_brand'               => $vehicleBrand,
            'vehicle_model'               => $vehicleModel,
            'vehicle_year'                => $vehicleYear,
            'vehicle_plate'               => $vehiclePlate,
            'vehicle_color'               => $vehicleColor,
            'vehicle_chassis'             => $vehicleChassis,
            'vehicle_engine'              => $vehicleEngine,
            'installment_count'           => $installmentCount,
            'installment_count_words'     => $installmentCountWords,
            'period_months'               => $periodMonths,
            'period_months_words'         => $periodMonthsWords,
            'frequency_name'              => $frequencyName,
            'installment_amount'          => $installmentAmount,
            'installment_amount_words'    => $installmentAmountWords,
            'installment_amount_formatted'=> $installmentAmountFormatted,
            'total_amount'                => $totalAmount,
            'total_amount_words'          => $totalAmountWords,
            'total_amount_formatted'      => $totalAmountFormatted,
            'reference_value_formatted'   => $referenceValueFormatted,
            'group_max'                   => $groupMax,
            'logo_path'                   => $logoPath,
        ];
    }

    /**
     * Instancia TCPDF y genera el binario PDF en hoja tamaño carta con cabecera y márgenes simétricos.
     */
    private function generatePdf(string $html, string $contractCode, string $headerDateHtml, string $logoPath): string
    {
        // Pase 1: Determinar el número total exacto de páginas para alineación perfecta a la derecha
        $pdfPass1 = $this->buildPdfInstance($html, $contractCode, $headerDateHtml, $logoPath, null);
        $totalPages = $pdfPass1->getNumPages();

        // Pase 2: Renderizado final con el conteo de páginas exacto sin desfase de alias
        $pdfPass2 = $this->buildPdfInstance($html, $contractCode, $headerDateHtml, $logoPath, $totalPages);
        return $pdfPass2->Output('', 'S');
    }

    /**
     * Construye una instancia configurada de TCPDF con encabezados alineados y márgenes simétricos.
     */
    private function buildPdfInstance(string $html, string $contractCode, string $headerDateHtml, string $logoPath, ?int $totalPages): TCPDF
    {
        $pdf = new class('P', 'mm', 'LETTER', true, 'UTF-8', false) extends TCPDF {
            public string $contractCode = '';
            public string $headerDateHtml = '';
            public string $logoPath = '';
            public ?int $totalPages = null;

            public function Header()
            {
                $left = 15;
                $right = 15;
                $top = 12;
                $pageWidth = 215.9;

                // Logo alineado al extremo izquierdo del encabezado
                if (file_exists($this->logoPath)) {
                    $this->Image($this->logoPath, $left, $top, 44, 0, 'PNG');
                }

                // Datos de fecha, código y página alineados al extremo derecho del encabezado
                $this->SetY($top);
                $pageCountStr = $this->totalPages !== null ? (string) $this->totalPages : $this->getAliasNbPages();
                $headerHtml = '
                <table cellpadding="0" cellspacing="0" style="width: 100%;">
                    <tr>
                        <td style="text-align: right; font-family: Arial, helvetica, sans-serif; font-size: 11pt; line-height: 1.4; color: #000;">
                            ' . $this->headerDateHtml . '<br>
                            <strong>' . $this->contractCode . '</strong><br>
                            Página <strong>' . $this->PageNo() . '</strong> de <strong>' . $pageCountStr . '</strong>
                        </td>
                    </tr>
                </table>';

                $rightWidth = 130;
                $rightX = $pageWidth - $right - $rightWidth;
                $this->setCellPaddings(0, 0, 0, 0);
                $this->writeHTMLCell($rightWidth, 25, $rightX, $top, $headerHtml, 0, 0, false, true, 'R');
            }

            public function Footer()
            {
                // Sin pie de página inferior (la numeración se encuentra en la cabecera como en el diseño original)
            }
        };

        $pdf->contractCode   = $contractCode;
        $pdf->headerDateHtml = $headerDateHtml;
        $pdf->logoPath       = $logoPath;
        $pdf->totalPages     = $totalPages;

        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(22, 42, 22); // Márgenes del cuerpo simétricos (22mm izq/der) y 42mm superior
        $pdf->SetHeaderMargin(12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf;
    }
}

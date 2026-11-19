<?php

namespace App\Libraries\Accounting;

use NumberFormatter;
use TCPDF;

/**
 * Renderizador de recibos de cobro y recaudación física/digital en PDF (Formato Carta / Letter).
 *
 * Transforma el detalle consolidado de acc_receipt generado por ReceiptModel::getDetailByCache()
 * en un documento PDF de formato Carta (Letter: 215.9 mm × 279.4 mm) mediante TCPDF
 * en las plantillas visuales de app/Views/Receipts/.
 */
class ReceiptRenderer
{
    private NumberFormatter $numFormatter;

    public function __construct()
    {
        $this->numFormatter = new NumberFormatter('es_VE', NumberFormatter::DECIMAL);
        $this->numFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $this->numFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
    }

    /**
     * Renderiza el comprobante consolidado en binario PDF (Formato Carta).
     *
     * @param array $detail Detalle estructurado de ReceiptModel::getDetailByCache().
     * @param string $template Nombre de la vista en app/Views/Receipts/ (default: 'formal').
     * @return string Contenido binario del PDF.
     */
    public function render(array $detail, string $template = 'formal'): string
    {
        $viewData = $this->buildViewData($detail);
        $tpl      = file_exists(APPPATH . 'Views/Receipts/' . $template . '.php') ? $template : 'formal';
        $html     = view('Receipts/' . $tpl, $viewData);

        return $this->generatePdf(
            $html,
            $viewData['receipt_code'],
            $viewData['header_date_html'],
            (string) ($viewData['logo_path'] ?? '')
        );
    }

    /**
     * Construye y formatea todos los datos requeridos por la plantilla visual.
     */
    public function buildViewData(array $detail): array
    {
        $companyName    = 'FINANCIAMIENTOS MOT/CAR C.A';
        $companyRif     = 'J-506980371';
        $branchName     = $detail['branch_name'] ?? 'Sucursal Principal';
        $branchAddress  = 'San Francisco, Estado Zulia - Venezuela';
        $branchCity     = $detail['client_city'] ?? 'San Francisco';

        // Fecha de emisión dinámica para el encabezado (formato contratos Motcar)
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $ts = time(); // Fecha de cuando se genera el PDF
        $day = date('j', $ts);
        $month = $months[(int) date('n', $ts)] ?? date('F', $ts);
        $year = date('Y', $ts);
        $headerDateHtml = "{$branchCity}, <b>{$day}</b> de <b>{$month}</b> de <b>{$year}</b>";

        $logoPath = APPPATH . 'Views/Contracts/assets/motcar_logo_clean.png';
        if (!file_exists($logoPath)) {
            $logoPath = APPPATH . 'Views/Contracts/assets/motcar_logo.jpeg';
        }
        $logoAvailable = file_exists($logoPath) ? $logoPath : null;

        // Estado del comprobante
        $statusRaw  = (string) ($detail['status'] ?? '0');
        $statusName = match ($statusRaw) {
            '1'     => 'CONFIRMADO',
            '2'     => 'ANULADO',
            default => 'BORRADOR',
        };
        $statusColor = match ($statusRaw) {
            '1'     => '#007744',
            '2'     => '#cc0000',
            default => '#777777',
        };

        // Identificación del cliente
        helper('text');
        $idType            = $detail['client_identification_type'] ?? '0';
        $idNumber          = (string) ($detail['client_identification_value'] ?? '');
        $clientIdFormatted = format_identification($idType, $idNumber) ?: 'S/I';

        $clientName = trim(($detail['client_first_name'] ?? '') . ' ' . ($detail['client_last_name'] ?? ''));
        if (empty($clientName)) {
            $clientName = 'Cliente no especificado';
        }

        $collectorName = trim(($detail['collector_first_name'] ?? '') . ' ' . ($detail['collector_last_name'] ?? ''));
        if (empty($collectorName)) {
            $collectorName = 'Caja Central';
        }

        // Formateo de ítems / conceptos pagados
        $itemsFormatted     = [];
        $totalDeudaUsd      = 0.0;
        $totalAmortizadoUsd = 0.0;
        foreach ($detail['items'] ?? [] as $it) {
            $deuda      = (float) ($it['receivable_total_amount'] ?? 0.0);
            $amortizado = (float) ($it['amount'] ?? 0.0);
            $rawDesc    = $it['receivable_description'] ?? 'Concepto';

            if (!empty($it['display_description'])) {
                $description = (string) $it['display_description'];
            } else {
                $type = (string) ($it['type'] ?? '0');
                $suffix = match ($type) {
                    '2'     => ' (abono)',
                    '3'     => ' (restante)',
                    default => '',
                };
                $description = $rawDesc . $suffix;
            }

            $totalDeudaUsd      += $deuda;
            $totalAmortizadoUsd += $amortizado;

            $itemsFormatted[] = [
                'description'          => $description,
                'amount_formatted'     => '$ ' . $this->formatAmount($amortizado),
                'deuda_formatted'      => '$ ' . $this->formatAmount($deuda),
                'amortizado_formatted' => '$ ' . $this->formatAmount($amortizado),
                'total_amount_formatted' => '$ ' . $this->formatAmount($deuda),
            ];
        }

        // Formateo de medios de pago
        $paymentsFormatted = [];
        $totalPaymentsUsd  = (float) ($detail['totals']['payments_usd'] ?? 0.0);
        $totalPaymentsVes  = 0.0;
        $hasVesPayments    = false;

        foreach ($detail['payments'] ?? [] as $pay) {
            $currencyCode = $pay['currency_code'] ?? 'USD';
            $rate         = (float) ($pay['exchange_rate'] ?? 1.0);
            $nominal      = (float) ($pay['amount'] ?? 0.0);
            $usdEquiv     = (float) ($pay['amount_usd'] ?? 0.0);

            if ($currencyCode === 'VES') {
                $totalPaymentsVes += $nominal;
                $hasVesPayments    = true;
            }

            $accName = $pay['payment_account_name'] ?? 'Caja';
            $catName = $pay['payment_category_name'] ?? '';
            $description = !empty($catName) ? "{$accName} ({$catName})" : $accName;

            $subFormatted = '';
            if ($currencyCode !== 'USD') {
                $symbol = $pay['currency_symbol'] ?? 'Bs.';
                $subFormatted = $symbol . ' ' . $this->formatAmount($nominal);
            }

            $paymentsFormatted[] = [
                'description'          => $description,
                'account_name'         => $accName,
                'category_name'        => $catName,
                'currency_code'        => $currencyCode,
                'is_usd'               => ($currencyCode === 'USD'),
                'reference'            => $pay['reference'] ?? 'S/R',
                'amount_formatted'     => ($pay['currency_symbol'] ?? '$') . ' ' . $this->formatAmount($nominal),
                'amount_usd_formatted' => '$ ' . $this->formatAmount($usdEquiv),
                'sub_amount_formatted' => $subFormatted,
            ];
        }

        // Saldo a favor y balance
        $creditUsd  = (float) ($detail['totals']['credit_usd'] ?? 0.0);
        $balanceUsd = (float) ($detail['totals']['balance_usd'] ?? 0.0);

        return [
            'company_name'        => $companyName,
            'company_rif'         => $companyRif,
            'branch_name'         => $branchName,
            'branch_address'      => $branchAddress,
            'logo_path'           => $logoAvailable,
            'header_date_html'    => $headerDateHtml,
            'receipt_code'        => $detail['code'] ?? 'RECP-00-000000-000000',
            'receipt_date'        => date('d/m/Y h:i A', strtotime($detail['date'] ?? date('Y-m-d H:i:s'))),
            'status_name'         => $statusName,
            'status_color'        => $statusColor,
            'client_name'         => $clientName,
            'client_id_formatted' => $clientIdFormatted,
            'client_phone'        => $detail['client_phone'] ?? '',
            'client_address'      => trim(($detail['client_address'] ?? '') . ' ' . ($detail['client_city'] ?? '')),
            'collector_name'      => $collectorName,
            'notes'               => $detail['notes'] ?? '',
            'items'               => $itemsFormatted,
            'payments'            => $paymentsFormatted,
            'totals'              => [
                'items_deuda_usd_formatted'      => '$ ' . $this->formatAmount($totalDeudaUsd),
                'items_amortizado_usd_formatted' => '$ ' . $this->formatAmount($totalAmortizadoUsd),
                'items_usd_formatted'            => '$ ' . $this->formatAmount($totalAmortizadoUsd),
                'payments_usd_formatted'         => '$ ' . $this->formatAmount($totalPaymentsUsd),
                'payments_ves_formatted'         => $hasVesPayments ? 'Bs. ' . $this->formatAmount($totalPaymentsVes) : null,
                'credit_usd_formatted'           => '$ ' . $this->formatAmount($creditUsd),
                'has_credit'                     => $creditUsd > 0.00,
                'balance_usd'                    => $balanceUsd,
            ],
        ];
    }

    /**
     * Genera el binario PDF en hoja tamaño carta con cabecera institucional idéntica a contratos.
     */
    private function generatePdf(string $html, string $receiptCode, string $headerDateHtml, string $logoPath): string
    {
        // Pase 1: Determinar el número total exacto de páginas
        $pdfPass1 = $this->buildPdfInstance($html, $receiptCode, $headerDateHtml, $logoPath, null);
        $totalPages = $pdfPass1->getNumPages();

        // Pase 2: Renderizado final con el conteo de páginas exacto
        $pdfPass2 = $this->buildPdfInstance($html, $receiptCode, $headerDateHtml, $logoPath, $totalPages);
        return $pdfPass2->Output('', 'S');
    }

    /**
     * Construye una instancia configurada de TCPDF con encabezados alineados y márgenes simétricos.
     */
    private function buildPdfInstance(string $html, string $receiptCode, string $headerDateHtml, string $logoPath, ?int $totalPages): TCPDF
    {
        $pdf = new class('P', 'mm', 'LETTER', true, 'UTF-8', false) extends TCPDF {
            public string $receiptCode = '';
            public string $headerDateHtml = '';
            public string $logoPath = '';
            public ?int $totalPages = null;

            public function Header()
            {
                $left = 16;
                $right = 16;
                $top = 12;
                $pageWidth = 215.9;

                // Logo alineado al extremo izquierdo del encabezado
                if (!empty($this->logoPath) && file_exists($this->logoPath)) {
                    $this->Image($this->logoPath, $left, $top, 44, 0, 'PNG');
                }

                // Datos de fecha, código y página alineados al extremo derecho del encabezado
                $this->SetY($top);
                $pageCountStr = $this->totalPages !== null ? (string) $this->totalPages : $this->getAliasNbPages();
                $headerHtml = '
                <table cellpadding="0" cellspacing="0" style="width: 100%;">
                    <tr>
                        <td style="text-align: right; font-family: helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.4; color: #1e293b;">
                            ' . $this->headerDateHtml . '<br>
                            <strong style="font-size: 10.5pt; color: #0d3b66;">' . $this->receiptCode . '</strong><br>
                            Página <strong>' . $this->PageNo() . '</strong> de <strong>' . $pageCountStr . '</strong>
                        </td>
                    </tr>
                </table>';

                $rightWidth = 140;
                $rightX = $pageWidth - $right - $rightWidth;
                $this->setCellPaddings(0, 0, 0, 0);
                $this->writeHTMLCell($rightWidth, 24, $rightX, $top, $headerHtml, 0, 0, false, true, 'R');
            }

            public function Footer()
            {
                $pageWidth = 215.9;
                $left = 15;
                $right = 15;

                // Línea divisoria superior del pie de página
                $this->SetY(-14);
                $this->Line($left, $this->GetY(), $pageWidth - $right, $this->GetY(), ['width' => 0.2, 'color' => [203, 213, 225]]);

                // Texto hardcodeado: documento administrativo interno, no válido como factura fiscal
                $this->SetY(-12);
                $this->SetFont('helvetica', '', 6.8);
                $this->SetTextColor(100, 116, 139);
                $disclaimer = 'Documento de control administrativo interno para la recepción y recaudación de fondos. No válido como factura fiscal.';
                $this->Cell(0, 8, $disclaimer, 0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        };

        $pdf->receiptCode    = $receiptCode;
        $pdf->headerDateHtml = $headerDateHtml;
        $pdf->logoPath       = $logoPath;
        $pdf->totalPages     = $totalPages;

        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(15, 39, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 11);
        $pdf->setCellPaddings(0, 0, 0, 0);

        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf;
    }

    private function formatAmount(float $amount): string
    {
        return $this->numFormatter->format($amount) ?: number_format($amount, 2, ',', '.');
    }
}

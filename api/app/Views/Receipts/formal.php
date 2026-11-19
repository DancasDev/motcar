<?php
/**
 * ============================================================================
 * MOTCAR — RECIBO DE PAGO (FORMATO FORMAL CARTA)
 * ============================================================================
 * 
 * ----------------------------------------------------------------------------
 * AJUSTES DE ESPACIADOS Y MÁRGENES (Modifica estos valores directamente aquí):
 * ----------------------------------------------------------------------------
 */

?>
<style>
    .recp-body {
        font-family: helvetica, Arial, sans-serif;
        font-size: 8.5pt;
        color: #1e293b;
        line-height: 1.3;
    }
    .recp-title {
        text-align: center;
        font-size: 16pt;
        font-weight: bold;
        color: #0f172a;
        letter-spacing: 1.5px;
    }
    .recp-section-title {
        font-size: 8.5pt;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .recp-ticket  {
        width: 100%;
        border-collapse: collapse;
    }
    .recp-ticket th {
        font-size: 8.5pt;
        font-weight: bold;
    }
    .recp-ticket td {
        font-size: 8.5pt;
    }
    .recp-table {
        width: 100%;
        border-collapse: collapse;
    }
    .recp-table th {
        background-color: #1e293b;
        color: #ffffff;
        font-size: 8.5pt;
        font-weight: bold;
        border: 0.5px solid #94a3b8;
    }
    .recp-table td {
        font-size: 8.5pt;
        border: 0.5px solid #94a3b8;
    }
</style>

<div class="recp-body">

    <!-- TÍTULO PRINCIPAL (UBICADO DEBAJO DEL ENCABEZADO) -->
    <div style="text-align: center;">
        <span class="recp-title">RECIBO DE PAGO</span>
    </div>
    <div style="font-size: 1px; height: 1px; line-height: 1px;">&nbsp;</div>

    <!-- FICHA SUPERIOR EN MITADES SIMÉTRICAS -->
    <div>
        <table class="recp-ticket" cellpadding="3.5" cellspacing="0">
            <tr>
                <!-- MITAD IZQUIERDA: DATOS DEL CLIENTE -->
                <td style="width: 49%; border: 0.5px dashed #64748b; vertical-align: top;">
                    <table cellpadding="2" cellspacing="0" style="width: 100%; line-height: 1.3;">
                        <tr>
                            <td style="width: 25%; font-weight: bold; color: #475569; border: none;">CLIENTE:</td>
                            <td style="width: 75%; font-weight: normal; color: #0f172a; border: none;"><?= esc($client_name) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; color: #475569; border: none;">R.I.F. / C.I.:</td>
                            <td style="font-weight: normal; color: #0f172a; border: none;"><?= esc($client_id_formatted) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; color: #475569; border: none;">TELÉFONO:</td>
                            <td style="font-weight: normal; color: #0f172a; border: none;"><?= esc($client_phone) ?: 'No especificado' ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; color: #475569; border: none;">DIRECCIÓN:</td>
                            <td style="font-weight: normal; color: #0f172a; border: none;"><?= esc($client_address) ?: 'No especificada' ?></td>
                        </tr>
                    </table>
                </td>

                <td style="width: 2%; border: none;"></td>

                <!-- MITAD DERECHA: DATOS DE OPERACIÓN Y NOTAS -->
                <td style="width: 49%; border: 0.5px dashed #64748b; vertical-align: top;">
                    <table cellpadding="2" cellspacing="0" style="width: 100%; line-height: 1.3;">
                        <tr>
                            <td style="width: 32%; font-weight: bold; color: #475569; border: none;">SUCURSAL:</td>
                            <td style="width: 68%; font-weight: normal; color: #0f172a; border: none;"><?= esc($branch_name) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; color: #475569; border: none;">CAJERO:</td>
                            <td style="font-weight: normal; color: #0f172a; border: none;"><?= esc($collector_name) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; color: #475569; border: none;">FECHA EMISIÓN:</td>
                            <td style="font-weight: normal; color: #0f172a; border: none;"><?= esc($receipt_date) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; color: #475569; border: none;">OBSERVACIÓN:</td>
                            <td style="font-weight: normal; color: #0f172a; border: none;"><?= !empty($notes) ? esc($notes) : 'Cancelación procesada a satisfacción' ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    <div style="font-size: 1px; height: 1px; line-height: 1px;">&nbsp;</div>

    <!-- 1. CONCEPTOS A PAGAR -->
    <div class="recp-section-title">
        1. Conceptos a Pagar
    </div>
    <div>
        <table class="recp-table" cellpadding="2" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 6%; text-align: center; border: 0.5px solid #94a3b8;">#</th>
                    <th style="width: 72%; text-align: left; border: 0.5px solid #94a3b8;">DESCRIPCIÓN</th>
                    <th style="width: 22%; text-align: right; border: 0.5px solid #94a3b8;">MONTO</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                    <tr>
                        <td style="width: 6%; text-align: center; color: #64748b; border: 0.5px solid #94a3b8;"><?= sprintf('%02d', $idx + 1) ?></td>
                        <td style="width: 72%; font-weight: bold; color: #0f172a; border: 0.5px solid #94a3b8;"><?= esc($it['description']) ?></td>
                        <td style="width: 22%; text-align: right; font-weight: bold; color: #0f172a; border: 0.5px solid #94a3b8;"><?= esc($it['amount_formatted']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <!-- FILA TOTAL: SOLO QUITAR BORDE IZQUIERDO E INFERIOR A LA CELDA TOTAL -->
                <tr>
                    <td colspan="2" style="border-top: 0.5px solid #94a3b8; border-right: 0.5px solid #94a3b8; border-bottom: 0px solid #ffffff; border-left: 0px solid #ffffff; text-align: right; font-weight: bold; color: #0f172a;">TOTAL</td>
                    <td style="border: 0.5px solid #94a3b8; text-align: right; font-weight: bold; color: #0f172a;"><?= esc($totals['items_amortizado_usd_formatted']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 2. FORMAS DE PAGO -->
    <div class="recp-section-title">
        2. Formas de Pago
    </div>
    <div>
        <table class="recp-table" cellpadding="2" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 6%; text-align: center; border: 0.5px solid #94a3b8;">#</th>
                    <th style="width: 72%; text-align: left; border: 0.5px solid #94a3b8;">DESCRIPCIÓN</th>
                    <th style="width: 22%; text-align: right; border: 0.5px solid #94a3b8;">MONTO</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $idx => $pay): ?>
                    <tr>
                        <td style="width: 6%; text-align: center; color: #64748b; border: 0.5px solid #94a3b8;"><?= sprintf('%02d', $idx + 1) ?></td>
                        <td style="width: 72%; color: #0f172a; border: 0.5px solid #94a3b8;">
                            <strong><?= esc($pay['description']) ?></strong>
                        </td>
                        <td style="width: 22%; text-align: right; border: 0.5px solid #94a3b8;">
                            <strong style="color: #0f172a;"><?= esc($pay['amount_usd_formatted']) ?></strong>
                            <?php if (!$pay['is_usd'] && !empty($pay['sub_amount_formatted'])): ?>
                                <br><span style="font-size: 8pt; color: #64748b; font-weight: normal;"><?= esc($pay['sub_amount_formatted']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <!-- FILA TOTAL: SOLO QUITAR BORDE IZQUIERDO E INFERIOR A LA CELDA TOTAL -->
                <tr>
                    <td colspan="2" style="border-top: 0.5px solid #94a3b8; border-right: 0.5px solid #94a3b8; border-bottom: 0px solid #ffffff; border-left: 0px solid #ffffff; text-align: right; font-weight: bold; color: #0f172a;">TOTAL</td>
                    <td style="border: 0.5px solid #94a3b8; text-align: right; font-weight: bold; color: #0f172a;"><?= esc($totals['payments_usd_formatted']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- 3. RESUMEN -->
    <div class="recp-section-title">
        3. Resumen
    </div>
    <div>
        <table class="recp-table" cellpadding="2" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 34%; text-align: center; border: 0.5px solid #94a3b8;">TOTAL A PAGAR</th>
                    <th style="width: 33%; text-align: center; border: 0.5px solid #94a3b8;">TOTAL PAGADO</th>
                    <th style="width: 33%; text-align: center; border: 0.5px solid #94a3b8;">SALDO A FAVOR</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="width: 34%; text-align: center; font-weight: bold; color: #0f172a; border: 0.5px solid #94a3b8;"><?= esc($totals['items_amortizado_usd_formatted']) ?></td>
                    <td style="width: 33%; text-align: center; font-weight: bold; color: #0f172a; border: 0.5px solid #94a3b8;"><?= esc($totals['payments_usd_formatted']) ?></td>
                    <td style="width: 33%; text-align: center; font-weight: bold; color: #0f172a; border: 0.5px solid #94a3b8;"><?= !empty($totals['has_credit']) ? esc($totals['credit_usd_formatted']) : '$ 0' ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ESPACIADOR: TABLA 3 A FIRMAS -->
    <div style="font-size: 1px; height: 3px; line-height: 3px;">&nbsp;</div>

    <!-- FIRMAS AMBAS CENTRADAS Y CON BUEN ESPACIO -->
    <div>
        <table cellpadding="0" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 5%; border: none;"></td>
                <!-- Cajero Autorizado -->
                <td style="width: 40%; text-align: center; vertical-align: bottom; border: none;">
                    <div style="border-top: 0.5px solid #0f172a; font-size: 7.5pt; padding-top: 4px;">
                        <strong>CAJERO AUTORIZADO</strong><br>
                        <?= esc($collector_name) ?>
                    </div>
                </td>

                <td style="width: 10%; border: none;"></td>

                <!-- Conforme Cliente -->
                <td style="width: 40%; text-align: center; vertical-align: bottom; border: none;">
                    <div style="border-top: 0.5px solid #0f172a; font-size: 7.5pt; padding-top: 4px;">
                        <strong>CONFORME CLIENTE</strong><br>
                        <?= esc($client_name) ?><br>
                        <span style="font-size: 7pt; color: #64748b;"><?= esc($client_id_formatted) ?></span>
                    </div>
                </td>
                <td style="width: 5%; border: none;"></td>
            </tr>
        </table>
    </div>

</div>


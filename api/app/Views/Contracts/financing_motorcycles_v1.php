<?php
$clientDomicile = !empty($client_address) ? ($client_address . ', ' . $client_city) : $client_city;
?>
<div style="font-family: Arial, helvetica, sans-serif; font-size: 11pt; line-height: 1.35; color: #000;">
    <div style="text-align: center; font-weight: bold; margin-bottom: 14px; font-size: 12pt; line-height: 1.3;">
        FINANCIAMIENTOS MOT/CAR C.A<br>
        J-506980371
    </div>
    <div style="text-align: justify;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Entre la Sociedad Mercantil <strong>FINANCIAMIENTOS MOT/CAR</strong> C.A, inscrita en el Registro Mercantil Tercero del Estado Zulia, bajo el Número 3, Tomo 76, de fecha 14 de mayo de 2025, con domicilio en la Avenida Principal Virgen de Fátima, casa N°17C-11, Municipio San Francisco, Registro de Información Fiscal (RIF) J-506980371, representada en este acto por su Presidente y Vicepresidente según estatutos de la empresa, los Ciudadanos <strong>CARLOS ALBERTO VERA DURAN</strong> y <strong>CARLOS JAVIER URDANETA AVILA</strong>, Venezolanos, solteros, titulares de las cédulas de Identidad <strong>N° V- 28.470.838</strong> y <strong>N° V- 30.929.095</strong>, respectivamente, y que a los fines de este contrato se denominaran <strong><u>LOS ACREEDORES</u></strong>, por una parte y por la otra, al Ciudadano: <strong><?= esc($client_full_name) ?></strong>, <?= esc($client_nationality) ?>, titular de la cédula de identidad <strong>Nº <?= esc($client_identification) ?></strong>, domiciliado(a) en <strong><?= esc($clientDomicile) ?></strong>, quien a los fines de este contrato se denominara <strong><u>EL DEUDOR</u></strong>, se ha convenido celebrar el presente contrato de <strong>COMPROMISO DE PAGO</strong> que se regirá bajo las siguientes clausulas <strong><u>PRIMERO</u>:</strong> <strong><u>LOS ACREEDORES</u></strong> hará entrega al <strong><u>EL DEUDOR</u></strong> el cual acepta y reconoce recibir un vehículo tipo Moto nueva MARCA: <strong><?= esc($vehicle_brand) ?></strong> AÑO: <strong><?= esc($vehicle_year) ?></strong> MODELO: <strong><?= esc($vehicle_model) ?></strong><?php if (!empty($vehicle_color)): ?> COLOR: <strong><?= esc($vehicle_color) ?></strong><?php endif; ?><?php if (!empty($vehicle_plate)): ?> PLACA: <strong><?= esc($vehicle_plate) ?></strong><?php endif; ?><?php if (!empty($vehicle_chassis)): ?> SERIAL CHASIS: <strong><?= esc($vehicle_chassis) ?></strong><?php endif; ?><?php if (!empty($vehicle_engine)): ?> SERIAL MOTOR: <strong><?= esc($vehicle_engine) ?></strong><?php endif; ?>, y <strong><u>EL DEUDOR</u></strong> se compromete a cumplir con el presente contrato, <strong><u>SEGUNDA</u>:</strong> <strong><u>EL DEUDOR</u></strong> se compromete a pagar la totalidad de las cuotas, SIN EXCEPCION, de la siguiente manera: Un total de <?= esc($installment_count_words) ?> (<?= esc($installment_count) ?>) cuotas, las cuales serán canceladas de manera <?= esc($frequency_name) ?> y sin falta por un periodo de <?= esc($period_months_words) ?> (<?= esc($period_months_padded) ?>) meses, cada cuota tendrá un valor de <strong><?= esc($installment_amount_words) ?> DÓLARES ($<?= esc($installment_amount_formatted) ?>)</strong>, para un total de <strong><?= esc($financed_amount_words) ?> DÓLARES ($<?= esc($financed_amount_formatted) ?>)</strong> también deberá cancelar una cuota inicial de <strong><?= esc($initial_amount_words) ?> DÓLARES ($<?= esc($initial_amount_formatted) ?>)</strong>, los cuales NO serán abonables a la deuda y se cancelarán al momento de la firma del presente contrato. <strong><u>TERCERO</u>:</strong> el vehículo (moto) se entregará en 07 días hábiles luego de haber cancelado la cuota inicial. <strong><u>CUARTO</u>:</strong> una vez entregada la moto se le dará a <strong><u>EL DEUDOR</u></strong> una copia del Certificado de origen el cual tendrá como propietario al ciudadano: <strong><?= esc($client_full_name) ?></strong>, titular de la cédula de identidad: <strong><?= esc($client_identification) ?></strong>, dicha moto tendrá una reserva de dominio a nombre de <strong>MOT/CAR C.A</strong>, al momento de la cancelación de todas las cuotas establecidas, se le hará entrega de los documentos originales a <strong><u>EL DEUDOR</u></strong>. <strong><u>QUINTA</u>:</strong> si <strong><u>EL DEUDOR</u></strong> acumula dos (2) cuotas vencidas <strong><u>LOS ACREEDORES</u></strong> tendrán derecho de retirar automáticamente dicho vehículo hasta que cancele la totalidad de la deuda y la multa que se haya derivado por dicho incumplimiento, en este sentido si <strong><u>EL DEUDOR</u></strong> no cancela dicha deuda una vez retenido el vehículo y pasado 30 días continuos de la retención deberá cancelar el 20% extra de la deuda actual. <strong><u>SEXTA</u>:</strong> <strong><u>EL DEUDOR</u></strong> no podrá vender, enajenar ni hacer traspasos de la misma hasta que no haya cancelado la totalidad de las cuotas establecidas en este contrato de lo contrario será objeto de una acción penal, en consecuencia, se hará la respectiva denuncia ante los Organismos competentes, y perderá la moto y lo que haya abonado por este concepto. <strong><u>SÉPTIMA</u>:</strong> Al Vehículo debe de apresurarse una póliza de seguro la cual estará a nombre del <strong><u>DEUDOR</u></strong>, la cual cubre por robo, hurto o perdida de la moto, los gastos extras que se ocasionaren por algún incidente o accidente que no estén cubiertos por el seguro, correrán por cuenta del mismo. <strong><u>OCTAVA</u>:</strong> ambas partes declaran conocer y aceptar, todas las condiciones establecidas en este contrato. En <?= esc($branch_city) ?> a la fecha de su firma.
    </div>

    <br><br>
    <table style="width: 100%; margin-top: 30px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 48%; text-align: left; vertical-align: top;">
                <table cellpadding="0" cellspacing="0" style="width: 225px;">
                    <tr>
                        <td style="border-bottom: 0.8px solid #000; font-size: 11pt; line-height: 15px;">X</td>
                    </tr>
                </table>
                <div style="font-size: 10.5pt; line-height: 1.35; padding-top: 4px;">
                    <strong>Los Acreedores</strong><br>
                    CARLOS ALBERTO VERA DURAN<br>
                    C.I: V.- 28.470.838
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; text-align: left; vertical-align: top;">
                <table cellpadding="0" cellspacing="0" style="width: 225px;">
                    <tr>
                        <td style="border-bottom: 0.8px solid #000; font-size: 11pt; line-height: 15px;">X</td>
                    </tr>
                </table>
                <div style="font-size: 10.5pt; line-height: 1.35; padding-top: 4px;">
                    <strong>El Deudor</strong><br>
                    <?= esc($client_full_name) ?><br>
                    <?= esc($client_id_formatted) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 48%; text-align: left; vertical-align: top;">
                <br><br><br>
                <table cellpadding="0" cellspacing="0" style="width: 225px;">
                    <tr>
                        <td style="border-bottom: 0.8px solid #000; font-size: 11pt; line-height: 15px;">X</td>
                    </tr>
                </table>
                <div style="font-size: 10.5pt; line-height: 1.35; padding-top: 4px;">
                    <strong>Los Acreedores</strong><br>
                    CARLOS JAVIER URDANETA AVILA<br>
                    C.I: V.- 30.929.095
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%;"></td>
        </tr>
    </table>
</div>

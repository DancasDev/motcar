<?php

return [
    'item_unreached'               => 'El monto recaudado no alcanza a cubrir la totalidad de las obligaciones vinculadas. Quedaron sin fondos los ítems: {0}.',
    'exchange_rate_not_found'      => 'No se encontró una tasa de cambio válida para la moneda {0} en la fecha {1}.',
    'cancellation_reason_required' => 'Debe indicar un motivo obligatorio para anular el comprobante.',
    'receipt_not_found'            => 'Recibo no encontrado en la sucursal.',
    'receipt_not_draft'            => 'La operación solo está permitida mientras el recibo permanezca en estado Borrador.',
    'receipt_already_closed'       => 'El recibo ya fue confirmado o anulado y no admite modificaciones.',
    'invalid_transition'           => 'La transición de estado solicitada no es válida.',
    'payments_empty'               => 'Debe registrar al menos un medio de pago antes de confirmar el recibo.',
    'items_empty'                  => 'Debe vincular al menos una cuenta por cobrar antes de confirmar el recibo.',
    'credit_already_consumed'      => 'No es posible anular el recibo porque el saldo a favor generado ya fue consumido.',
    'receivable_not_payable'       => 'Una o más obligaciones vinculadas ya no se encuentran disponibles para cobro: {0}.',
    'receivable_client_mismatch'   => 'La cuenta por cobrar indicada no pertenece al cliente del recibo.',
    'credit_not_found'             => 'El saldo a favor especificado no fue encontrado.',
    'credit_not_available'         => 'El saldo a favor seleccionado ya no se encuentra disponible.',
    'credit_client_mismatch'       => 'El saldo a favor no pertenece al cliente del recibo.',
    'credit_insufficient_balance'  => 'El monto a consumir ({0}) excede el saldo disponible ({1}) del crédito.',
    'credit_duplicate_in_receipt'  => 'El saldo a favor ya ha sido añadido como medio de pago en este recibo.',
    'payment_origin_not_found'     => 'El origen de pago especificado no fue encontrado o está inactivo.',
];



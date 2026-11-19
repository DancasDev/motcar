<?php

return [
    'item_unreached'               => 'The collected amount is insufficient to cover all linked obligations. Items left without funds: {0}.',
    'exchange_rate_not_found'      => 'No valid exchange rate found for currency {0} on date {1}.',
    'cancellation_reason_required' => 'A cancellation reason is required to void the receipt.',
    'receipt_not_found'            => 'Receipt not found in the branch.',
    'receipt_not_draft'            => 'Operation only permitted while the receipt remains in Draft status.',
    'receipt_already_closed'       => 'The receipt is already closed or void and cannot be modified.',
    'invalid_transition'           => 'The requested status transition is not valid.',
    'payments_empty'               => 'At least one payment method must be registered before confirming the receipt.',
    'items_empty'                  => 'At least one receivable must be linked before confirming the receipt.',
    'credit_already_consumed'      => 'Cannot void receipt because the generated credit balance has already been consumed.',
    'receivable_not_payable'       => 'One or more linked receivables are no longer available for collection: {0}.',
    'receivable_client_mismatch'   => 'The specified receivable does not belong to the receipt client.',
    'credit_not_found'             => 'The specified credit balance was not found.',
    'credit_not_available'         => 'The selected credit balance is no longer available.',
    'credit_client_mismatch'       => 'The credit balance does not belong to the receipt client.',
    'credit_insufficient_balance'  => 'The amount to consume ({0}) exceeds the available credit balance ({1}).',
    'credit_duplicate_in_receipt'  => 'The credit balance has already been added as a payment method in this receipt.',
    'payment_origin_not_found'     => 'The specified payment origin was not found or is inactive.',
];



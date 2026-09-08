<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\User;

interface PaymentGatewayInterface
{
    /**
     * Initialize a payment session for an invoice.
     *
     * @param  Invoice  $invoice
     * @param  User  $payer
     * @param  array  $options
     * @return array{success: bool, checkout_url: string, tx_ref: string, reference?: string, message?: string}
     */
    public function initializePayment(Invoice $invoice, User $payer, array $options = []): array;

    /**
     * Verify a completed transaction with the gateway.
     *
     * @param  string  $txRef
     * @return array{success: bool, status: string, amount: float, currency: string, tx_ref: string, reference?: string, raw?: array}
     */
    public function verifyPayment(string $txRef): array;

    /**
     * Verify webhook signature.
     *
     * @param  string  $payload
     * @param  string  $signature
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;
}

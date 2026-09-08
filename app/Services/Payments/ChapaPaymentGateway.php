<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChapaPaymentGateway implements PaymentGatewayInterface
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $baseUrl;
    protected string $currency;
    protected bool $simulate;

    public function __construct()
    {
        $this->secretKey = (string) config('services.chapa.secret_key', '');
        $this->publicKey = (string) config('services.chapa.public_key', '');
        $this->baseUrl = rtrim((string) config('services.chapa.base_url', 'https://api.chapa.co/v1'), '/');
        $this->currency = (string) config('services.chapa.currency', 'ETB');
        $this->simulate = (bool) config('services.chapa.simulate', true) || app()->environment('testing') || str_contains($this->secretKey, 'demo');
    }

    /**
     * Set simulation mode manually (e.g. for testing).
     */
    public function setSimulate(bool $simulate): self
    {
        $this->simulate = $simulate;
        return $this;
    }

    /**
     * Initialize a payment session for an invoice.
     */
    public function initializePayment(Invoice $invoice, User $payer, array $options = []): array
    {
        $amount = isset($options['amount']) ? (float) $options['amount'] : (float) $invoice->balance();
        $txRef = 'BINA-INV-' . $invoice->id . '-' . time() . '-' . substr(md5(uniqid('', true)), 0, 6);
        $callbackUrl = $options['callback_url'] ?? url('/api/webhooks/chapa');
        $returnUrl = $options['return_url'] ?? url("/api/payments/chapa/callback?tx_ref={$txRef}");

        $studentName = $invoice->student?->user?->name ?? 'Student';
        $schoolName = $invoice->school?->name ?? 'Bina Schools';

        if ($this->simulate) {
            return [
                'success' => true,
                'checkout_url' => "https://checkout.chapa.co/checkout/test/{$txRef}",
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $this->currency,
                'message' => 'Simulated Chapa checkout initialized successfully.',
            ];
        }

        try {
            $nameParts = explode(' ', trim($payer->name ?? 'Parent'), 2);
            $firstName = $nameParts[0] ?? 'Parent';
            $lastName = $nameParts[1] ?? 'User';

            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->post("{$this->baseUrl}/transaction/initialize", [
                    'amount' => $amount,
                    'currency' => $this->currency,
                    'email' => $payer->email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone_number' => $options['phone_number'] ?? null,
                    'tx_ref' => $txRef,
                    'callback_url' => $callbackUrl,
                    'return_url' => $returnUrl,
                    'customization' => [
                        'title' => "{$schoolName} - Invoice #{$invoice->invoice_number}",
                        'description' => "Tuition & School Fee Payment for {$studentName}",
                    ],
                ]);

            $body = $response->json();

            if ($response->successful() && ($body['status'] ?? '') === 'success') {
                return [
                    'success' => true,
                    'checkout_url' => $body['data']['checkout_url'],
                    'tx_ref' => $txRef,
                    'amount' => $amount,
                    'currency' => $this->currency,
                    'message' => $body['message'] ?? 'Checkout session created.',
                ];
            }

            Log::error('Chapa initialization failed', ['response' => $body]);

            return [
                'success' => false,
                'checkout_url' => '',
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $this->currency,
                'message' => $body['message'] ?? 'Failed to initialize Chapa payment.',
            ];
        } catch (\Throwable $e) {
            Log::error('Chapa API exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'checkout_url' => '',
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $this->currency,
                'message' => 'Payment gateway error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify a completed transaction with Chapa.
     */
    public function verifyPayment(string $txRef): array
    {
        if ($this->simulate) {
            return [
                'success' => true,
                'status' => 'success',
                'amount' => 0.0, // Caller resolves from invoice balance or payment record
                'currency' => $this->currency,
                'tx_ref' => $txRef,
                'reference' => 'CHAPA-SIM-' . time(),
                'raw' => ['simulated' => true],
            ];
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->get("{$this->baseUrl}/transaction/verify/{$txRef}");

            $body = $response->json();

            if ($response->successful() && ($body['status'] ?? '') === 'success') {
                $data = $body['data'] ?? [];

                return [
                    'success' => true,
                    'status' => strtolower($data['status'] ?? 'success'),
                    'amount' => (float) ($data['amount'] ?? 0.0),
                    'currency' => $data['currency'] ?? $this->currency,
                    'tx_ref' => $data['tx_ref'] ?? $txRef,
                    'reference' => $data['reference'] ?? null,
                    'raw' => $data,
                ];
            }

            return [
                'success' => false,
                'status' => 'failed',
                'amount' => 0.0,
                'currency' => $this->currency,
                'tx_ref' => $txRef,
                'reference' => null,
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Chapa verification exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'status' => 'error',
                'amount' => 0.0,
                'currency' => $this->currency,
                'tx_ref' => $txRef,
                'reference' => null,
                'raw' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Verify webhook HMAC signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if ($this->simulate) {
            return true;
        }

        $secret = config('services.chapa.webhook_secret') ?: $this->secretKey;
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BkashService
{
    protected $baseUrl;
    protected $appKey;
    protected $appSecret;
    protected $username;
    protected $password;
    protected $headers;

    public function __construct()
    {
        // Credentials can be loaded from config/bkash.php or env directly
        $this->baseUrl = config('services.bkash.base_url', env('BKASH_BASE_URL'));
        $this->appKey = config('services.bkash.app_key', env('BKASH_APP_KEY'));
        $this->appSecret = config('services.bkash.app_secret', env('BKASH_APP_SECRET'));
        $this->username = config('services.bkash.username', env('BKASH_USERNAME'));
        $this->password = config('services.bkash.password', env('BKASH_PASSWORD'));
    }

    /**
     * Get or refresh the authentication token.
     * Retries automatically if expired.
     */
    protected function getToken()
    {
        return Cache::remember('bkash_token', 3500, function () {
            $response = Http::withHeaders([
                'username' => $this->username,
                'password' => $this->password,
            ])->post($this->baseUrl . '/tokenized/checkout/token/grant', [
                'app_key' => $this->appKey,
                'app_secret' => $this->appSecret,
            ]);

            if ($response->failed()) {
                Log::error('bKash Token Error: ' . $response->body());
                throw new \Exception('Failed to get bKash token');
            }

            return $response->json('id_token');
        });
    }

    protected function getHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getToken(),
            'X-APP-Key' => $this->appKey,
        ];
    }

    /**
     * 1. Create Agreement
     */
    public function createAgreement($callbackUrl)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/tokenized/checkout/create', [
                'mode' => '0000', // No payment, just agreement
                'callbackURL' => $callbackUrl,
                'payerReference' => 'link_wallet', 
            ]);

        return $response->json();
    }

    /**
     * 2. Execute Agreement (After Callback)
     */
    public function executeAgreement($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ]);

        return $response->json();
    }

    /**
     * 3. Create Payment with Agreement
     */
    public function createPayment($agreementId, $amount, $merchantInvoiceNumber, $callbackUrl)
    {
        $payload = [
            'mode' => '0001', // Payment with Agreement
            'payerReference' => $merchantInvoiceNumber,
            'callbackURL' => $callbackUrl,
            'agreementID' => $agreementId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $merchantInvoiceNumber,
        ];

        Log::info('bKash Create Payment Payload:', $payload);

        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/tokenized/checkout/create', $payload);

        Log::info('bKash Create Payment Response:', $response->json());

        return $response->json();
    }

    /**
     * 4. Execute Payment
     */
    public function executePayment($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ]);
        
        return $response->json();
    }

    /**
     * Query Payment
     */
    public function queryPayment($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get($this->baseUrl . '/tokenized/checkout/payment/status', [
                'paymentID' => $paymentId,
            ]);

        return $response->json();
    }
    
    /**
     * Refund Transaction
     */
    public function refund($paymentId, $amount, $trxId, $reason = 'Requested by user', $sku = 'wallet-refund')
    {
        $payload = [
            'paymentID' => $paymentId,
            'amount' => number_format($amount, 2, '.', ''),
            'trxID' => $trxId,
            'sku' => $sku,
            'reason' => $reason,
        ];

        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/tokenized/checkout/payment/refund', $payload);

        return $response->json();
    }
}

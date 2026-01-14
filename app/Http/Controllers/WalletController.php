<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\User;
use App\Services\BkashService;
use App\Services\StatementService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    protected $bkash;
    protected $walletService;
    protected $statementService;

    public function __construct(BkashService $bkash, WalletService $walletService, StatementService $statementService)
    {
        $this->bkash = $bkash;
        $this->walletService = $walletService;
        $this->statementService = $statementService;
    }

    /**
     * Get Wallet Balance and History
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate(['user_id' => $user->id]);
        $agreement = Agreement::where('user_id', $user->id)->where('status', 'Active')->first();

        return response()->json([
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'has_agreement' => !!$agreement,
            'agreement_phone' => $agreement ? $agreement->payer_reference : null,
        ]);
    }

    /**
     * Get Transaction History
     */
    public function history(Request $request)
    {
        $wallet = $request->user()->wallet;
        if (!$wallet) {
            return response()->json(['data' => []]);
        }

        $transactions = $wallet->transactions()->latest()->paginate(10);
        return response()->json($transactions);
    }


    public function linkWallet(Request $request)
    {
        // Standard Callback URL (Public)
        $callbackUrl = route('api.bkash.agreement.callback');
        
        try {
            $response = $this->bkash->createAgreement($callbackUrl);
            
            if (isset($response['bkashURL']) && isset($response['paymentID'])) {
                // Cache the PaymentID -> UserID mapping for 30 minutes
                \Illuminate\Support\Facades\Cache::put(
                    'bkash_agreement_' . $response['paymentID'], 
                    $request->user()->id, 
                    now()->addMinutes(30)
                );

                return response()->json(['redirect_url' => $response['bkashURL']]);
            }
            return response()->json(['error' => 'Failed to initiate agreement', 'details' => $response], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Step 2: Agreement Callback
     */
    public function linkWalletCallback(Request $request)
    {
        $status = $request->status;
        $paymentId = $request->paymentID;

        if ($status !== 'success' || !$paymentId) {
             return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?agreement_status=failed&error=Agreement+Cancelled');
        }

        // Retrieve User ID from Cache
        $userId = \Illuminate\Support\Facades\Cache::get('bkash_agreement_' . $paymentId);

        if (!$userId) {
            return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?agreement_status=failed&error=Session+Expired+or+Invalid+Payment');
        }

        try {
            // Execute Agreement
            $result = $this->bkash->executeAgreement($paymentId);

            if (isset($result['agreementID'])) {
                
                Agreement::updateOrCreate(
                    ['user_id' => $userId],
                    [
                        'agreement_id' => $result['agreementID'],
                        'payer_reference' => $result['payerReference'],
                        'status' => 'Active'
                    ]
                );

                // Clear Cache
                \Illuminate\Support\Facades\Cache::forget('bkash_agreement_' . $paymentId);

                // Redirect to Frontend Dashboard
                return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?agreement_status=success');
            }

            return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?agreement_status=failed&error=Execution+Failed');

        } catch (\Exception $e) {
            return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?agreement_status=failed&error=System+Error');
        }
    }

    /**
     * Step 3: Add Money (Payment with Agreement)
     */
    public function addMoney(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);

        $user = $request->user();
        $amount = $request->amount;
        $agreement = Agreement::where('user_id', $user->id)->where('status', 'Active')->first();

        if (!$agreement) {
            return response()->json(['error' => 'No active bKash agreement found'], 400);
        }

        try {
            $merchantInvoice = 'INV-' . Str::upper(Str::random(10));
            $callbackUrl = route('api.bkash.payment.callback'); 

            // Create Payment
            $createRes = $this->bkash->createPayment(
                $agreement->agreement_id, 
                $amount, 
                $merchantInvoice, 
                $callbackUrl
            );

            if (isset($createRes['bkashURL']) && isset($createRes['paymentID'])) {
                 // Cache the PaymentID -> UserID mapping for 30 minutes for the callback
                 \Illuminate\Support\Facades\Cache::put(
                    'bkash_payment_' . $createRes['paymentID'], 
                    $user->id, 
                    now()->addMinutes(30)
                );
                
                // Also cache the amount for verification if needed, or rely on execution response
                \Illuminate\Support\Facades\Cache::put(
                    'bkash_payment_amount_' . $createRes['paymentID'], 
                    $amount, 
                    now()->addMinutes(30)
                );

                return response()->json(['redirect_url' => $createRes['bkashURL']]);
            }

            return response()->json(['error' => 'Payment creation failed', 'details' => $createRes], 400);

        } catch (\Exception $e) {
            Log::error($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Step 3b: Payment Callback (Execute)
     */
    public function paymentCallback(Request $request)
    {
        $status = $request->status;
        $paymentId = $request->paymentID;

        if ($status !== 'success' || !$paymentId) {
             return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?payment_status=failed&error=Payment+Cancelled');
        }

        // Retrieve User ID from Cache
        $userId = \Illuminate\Support\Facades\Cache::get('bkash_payment_' . $paymentId);
        $amount = \Illuminate\Support\Facades\Cache::get('bkash_payment_amount_' . $paymentId);

        if (!$userId) {
            return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?payment_status=failed&error=Session+Expired');
        }

        $user = User::find($userId);

        try {
            // Execute Payment
            $execRes = $this->bkash->executePayment($paymentId);

            if (isset($execRes['trxID'])) {
                
                // REDIS LOCK for Credit (Prevent Double Credit if callback hits twice - though cache clear helps)
                $lockKey = $this->walletService->acquireLock($userId);
                
                if ($lockKey) {
                    try {
                        // Check if already credited (idempotency check via trxID if stored, but here we trust flow for now)
                        $this->walletService->credit(
                            $user,
                            $execRes['amount'] ?? $amount, // Use amount from response as truth, fallback to cache
                            $execRes['trxID'],
                            $paymentId,
                            'Added money via bKash',
                            $execRes
                        );
                    } finally {
                        $this->walletService->releaseLock($lockKey);
                    }
                }

                // Clear Cache
                \Illuminate\Support\Facades\Cache::forget('bkash_payment_' . $paymentId);
                \Illuminate\Support\Facades\Cache::forget('bkash_payment_amount_' . $paymentId);

                return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?payment_status=success');
            }

            return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?payment_status=failed&error=Execution+Failed');

        } catch (\Exception $e) {
            Log::error($e);
            return redirect(env('APP_FRONTEND_URL', 'http://localhost:5173') . '/dashboard?payment_status=failed&error=System+Error');
        }
    }

    /**
     * Download Statement PDF
     */
    public function downloadStatement(Request $request)
    {
        $user = $request->user();
        $transactions = $user->wallet->transactions()->latest()->get(); 
        
        $data = [
            'user' => $user,
            'transactions' => $transactions,
            'date' => now()->toDayDateTimeString(),
        ];

        try {
            // Need a view 'documents.statement'
            $pdfContent = $this->statementService->generateStatementPdf('documents.statement', $data);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="statement.pdf"');
        } catch (\Throwable $e) {
            Log::error('PDF Statement Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'PDF Service Unavailable', 
                'message' => 'The PDF generation service is not responding. Please make sure Docker is running.'
            ], 503);
        }
    }
}

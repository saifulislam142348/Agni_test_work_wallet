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
        
        // Fetch all active agreements
        $agreements = Agreement::where('user_id', $user->id)
            ->where('status', 'Active')
            ->select('id', 'payer_reference', 'created_at')
            ->get();

        return response()->json([
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'agreements' => $agreements,
            'has_agreement' => $agreements->isNotEmpty(),
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

        Log::info('bKash Link Callback Initiated', [
            'status' => $status,
            'paymentId' => $paymentId,
            'all_params' => $request->all()
        ]);

        if ($status !== 'success' || !$paymentId) {
             Log::warning('bKash Link Callback Failed: Invalid Status or Missing PaymentID');
             return redirect(config('app.frontend_url') . '/dashboard?agreement_status=failed&error=Agreement+Cancelled');
        }

        // Retrieve User ID from Cache
        $userId = \Illuminate\Support\Facades\Cache::get('bkash_agreement_' . $paymentId);
        
        Log::info('bKash Link Callback User Lookup', ['userId' => $userId, 'cacheKey' => 'bkash_agreement_' . $paymentId]);

        if (!$userId) {
            Log::error('bKash Link Callback Error: User ID not found in cache (Session Expired)');
            return redirect(config('app.frontend_url') . '/dashboard?agreement_status=failed&error=Session+Expired+or+Invalid+Payment');
        }

        try {
            // Execute Agreement
            $result = $this->bkash->executeAgreement($paymentId);
            
            Log::info('bKash Execute Agreement Response', ['response' => $result]);

            if (isset($result['agreementID'])) {
                
                // Enforce "Single Linked Account" Policy
                // Deactivate ALL existing active agreements for this user
                Agreement::where('user_id', $userId)
                    ->where('status', 'Active')
                    ->update(['status' => 'Inactive']);

                // Store in Agreements Table (Encrypted via Model Cast)
                Agreement::updateOrCreate(
                    [
                        'user_id' => $userId, 
                        // Use agreementID as unique constraint if needed, but here we want to ensure we capture the new one correctly
                        // We rely on user_id + status logic mostly. 
                        'agreement_id' => $result['agreementID']
                    ],
                    [
                        'payer_reference' => $result['payerReference'] ?? ($result['customerMsisdn'] ?? 'N/A'),
                        'status' => 'Active'
                    ]
                );

                // Clear Cache
                \Illuminate\Support\Facades\Cache::forget('bkash_agreement_' . $paymentId);

                // Check for pending payment
                $pendingAmount = \Illuminate\Support\Facades\Cache::get('pending_payment_amount_' . $userId);

                if ($pendingAmount) {
                    \Illuminate\Support\Facades\Cache::forget('pending_payment_amount_' . $userId);
                    // Use the agreement we just saved/updated
                    return $this->initiatePayment($userId, $result['agreementID'], $pendingAmount);
                }

                // Redirect to Frontend Dashboard
                $frontendUrl = config('app.frontend_url');
                return redirect($frontendUrl . '/dashboard?agreement_status=success');
            }

            Log::error('bKash Link Callback Error: agreementID missing in response', ['response' => $result]);
            return redirect(config('app.frontend_url') . '/dashboard?agreement_status=failed&error=Execution+Failed');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Agreement Error: ' . $e->getMessage());
            return redirect(config('app.frontend_url') . '/dashboard?agreement_status=failed&error=' . urlencode('System Error: ' . $e->getMessage()));
        }
    }

    /**
     * Step 3: Add Money (Payment with Agreement)
     */
    public function addMoney(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'agreement_id' => 'nullable|exists:agreements,id'
        ]);

        $user = $request->user();
        $amount = $request->amount;
        
        // 1. Try Specific Agreement selection
        if ($request->agreement_id) {
             $agreement = Agreement::where('user_id', $user->id)->where('id', $request->agreement_id)->first();
             if ($agreement) {
                 // Decryption is handled by Model Cast? 
                 // If 'encrypted' cast is used, accessing $agreement->agreement_id returns decrypted string automatically.
                 return $this->initiatePayment($user->id, $agreement->agreement_id, $amount);
             }
        }

        // 2. Try Default/Latest Agreement (Only if NOT forcing new)
        if (!$request->force_new) {
            $latestAgreement = Agreement::where('user_id', $user->id)->where('status', 'Active')->latest()->first();

            if ($latestAgreement) {
                 return $this->initiatePayment($user->id, $latestAgreement->agreement_id, $amount);
            }
        }
        
        // 3. No Agreement -> Start Binding Flow
        \Illuminate\Support\Facades\Cache::put('pending_payment_amount_' . $user->id, $amount, now()->addMinutes(30));
        
        // Call linkWallet logic
        return $this->linkWallet($request);
    }

    private function initiatePayment($userId, $agreementId, $amount)
    {
        try {
            $merchantInvoice = 'INV-' . Str::upper(Str::random(10));
            $callbackUrl = route('api.bkash.payment.callback'); 

            // Create Payment
            $createRes = $this->bkash->createPayment(
                $agreementId, 
                $amount, 
                $merchantInvoice, 
                $callbackUrl
            );

            if (isset($createRes['bkashURL']) && isset($createRes['paymentID'])) {
                 // Cache the PaymentID -> UserID mapping for 30 minutes
                 \Illuminate\Support\Facades\Cache::put(
                    'bkash_payment_' . $createRes['paymentID'], 
                    $userId, 
                    now()->addMinutes(30)
                );
                
                // Also cache the amount
                \Illuminate\Support\Facades\Cache::put(
                    'bkash_payment_amount_' . $createRes['paymentID'], 
                    $amount, 
                    now()->addMinutes(30)
                );

                $redirectUrl = $createRes['bkashURL'];
                
                // If called via API (addMoney), return JSON. If via internal redirect (Callback), return Redirect.
                // How to detect? request()->wantsJson() or check caller?
                // addMoney is API. linkWalletCallback is Redirect.
                
                if (request()->route()->getName() === 'api.bkash.agreement.callback') {
                     return redirect($redirectUrl);
                }
                
                return response()->json(['redirect_url' => $redirectUrl]);
            }
            
            $error = $createRes['statusMessage'] ?? 'Payment creation failed';
            
            if (request()->route()->getName() === 'api.bkash.agreement.callback') {
                 return redirect(config('app.frontend_url') . '/dashboard?payment_status=failed&error=' . urlencode($error));
            }

            return response()->json(['error' => $error, 'details' => $createRes], 400);

        } catch (\Exception $e) {
            Log::error($e);
            if (request()->route()->getName() === 'api.bkash.agreement.callback') {
                 return redirect(config('app.frontend_url') . '/dashboard?payment_status=failed&error=System+Error');
            }
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
             return redirect(config('app.frontend_url') . '/dashboard?payment_status=failed&error=Payment+Cancelled');
        }

        // Retrieve User ID from Cache
        $userId = \Illuminate\Support\Facades\Cache::get('bkash_payment_' . $paymentId);
        $amount = \Illuminate\Support\Facades\Cache::get('bkash_payment_amount_' . $paymentId);

        if (!$userId) {
            return redirect(config('app.frontend_url') . '/dashboard?payment_status=failed&error=Session+Expired');
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

                return redirect(config('app.frontend_url') . '/dashboard?payment_status=success');
            }

            return redirect(config('app.frontend_url') . '/dashboard?payment_status=failed&error=Execution+Failed');

        } catch (\Exception $e) {
            Log::error($e);
            return redirect(config('app.frontend_url') . '/dashboard?payment_status=failed&error=System+Error');
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
                'message' => 'Unable to generate PDF statement at this time. Please try again later.'
            ], 503);
        }
    }
}

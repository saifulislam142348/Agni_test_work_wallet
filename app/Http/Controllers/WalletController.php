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

    /**
     * Step 1: Link Wallet (Create Agreement)
     */
    public function linkWallet(Request $request)
    {
        $callbackUrl = route('api.bkash.agreement.callback'); // Define this route
        
        try {
            $response = $this->bkash->createAgreement($callbackUrl);
            if (isset($response['bkashURL'])) {
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

        if ($status !== 'success') {
             // In a real app, redirect to frontend with error
            return response()->json(['status' => 'failed', 'message' => 'Agreement failed or cancelled']);
        }

        try {
            // Execute Agreement
            $result = $this->bkash->executeAgreement($paymentId);

            if (isset($result['agreementID'])) {
                // Store Agreement
                // In API, we might not have auth context if this is a direct browser callback. 
                // Usually bKash callbacks need to handle session/auth. 
                // For simplicity, we assume we can identify user or this is an API call.
                // NOTE: In a decoupled frontend, this callback is tricky. 
                // Ideally, frontend handles the redirect and calls backend with parameters.
                // We will assume the frontend sends the parameters to this endpoint.
                
                // If this method is called by the Frontend after it receives params from bKash:
                $user = $request->user();
                
                Agreement::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'agreement_id' => $result['agreementID'],
                        'payer_reference' => $result['payerReference'],
                        'status' => 'Active'
                    ]
                );

                return response()->json(['status' => 'success', 'message' => 'Wallet linked successfully']);
            }

            return response()->json(['error' => 'Agreement execution failed', 'details' => $result], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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

        // REDIS LOCK
        $lockKey = $this->walletService->acquireLock($user->id);
        if (!$lockKey) {
            return response()->json(['error' => 'Duplicate request. Please wait.'], 429);
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

            if (!isset($createRes['paymentID'])) {
                throw new \Exception('Failed to create payment');
            }

            // Execute Payment immediately (since it's agreement based, often auto-captured or requires simple execute)
            // Note: For Payment with Agreement, user authorization might be needed if it's the first time or high amount.
            // But usually "Grant Token" flow is strictly for Agreement. 
            // Here we just Execute.
            
            $execRes = $this->bkash->executePayment($createRes['paymentID']);

            if (isset($execRes['trxID'])) {
                // SUCCESS - Credit Wallet
                $this->walletService->credit(
                    $user,
                    $amount,
                    $execRes['trxID'],
                    $createRes['paymentID'],
                    'Added money via bKash',
                    $execRes
                );

                return response()->json(['status' => 'success', 'balance' => $user->wallet->fresh()->balance]);
            }

            return response()->json(['error' => 'Payment failed', 'details' => $execRes], 400);

        } catch (\Exception $e) {
            Log::error($e);
            return response()->json(['error' => $e->getMessage()], 500);
        } finally {
            $this->walletService->releaseLock($lockKey);
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

        // Need a view 'documents.statement'
        $pdfContent = $this->statementService->generateStatementPdf('documents.statement', $data);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="statement.pdf"');
    }
}

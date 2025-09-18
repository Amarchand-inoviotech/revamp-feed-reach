<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Services\NmiService;
use App\Models\PaymentMethod;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class NMIController extends Controller
{
    /**
     * Handle NMI webhook notifications
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('NMI Webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'raw_body' => $request->getContent()
            ]);

            // Get NMI service instance
            $nmiService = $this->getNmiService();

            // Validate webhook
            // if (!$nmiService->validateWebhook($request->getContent(), '')) {
            //     Log::warning('NMI Webhook validation failed', [
            //         'ip' => $request->ip(),
            //         'headers' => $request->headers->all()
            //     ]);

            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Webhook validation failed'
            //     ], 401);
            // }

            // Process the webhook
            Log::info('Processing NMI webhook', [
                'data' => $request->all()
            ]);
            $result = $nmiService->processWebhook($request->all());

            if ($result['success']) {
                Log::info('NMI Webhook processed successfully', [
                    'result' => $result
                ]);

                return response()->json($result, 200);
            }

            Log::warning('NMI Webhook processing failed', [
                'error' => $result['error'] ?? 'Unknown error',
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to process webhook'
            ], 422);

        } catch (Exception $e) {
            Log::error('NMI Webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get NMI service instance
     */
    protected function getNmiService(): NmiService
    {
        $paymentMethod = PaymentMethod::where('name', 'nmi')->first();
        if (!$paymentMethod) {
            throw new Exception('NMI payment method not found');
        }

        $account = Account::where('payment_method_id', $paymentMethod->id)
            ->where('status', true)
            ->first();

        if (!$account) {
            throw new Exception('No active NMI account found');
        }

        return new NmiService($account, app()->environment('production'));
    }
}

<?php

namespace App\Http\Controllers\Api\Frontoffice\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontoffice\BusinessRequest;
use App\Mail\BusinessQueryMailable;
use App\Repositories\Contracts\BusinessRepositoryContract;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BusinessController extends Controller
{
    private BusinessRepositoryContract $repo;

    /**
     * Display a listing of the resource.
     */

    public function __construct(BusinessRepositoryContract $repository)
    {
        $this->repo = $repository;
    }
    public function store(BusinessRequest $request)
    {
        $payload = $request->validated();
        $response = null;
        try {
            DB::transaction(function () use (&$payload, &$response) {
                $response = $this->repo->createBusiness($payload, true);
            });
        } catch (Exception $e) {
            dd($e);
            return errorResponse($e->getMessage(), 500);
        }
        return successResponse($response, 'Business created successfully', 201, 0);
    }

    public function sendEmail(Request $request)
    {
        try {
            // Get processed data from the request
            $queryData = $request->all();

            // Determine recipient email (use provided recipient or default)
            $recipientEmail = config('mail.from.address','query@revivepay.com');

            // Create and defer the main email
            $mailable = new BusinessQueryMailable($queryData);
            sendEmail($recipientEmail, 'New Business Query - ' . ($queryData['name'] ?? 'Unknown'), $mailable, $queryData);

            Log::info('Business query email sent', $queryData);


            return successResponse(
                ['message' => 'Your business query has been sent successfully. We will get back to you soon.'],
                'Business query sent successfully',
                200
            );

        } catch (Exception $e) {
            Log::error('Failed to send business query email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return errorResponse(
                'Failed to send your business query. Please try again later.',
                500
            );
        }
    }
}

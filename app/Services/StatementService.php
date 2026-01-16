<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class StatementService
{
    protected $gotenbergUrl;

    public function __construct()
    {
        $this->gotenbergUrl = env('GOTENBERG_URL', 'http://localhost:3000');
    }

    /**
     * Generate PDF from Blade view using DomPDF
     */
    public function generateStatementPdf($view, $data, $filename = 'statement.pdf')
    {
        // Load view and render PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data);
        
        // Return PDF stream as string (or download response, but here we expect content as string if caller handles headers)
        // However, WalletController expects string to put in response body.
        return $pdf->output();
    }
}

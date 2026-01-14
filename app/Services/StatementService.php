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
     * Generate PDF from Blade view using Gotenberg
     */
    public function generateStatementPdf($view, $data, $filename = 'statement.pdf')
    {
        $html = View::make($view, $data)->render();

        // Gotenberg /forms/chromium/convert/html endpoint
        $response = Http::attach(
            'files', $html, 'index.html'
        )->post($this->gotenbergUrl . '/forms/chromium/convert/html', [
            'marginTop' => 0.5,
            'marginBottom' => 0.5,
            'marginLeft' => 0.5,
            'marginRight' => 0.5,
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to generate PDF via Gotenberg');
        }

        return $response->body();
    }
}

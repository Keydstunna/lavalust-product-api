<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Welcome extends Controller
{
    public function index()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'name'      => 'Product Management API',
            'status'    => 'running',
            'framework' => 'LavaLust',
            'endpoints' => [
                'auth'     => '/api/auth/login',
                'products' => '/api/products (requires login)',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
<?php

use Illuminate\Support\Facades\Route;

// The single-page frontend. Strict CSP: only same-origin scripts, no inline JS.
Route::get('/', fn () => response()
    ->view('app')
    ->header('Content-Security-Policy', implode('; ', [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' https://fonts.googleapis.com",
        'font-src https://fonts.gstatic.com',
        "img-src 'self' data:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ])));

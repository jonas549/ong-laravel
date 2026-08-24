<?php

namespace App\Http\Middleware;

use App\Services\SmtpConfigService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplySmtpSettings
{
    public function __construct(private SmtpConfigService $smtp)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->smtp->aplicar();
        } catch (Throwable) {
            // Si la tabla settings todavía no existe (instalación nueva,
            // migraciones sin correr), seguimos con la config del .env.
        }

        return $next($request);
    }
}

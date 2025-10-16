<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;

class CustomExceptionHandler
{
    public static function handle(HttpException $e, Request $request)
    {
        $statusCode = $e->getStatusCode();
        $message = $e->getMessage();

        // API request - no customize
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        $host = $request->getHost();
        // With CMS
        if (str_starts_with($host, 'cms.')) {
            return null;
        }

        // With Auth
        return self::handleAuthPageException($statusCode, $message);
    }

    private static function handleAuthPageException($statusCode, $message)
    {
        if (view()->exists("landingpage.template.error")) {
            return response()->view("landingpage.template.error", [
                'message' => $message ?: self::getDefaultMessage($statusCode),
                'statusCode' => $statusCode
            ], $statusCode);
        }

        return null;
    }

    private static function getDefaultMessage($code)
    {
        return match ($code) {
            404 => 'Page introuvable',
            403 => 'Vous n’avez pas l’autorisation d’accéder à cette page',
            401 => 'Veuillez vous connecter',
            500 => 'Erreur interne du serveur',
            503 => 'Le système est en maintenance',
            default => 'Une erreur est survenue'
        };
    }
}

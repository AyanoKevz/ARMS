<?php

namespace App\Http\Middleware;

use App\Services\AuditTrail;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds an entry to the admin audit trail after each admin action that worked.
 *
 * Runs after the controller, so it can tell a completed action from a refused
 * one. Nothing is recorded for:
 *   - reads (GET/HEAD) — the trail is of changes, not page views
 *   - routes not listed in AuditTrail::ACTIONS
 *   - non-admins
 *   - a failed request: an error status, a validation failure or business-rule
 *     refusal flashed to the session, or a JSON reply with success: false
 */
class RecordAdminActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe()) {
            return $response;
        }

        $routeName = $request->route()?->getName();

        if (!$routeName || !isset(AuditTrail::ACTIONS[$routeName])) {
            return $response;
        }

        $actor = $request->user();

        if (!$actor || !AuditTrail::roleOf($actor) || !$this->succeeded($request, $response)) {
            return $response;
        }

        AuditTrail::recordRequest($request, $actor, $routeName);

        return $response;
    }

    private function succeeded(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            return !(is_array($data) && array_key_exists('success', $data) && $data['success'] === false);
        }

        // A redirect back with errors: the action was refused, not done.
        if ($request->hasSession()) {
            $session = $request->session();

            if ($session->has('error')) {
                return false;
            }

            $errors = $session->get('errors');

            if ($errors && method_exists($errors, 'any') && $errors->any()) {
                return false;
            }
        }

        return true;
    }
}

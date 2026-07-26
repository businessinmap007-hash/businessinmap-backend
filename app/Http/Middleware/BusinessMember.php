<?php

namespace App\Http\Middleware;

use App\Services\Business\BusinessAccessService;
use App\Support\BusinessContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Lets EITHER the owning business OR an active delegated staff member act on a
 * business surface, and (optionally) enforces one capability:
 * `business.member:orders`. The owner always passes; a staff member must carry
 * the named capability. The resolved acting business is stashed on the request
 * for BusinessContext to read.
 *
 * A staff member of several businesses names the one they act for via the
 * `X-Business-Id` header or a `business_id` field; with a single membership it
 * is inferred.
 */
class BusinessMember
{
    public function __construct(private readonly BusinessAccessService $access)
    {
    }

    public function handle(Request $request, Closure $next, ?string $capability = null)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => __('يجب تسجيل الدخول أولاً.')], 401);
        }

        $requested = $request->header('X-Business-Id') ?? $request->input('business_id');
        $requested = ($requested === null || $requested === '') ? null : (int) $requested;

        $context = $this->access->resolveContext($user, $requested);

        if ($context === BusinessAccessService::AMBIGUOUS) {
            return response()->json([
                'success' => false,
                'message' => __('حدّد النشاط الذي تديره عبر business_id.'),
            ], 409);
        }

        if ($context === BusinessAccessService::NO_ACCESS) {
            return response()->json([
                'success' => false,
                'message' => __('لا تملك صلاحية إدارة هذا النشاط.'),
            ], 403);
        }

        if ($capability !== null && ! $context['is_owner'] && ! in_array($capability, $context['capabilities'], true)) {
            return response()->json([
                'success' => false,
                'message' => __('لا تملك صلاحية تنفيذ هذا الإجراء.'),
            ], 403);
        }

        $request->attributes->set(BusinessContext::BUSINESS, $context['business']);
        $request->attributes->set(BusinessContext::IS_OWNER, $context['is_owner']);
        $request->attributes->set(BusinessContext::CAPABILITIES, $context['capabilities']);

        return $next($request);
    }
}

<?php declare(strict_types=1);

namespace Ecommerly\Connector\Api\Controller;

use Ecommerly\Connector\Framework\Routing\EcommerlyRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Whether this store can be dispatched into right now.
 *
 * Reaching this method at all already proves the interesting part:
 * the plugin is installed and active, the connection is enabled, and
 * the signature verified. Anything heavier — a database round trip,
 * a cache probe — would make the dispatcher's fail-fast health check
 * slower than the call it is meant to protect.
 */
#[Route(defaults: ['_routeScope' => [EcommerlyRouteScope::ID], 'auth_required' => false])]
class HealthController extends SignedController
{
    #[Route(path: '/ecommerly/health/', name: 'ecommerly.health', methods: ['POST'])]
    public function health(Request $request): JsonResponse
    {
        if ($rejection = $this->reject($request)) {
            return $rejection;
        }

        return new JsonResponse(['healthy' => true]);
    }
}

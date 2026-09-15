<?php declare(strict_types=1);

namespace Ecommerly\Connector\Api\Controller;

use Ecommerly\Connector\Capability\CapabilityRegistry;
use Ecommerly\Connector\Capability\EditionDetector;
use Ecommerly\Connector\Connection\ConnectionConfig;
use Ecommerly\Connector\Connection\SignedRequestVerifier;
use Ecommerly\Connector\Framework\Routing\EcommerlyRouteScope;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What this store can answer, and what it is.
 *
 * Ecommerly calls this before dispatching anything, so it is the one
 * place an edition boundary or a narrowed scope becomes visible
 * without a failed capability call.
 *
 * The trailing slash is not cosmetic: the path is part of the signed
 * canonical string, and Symfony would 301 a signed POST to the
 * unslashed form, turning it into a GET that fails with no useful
 * explanation.
 */
#[Route(defaults: ['_routeScope' => [EcommerlyRouteScope::ID], 'auth_required' => false])]
class ManifestController extends SignedController
{
    public function __construct(
        SignedRequestVerifier $verifier,
        LoggerInterface $logger,
        private readonly CapabilityRegistry $capabilities,
        private readonly ConnectionConfig $config,
        private readonly EditionDetector $edition,
        private readonly string $pluginVersion
    ) {
        parent::__construct($verifier, $logger);
    }

    #[Route(path: '/ecommerly/manifest/', name: 'ecommerly.manifest', methods: ['POST'])]
    public function manifest(Request $request): JsonResponse
    {
        if ($rejection = $this->reject($request)) {
            return $rejection;
        }

        return new JsonResponse([
            'module_version' => $this->pluginVersion,
            'platform_version' => $this->edition->shopwareVersion(),
            'enabled_scopes' => $this->config->scopes(),
            'supported_capabilities' => $this->capabilities->supported(),
            'edition' => $this->edition->edition(),
            'active_bundles' => $this->edition->activeBundles(),
        ]);
    }
}

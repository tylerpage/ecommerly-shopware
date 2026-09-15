<?php declare(strict_types=1);

namespace Ecommerly\Connector\Api\Controller;

use Ecommerly\Connector\Capability\CapabilityRegistry;
use Ecommerly\Connector\Connection\ConnectionConfig;
use Ecommerly\Connector\Connection\SignedRequestVerifier;
use Ecommerly\Connector\Framework\Routing\EcommerlyRouteScope;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * The single entry point for capability execution.
 *
 * One endpoint taking a named capability, never a path or query the
 * caller composes: Ecommerly's hard product boundary is that no
 * connector exposes arbitrary execution, and a registry lookup on a
 * fixed name is what enforces it here.
 *
 * Context::createDefaultContext() rather than an admin API context:
 * these reads are not performed on behalf of any Shopware user, and
 * borrowing an admin identity would misreport who acted in any
 * downstream audit.
 */
#[Route(defaults: ['_routeScope' => [EcommerlyRouteScope::ID], 'auth_required' => false])]
class CapabilityController extends SignedController
{
    public function __construct(
        SignedRequestVerifier $verifier,
        LoggerInterface $logger,
        private readonly CapabilityRegistry $capabilities,
        private readonly ConnectionConfig $config
    ) {
        parent::__construct($verifier, $logger);
    }

    #[Route(path: '/ecommerly/capability/execute', name: 'ecommerly.capability.execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        if ($rejection = $this->reject($request)) {
            return $rejection;
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['capability']) || ! is_string($payload['capability'])) {
            return new JsonResponse([
                'outcome' => 'failed',
                'error_code' => 'invalid_arguments',
                'message' => 'A capability name is required.',
            ]);
        }

        $arguments = isset($payload['arguments']) && is_array($payload['arguments']) ? $payload['arguments'] : [];

        try {
            $result = $this->capabilities->handle(
                $payload['capability'],
                $arguments,
                Context::createDefaultContext(),
                $this->config->scopes()
            );
        } catch (Throwable $exception) {
            // The message is logged, never returned: an exception
            // from the DAL can carry table names, SQL and filesystem
            // paths, and this response crosses a network boundary to
            // a system that will put it in front of a model.
            $this->logger->error('An Ecommerly capability failed.', [
                'capability' => $payload['capability'],
                'exception' => $exception->getMessage(),
            ]);

            return new JsonResponse([
                'outcome' => 'failed',
                'error_code' => 'capability_error',
                'message' => 'The capability could not be completed on this store.',
            ]);
        }

        return new JsonResponse($result->toArray());
    }
}

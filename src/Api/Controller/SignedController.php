<?php declare(strict_types=1);

namespace Ecommerly\Connector\Api\Controller;

use Ecommerly\Connector\Connection\SignedRequestVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shared rejection handling for the three signed endpoints.
 *
 * Every rejection is logged as a security event and answered with the
 * same shape, so a caller cannot distinguish an unknown connection
 * from a bad secret by response shape or timing of the reply. The log
 * line is where the detail goes, because the operator investigating a
 * failed integration needs it and the caller does not.
 */
abstract class SignedController
{
    public function __construct(
        protected readonly SignedRequestVerifier $verifier,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Null means the request is authentic and the caller may proceed.
     */
    protected function reject(Request $request): ?JsonResponse
    {
        $result = $this->verifier->verify($request);

        if ($result->ok) {
            return null;
        }

        $this->logger->warning('Ecommerly rejected a signed request.', [
            'error_code' => $result->errorCode,
            'path' => $request->getPathInfo(),
            'connection_id' => $request->headers->get(SignedRequestVerifier::HEADER_CONNECTION_ID),
            'remote_addr' => $request->getClientIp(),
        ]);

        return new JsonResponse([
            'outcome' => 'failed',
            'error_code' => $result->errorCode,
            'message' => $result->message,
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }
}

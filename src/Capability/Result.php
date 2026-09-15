<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability;

/**
 * What a capability handler returns, in the vocabulary Ecommerly's
 * SignedHttpConnector::execute() reads back.
 *
 * `unavailable` is not an error and must not be reported as one: it
 * means the question is legitimately unanswerable here — an edition
 * boundary, a scope this connection was not granted, a subsystem this
 * platform does not have. Ecommerly surfaces that to the operator as
 * a limitation. Returning `failed`, or worse a guess, is how an
 * assistant ends up inventing an answer about a real store.
 */
final class Result
{
    private function __construct(
        public readonly string $outcome,
        public readonly array $data,
        public readonly ?string $errorCode,
        public readonly ?string $message
    ) {
    }

    public static function success(array $data): self
    {
        return new self('success', $data, null, null);
    }

    public static function unavailable(string $errorCode, string $message): self
    {
        return new self('unavailable', [], $errorCode, $message);
    }

    public static function failed(string $errorCode, string $message): self
    {
        return new self('failed', [], $errorCode, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = ['outcome' => $this->outcome, 'data' => $this->data];

        if ($this->errorCode !== null) {
            $payload['error_code'] = $this->errorCode;
            $payload['message'] = $this->message;
        }

        return $payload;
    }
}

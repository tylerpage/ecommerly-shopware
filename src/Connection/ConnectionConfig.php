<?php declare(strict_types=1);

namespace Ecommerly\Connector\Connection;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Everything the plugin knows about its pairing with Ecommerly.
 *
 * Merchant-editable settings come from config.xml under the
 * `.config.` prefix, which the administration renders itself; the
 * pairing state below it is written only by the console commands and
 * never exposed as an editable field, because a hand-edited
 * connection id or secret would fail every signature check with no
 * obvious cause.
 */
class ConnectionConfig
{
    private const SETTING_PREFIX = 'EcommerlyConnector.config.';

    private const STATE_PREFIX = 'EcommerlyConnector.connection.';

    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly SecretCipher $cipher
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->systemConfig->get(self::SETTING_PREFIX.'enabled') ?? true);
    }

    public function ecommerlyBaseUrl(): ?string
    {
        $value = $this->systemConfig->getString(self::SETTING_PREFIX.'ecommerlyBaseUrl');

        return $value === '' ? null : rtrim($value, '/');
    }

    public function isPaired(): bool
    {
        return $this->connectionId() !== null;
    }

    public function connectionId(): ?string
    {
        $value = $this->systemConfig->getString(self::STATE_PREFIX.'connectionId');

        return $value === '' ? null : $value;
    }

    public function secret(): ?string
    {
        $stored = $this->systemConfig->getString(self::STATE_PREFIX.'secret');

        return $stored === '' ? null : $this->cipher->decrypt($stored);
    }

    /**
     * Rotation keeps the outgoing secret valid for a short overlap,
     * so a request Ecommerly signed just before the rotation lands
     * does not fail in flight. Expired overlaps are treated as
     * absent rather than cleaned up eagerly — the check is cheap and
     * a scheduled cleanup would be one more thing to fail silently.
     */
    public function previousSecret(): ?string
    {
        $stored = $this->systemConfig->getString(self::STATE_PREFIX.'previousSecret');
        $expiresAt = (int) ($this->systemConfig->getInt(self::STATE_PREFIX.'previousSecretExpiresAt') ?? 0);

        if ($stored === '' || $expiresAt <= time()) {
            return null;
        }

        return $this->cipher->decrypt($stored);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        $scopes = $this->systemConfig->get(self::STATE_PREFIX.'scopes');

        return is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [];
    }

    /**
     * Re-pairing an already-paired store is how a credential gets
     * rotated: the outgoing secret is kept valid for a short overlap
     * so a request Ecommerly signed moments before the new
     * credentials landed still verifies, instead of failing in
     * flight. That is the overlap window PRD §6.3 asks rotation to
     * keep, and SignedRequestVerifier is what honours it.
     *
     * @param list<string> $scopes
     */
    public function storePairing(string $connectionId, string $secret, array $scopes = [], int $overlapSeconds = 300): void
    {
        $outgoing = $this->systemConfig->getString(self::STATE_PREFIX.'secret');

        $this->systemConfig->set(self::STATE_PREFIX.'previousSecret', $outgoing);
        $this->systemConfig->set(self::STATE_PREFIX.'previousSecretExpiresAt', $outgoing === '' ? 0 : time() + $overlapSeconds);
        $this->systemConfig->set(self::STATE_PREFIX.'connectionId', $connectionId);
        $this->systemConfig->set(self::STATE_PREFIX.'secret', $this->cipher->encrypt($secret));
        $this->systemConfig->set(self::STATE_PREFIX.'scopes', $scopes);
        $this->systemConfig->set(self::STATE_PREFIX.'pairedAt', (new \DateTimeImmutable)->format(DATE_ATOM));
    }

    public function pairedAt(): ?string
    {
        $value = $this->systemConfig->getString(self::STATE_PREFIX.'pairedAt');

        return $value === '' ? null : $value;
    }

    public function forget(): void
    {
        foreach (['connectionId', 'secret', 'scopes', 'pairedAt', 'previousSecret', 'previousSecretExpiresAt'] as $key) {
            $this->systemConfig->delete(self::STATE_PREFIX.$key);
        }
    }
}

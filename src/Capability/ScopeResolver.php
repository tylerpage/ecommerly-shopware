<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Turns the scope arguments in Ecommerly's capability schemas into
 * Shopware identifiers.
 *
 * A wrinkle worth understanding before extending this. Ecommerly's
 * shared schemas currently name scope in Magento's vocabulary —
 * `website_id`, `store_view_id`, `customer_group_id` — as integers,
 * because Magento was the first connector. Shopware identifies all
 * three with UUIDs, so a Magento-shaped integer is not merely a
 * different spelling of a Shopware id; there is nothing it could
 * resolve to.
 *
 * Rather than guess (pick the default sales channel? the first one?),
 * an integer scope argument is reported unavailable with a code that
 * says why. The neutral `sales_channel_id` and `language_id` aliases
 * are the ones that work, and adding them to the shared schemas is
 * tracked as its own piece of work on the Ecommerly side.
 */
class ScopeResolver
{
    public const SALES_CHANNEL_KEYS = ['sales_channel_id', 'website_id', 'store_view_id'];

    public const LANGUAGE_KEYS = ['language_id'];

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{id: ?string, error: ?Result}
     */
    public function salesChannelId(array $arguments): array
    {
        return $this->resolveUuid($arguments, self::SALES_CHANNEL_KEYS, 'sales channel');
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{id: ?string, error: ?Result}
     */
    public function languageId(array $arguments): array
    {
        return $this->resolveUuid($arguments, self::LANGUAGE_KEYS, 'language');
    }

    /**
     * @param array<string, mixed> $arguments
     * @param list<string>         $keys
     *
     * @return array{id: ?string, error: ?Result}
     */
    private function resolveUuid(array $arguments, array $keys, string $label): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $arguments) || $arguments[$key] === null) {
                continue;
            }

            $value = $arguments[$key];

            if (is_string($value) && Uuid::isValid($value)) {
                return ['id' => $value, 'error' => null];
            }

            return ['id' => null, 'error' => Result::unavailable(
                'scope_identifier_not_applicable',
                sprintf(
                    'This store identifies a %s by UUID; [%s] was given as a value this platform has no equivalent for.',
                    $label,
                    $key
                )
            )];
        }

        return ['id' => null, 'error' => null];
    }
}

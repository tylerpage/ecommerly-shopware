<?php declare(strict_types=1);

namespace Ecommerly\Connector\Framework\Routing;

use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Symfony\Component\HttpFoundation\Request;

/**
 * A scope of its own, so Ecommerly's endpoints inherit neither the
 * admin API's OAuth nor the storefront's session handling.
 *
 * ApiAuthenticationListener::validateRequest() only validates a
 * bearer token when the matched route's scope implements
 * ApiContextRouteScopeDependant. This scope deliberately does not, so
 * requests here are never OAuth-checked — they authenticate by HMAC
 * instead (SignedRequestVerifier), which is the protocol Ecommerly
 * speaks to every platform. Borrowing the 'api' scope would demand an
 * admin token nobody has; borrowing 'storefront' would drag in
 * session and sales-channel resolution these endpoints have no use
 * for.
 *
 * isAllowed() returning true is not "no authentication": the scope
 * decides only whether a request may reach the controller at all, and
 * every controller behind it rejects anything unsigned.
 */
class EcommerlyRouteScope extends AbstractRouteScope
{
    public const ID = 'ecommerly';

    /**
     * $allowedPaths is assigned here rather than redeclared as a
     * property, and that is load-bearing for supporting 6.6 and 6.7
     * from one codebase: AbstractRouteScope declares it untyped on
     * 6.6 and, per its own `@deprecated tag:v6.7.0 - Will be natively
     * typed` annotation, as `array` on 6.7. A subclass that
     * redeclares the property is a fatal error on one line or the
     * other, because PHP rejects both adding a type the parent lacks
     * and omitting one the parent has. Assigning a value carries no
     * type declaration, so it is correct against both.
     */
    public function __construct()
    {
        $this->allowedPaths = ['ecommerly'];
    }

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return self::ID;
    }
}

<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

/**
 * Storage whose writes are carried by the `Response` handed to `write()`, so
 * they reach the client only when that exact response is sent — cookie storage
 * is the built-in case. Writes of a storage without this marker are
 * lifecycle-scoped: they land in a backend shared by the whole request
 * lifecycle (the session) and are visible no matter which response is sent.
 *
 * The distinction decides how far the preference-written marker travels: a
 * lifecycle-scoped write suppresses the invalid-preference cleanup on the main
 * request, while a response-scoped write cannot, because its response may be
 * discarded (fragment rendering). Response-scoped storage therefore has to
 * refuse clearing a preference it has just written to the same response.
 */
interface ResponseScopedStorageInterface extends TimezonePreferenceStorageInterface
{
}

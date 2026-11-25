<?php

namespace Postio\Laravel;

use RuntimeException;

/**
 * Postio could not answer (bad key, out of credit, rate limited or unreachable),
 * as distinct from a postcode that simply has no addresses. The PostioException
 * with Postio's error code is the previous exception.
 */
class LookupUnavailableException extends RuntimeException
{
}

<?php

/**
 * Qubit API Exception Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Exceptions classes directly
 */

use AtomExtensions\Exceptions\Api404Exception;
use AtomExtensions\Exceptions\ApiBadRequestException;
use AtomExtensions\Exceptions\ApiNotAuthorizedException;
use AtomExtensions\Exceptions\ApiForbiddenException;

class QubitApi404Exception extends Api404Exception {}
class QubitApiBadRequestException extends ApiBadRequestException {}
class QubitApiNotAuthorizedException extends ApiNotAuthorizedException {}
class QubitApiForbiddenException extends ApiForbiddenException {}

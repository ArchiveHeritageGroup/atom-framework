<?php

namespace AtomFramework\Http\Middleware;

use Closure;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Http\Request;

/**
 * Database transaction middleware with deadlock retry.
 *
 * Replaces QubitTransactionFilter. Wraps the request in a database
 * transaction, retrying up to 3 times on MySQL deadlock (error 1213).
 */
class TransactionMiddleware
{
    private const RETRY_LIMIT = 3;
    private const DEADLOCK_ERROR_CODE = 1213;

    public function handle(Request $request, Closure $next)
    {
        $retries = 0;

        while (true) {
            DB::connection()->beginTransaction();

            try {
                $response = $next($request);
                DB::connection()->commit();

                return $response;
            } catch (\PDOException $e) {
                DB::connection()->rollBack();

                // Retry on deadlock
                if (isset($e->errorInfo[1])
                    && self::DEADLOCK_ERROR_CODE == $e->errorInfo[1]
                    && $retries < self::RETRY_LIMIT) {
                    $retries++;

                    continue;
                }

                throw $e;
            } catch (\Exception $e) {
                DB::connection()->rollBack();

                throw $e;
            }
        }
    }
}

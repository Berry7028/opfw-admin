<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware to check if the panel is getting updated
 *
 * @package App\Http\Middleware
 */
class UpdateMiddleware
{
    const Style = 'body,html{margin:0;background:#111827;height:100%;width:100%}div{color:#fff;font-family:Nunito,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:28px;font-weight:600;text-shadow:0 0 4px #000;text-align:center}small{display:block;font-size:16px;font-weight:500;font-style:italic;color:#cecece}small:last-child{font-size:13px;color:#b2e68e}';

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $file = __DIR__ . '/../../../update';

        if (file_exists($file)) {
            http_response_code(418);

            die('<style>' . self::Style . '</style><div>Update in progress<small>Try again in a few minutes</small><small>Stage: ' . file_get_contents($file) . '</small></div>');
        }

        return $next($request);
    }

}

import { execSync } from 'node:child_process';

/**
 * Reset the login rate limiter before the suite.
 *
 * Filament's login calls rateLimit(5) unconditionally, so it counts SUCCESSFUL
 * sign-ins toward the cap and never clears them — six logins inside a minute
 * and the sixth is refused with "Too many login attempts", leaving the browser
 * on /admin/login. The learner door does not have this problem: LoginForm only
 * hits the limiter on failure and clears it on success.
 *
 * One suite run is well under the cap, but two or three back-to-back runs (or a
 * watch loop) are not, which showed up as an intermittent admin-login failure
 * that looked like a parallelism race and was not one. The limiter lives in the
 * cache store, so clearing it is enough — and it costs one container start.
 *
 * Non-fatal by design: if podman or the image is unavailable, the suite should
 * still run and merely risk the throttle it would have hit anyway.
 */
export default function globalSetup(): void {
    try {
        execSync('./run php artisan cache:clear', { stdio: 'pipe' });
    } catch (e) {
        console.warn('  ! could not clear the rate limiter — repeated runs may hit the login throttle');
    }
}

<?php
/**
 * CSRF (Cross-Site Request Forgery) Protection
 *
 * Generates and validates CSRF tokens to prevent unauthorized form submissions.
 * Requires session to be started (handled by db.php).
 */

/**
 * Generate or retrieve the current CSRF token
 *
 * @return string The CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate HTML input field with CSRF token
 *
 * @return string HTML input element
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify CSRF token from request
 *
 * @param string|null $token Token to verify (defaults to $_POST['csrf_token'])
 * @return bool True if token is valid
 */
function csrf_verify(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($sessionToken) || empty($token)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

/**
 * Verify CSRF token or die with error
 *
 * @param string|null $token Token to verify (defaults to $_POST['csrf_token'])
 * @param int $httpCode HTTP status code to send on failure
 * @return void
 */
function csrf_protect(?string $token = null, int $httpCode = 403): void {
    if (!csrf_verify($token)) {
        http_response_code($httpCode);
        error_log('CSRF token validation failed from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}

/**
 * Regenerate CSRF token (call after successful form submission to prevent replay)
 *
 * @return string New token
 */
function csrf_regenerate(): string {
    unset($_SESSION['csrf_token']);
    return csrf_token();
}

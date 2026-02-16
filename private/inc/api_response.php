<?php
/**
 * Standardized API Response Helpers
 *
 * Provides consistent JSON response format across all API endpoints.
 * All responses follow the format: { ok: boolean, data?: any, error?: string }
 */

/**
 * Send a JSON API response and exit
 *
 * @param mixed $data Response data
 * @param int $statusCode HTTP status code
 * @return never
 */
function api_response($data, int $statusCode = 200): never {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send an error response and exit
 *
 * @param string $message Error message
 * @param int $statusCode HTTP status code (default 400)
 * @param array $extra Optional additional data to include
 * @return never
 */
function api_error(string $message, int $statusCode = 400, array $extra = []): never {
    $response = ['ok' => false, 'error' => $message];
    if (!empty($extra)) {
        $response = array_merge($response, $extra);
    }
    api_response($response, $statusCode);
}

/**
 * Send a success response and exit
 *
 * @param mixed $data Data to return (optional)
 * @param int $statusCode HTTP status code (default 200)
 * @return never
 */
function api_success($data = null, int $statusCode = 200): never {
    $response = ['ok' => true];
    if ($data !== null) {
        $response['data'] = $data;
    }
    api_response($response, $statusCode);
}

/**
 * Validate required fields in request data
 *
 * @param array $data Request data ($_POST, $_GET, etc.)
 * @param array $required Array of required field names
 * @param int $errorCode HTTP status code for error (default 400)
 * @return void Dies with api_error if validation fails
 */
function api_require_fields(array $data, array $required, int $errorCode = 400): void {
    $missing = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        api_error('Missing required fields: ' . implode(', ', $missing), $errorCode);
    }
}

/**
 * Validate request method
 *
 * @param string|array $allowedMethods Single method or array of methods (e.g., 'POST' or ['POST', 'GET'])
 * @return void Dies with api_error if method not allowed
 */
function api_require_method($allowedMethods): void {
    $allowed = is_array($allowedMethods) ? $allowedMethods : [$allowedMethods];
    $current = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($current, $allowed, true)) {
        api_error('Method not allowed. Expected: ' . implode(' or ', $allowed), 405);
    }
}

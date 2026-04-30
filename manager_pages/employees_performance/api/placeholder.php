<?php
/**
 * api/placeholder.php
 * Placeholder — backend will be wired here in the next phase.
 * Returns a JSON stub so the front-end can detect the endpoint.
 */
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Backend not implemented yet. UI-only phase.',
    'data'    => []
]);

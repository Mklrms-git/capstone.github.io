<?php
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
if (!$filename) {
    http_response_code(400);
    exit('No file specified.');
}

$baseDir = realpath(__DIR__ . '/uploads/notes');
if (!$baseDir) {
    http_response_code(500);
    exit('Upload directory not found.');
}

$fullPath = $baseDir . DIRECTORY_SEPARATOR . $filename;

// Allow letters, numbers, spaces, dash, underscore, period, parentheses
if (!preg_match('/^[a-zA-Z0-9\s_\-().]+\.(pdf|docx?|xlsx?|pptx?|jpe?g|png|gif|txt)$/i', $filename)) {
    http_response_code(403);
    exit('Invalid file name.');
}

if (!file_exists($fullPath) || strpos(realpath($fullPath), $baseDir) !== 0) {
    http_response_code(404);
    exit('File not found.');
}

// Debug for PDFs
if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'pdf') {
    error_log("Serving PDF: $fullPath");
    error_log("Size: " . filesize($fullPath));
}

$mimeType = mime_content_type($fullPath);
header('Content-Type: ' . $mimeType);

// You can change this to `inline` if needed
header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');

header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
?>

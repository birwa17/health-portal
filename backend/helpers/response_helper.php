<?php

function send_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function success(string $message, $data = []): void
{
    send_json(['success' => true, 'message' => $message, 'data' => $data]);
}

function fail(string $message, int $code = 400): void
{
    send_json(['success' => false, 'message' => $message], $code);
}

function required(array $input, string $field): string
{
    $value = trim((string)($input[$field] ?? ''));
    if ($value === '') {
        fail("The field '$field' is required.", 422);
    }
    return $value;
}

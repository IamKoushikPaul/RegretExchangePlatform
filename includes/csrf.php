<?php
// Simple CSRF protection. Call session_start() before using these.

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    return isset($_POST["csrf_token"], $_SESSION["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"]);
}
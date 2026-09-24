<?php

header("Content-Type: application/json");

echo json_encode([
    "success" => true,
    "message" => "Contact Manager API is running",
    "phpVersion" => PHP_VERSION
]);


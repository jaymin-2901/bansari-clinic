<?php
require_once __DIR__ . "/../../security/bootstrap.php";

// Require authentication
$user = SecurityBootstrap::authenticatedEndpoint('upload_image');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(["error" => "Method not allowed"], 405);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input["image_data"])) {
    jsonResponse(["error" => "No image data provided"], 400);
}
$imageData = $input["image_data"];
$prefix = isset($input["prefix"]) ? preg_replace("/[^a-z0-9_]/i", "", $input["prefix"]) : "img";
if (strpos($imageData, "data:image/") !== 0) { echo json_encode(["error" => "Invalid image data format"]); exit; }
$imageParts = explode(";", $imageData);
$mime = str_replace("data:", "", $imageParts[0]);
$allowedMimes = ["image/jpeg", "image/png", "image/webp"];
if (!in_array($mime, $allowedMimes)) { echo json_encode(["error" => "Invalid image type"]); exit; }
$base64Data = base64_decode(str_replace(" ", "+", substr($imageData, strpos($imageData, ",") + 1)));
$uploadDir = dirname(dirname(dirname(dirname(__DIR__)))) . "/public/uploads/testimonials/";
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
$extension = $mime === "image/png" ? "png" : ($mime === "image/webp" ? "webp" : "jpg");
$filename = $prefix . "_cropped_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
$filepath = $uploadDir . $filename;
if (file_put_contents($filepath, $base64Data) === false) { echo json_encode(["error" => "Failed to save image"]); exit; }
echo json_encode(["success" => true, "filename" => $filename, "path" => "/uploads/testimonials/" . $filename]);

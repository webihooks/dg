<?php 
header('Content-Type: application/json');

require_once 'config/db_connection.php';
require_once 'functions/profile_functions.php';

echo json_encode([
  "name" => $business_name,
  "short_name" => $business_name,
  "display" => "fullscreen",
  "background_color" => $primary_color,
  "theme_color" => $primary_color,
  "orientation" => "portrait",
  "scope" => "/",
  "related_applications" => [
    [
      "platform" => "play",
      "id" => "com.deegeecard.restaurant"
    ]
  ],
  "prefer_related_applications" => true
]);
 ?>
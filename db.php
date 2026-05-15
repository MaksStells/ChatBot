<?php
require_once __DIR__ . "/config.php";

function supabase_request($method, $endpoint, $data = null, $extra_headers = []) {
  $url = SUPABASE_URL . "/rest/v1/" . $endpoint;
  $headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_KEY,
    "Authorization: Bearer " . SUPABASE_KEY,
    "Prefer: return=representation"
  ];
  foreach ($extra_headers as $extra) {
    $key = explode(":", $extra)[0];
    $headers = array_filter($headers, function($h) use ($key) {
      return strpos($h, $key . ":") !== 0;
    });
    $headers[] = $extra;
  }
  $headers = array_values($headers);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  //curl_close($ch);
  unset($ch);

  return ["code" => $httpCode, "data" => json_decode($response, true)];
}
?>
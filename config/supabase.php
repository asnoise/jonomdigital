<?php
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

require_once __DIR__ . '/database.php';

class SupabaseClient {
    private $url;
    private $key;

    public function __construct() {
        $this->url = SUPABASE_URL;
        $this->key = SUPABASE_SERVICE_ROLE_KEY;
    }

    private function request($endpoint, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        $targetUrl = $this->url . '/rest/v1/' . ltrim($endpoint, '/');
        
        $defaultHeaders = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $mergedHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // PROFREEHOST COMPATIBILITY FLAGS
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);     

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Supabase API Connection Error: " . $error);
            return ['status' => 500, 'error' => 'Connection failure.'];
        }

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    // =========================================================================
    // NATIVE BINARY UPLOAD SYSTEM FOR SUPABASE STORAGE BUCKETS [2]
    // =========================================================================
    public function upload($bucket, $path, $fileTmpPath, $mimeType) {
        $ch = curl_init();
        $targetUrl = $this->url . '/storage/v1/object/' . $bucket . '/' . ltrim($path, '/');
        
        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: ' . $mimeType
        ];

        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($fileTmpPath));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Extends timeout boundary for large WAV files

        // PROFREEHOST COMPATIBILITY FLAGS
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 500, 'error' => $error];
        }

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    // Dynamic Select Query
    public function select($table, $select = '*', $filters = []) {
        $endpoint = $table . '?select=' . urlencode($select);
        foreach ($filters as $column => $value) {
            $endpoint .= '&' . urlencode($column) . '=eq.' . urlencode($value);
        }
        return $this->request($endpoint, 'GET');
    }

    // Dynamic Insert Query
    public function insert($table, $data) {
        return $this->request($table, 'POST', $data);
    }

    // Dynamic Update Query
    public function update($table, $data, $filters = []) {
        $endpoint = $table;
        if (!empty($filters)) {
            $endpoint .= '?';
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = urlencode($column) . '=eq.' . urlencode($value);
            }
            $endpoint .= implode('&', $conditions);
        }
        return $this->request($endpoint, 'PATCH', $data);
    }

    // Dynamic Delete Query
    public function delete($table, $filters = []) {
        $endpoint = $table;
        if (!empty($filters)) {
            $endpoint .= '?';
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = urlencode($column) . '=eq.' . urlencode($value);
            }
            $endpoint .= implode('&', $conditions);
        }
        return $this->request($endpoint, 'DELETE');
    }
}
<?php
class Supabase
{
    private $url;
    private $key;

    public function __construct($url, $key)
    {
        $this->url = rtrim($url, '/');
        $this->key = $key;
    }

    private function parseHeaders($headerContent)
    {
        $headers = [];
        foreach (explode("\r\n", $headerContent) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                $parts = explode(': ', $line);
                if (count($parts) == 2) {
                    $headers[$parts[0]] = $parts[1];
                }
            }
        }
        return $headers;
    }

    /**
     * Upload a file to Supabase Storage
     */
    public function uploadFile($bucket, $filename, $filePath, $contentType)
    {
        $url = $this->url . '/storage/v1/object/' . $bucket . '/' . $filename;
        $ch = curl_init();

        $fileContent = file_get_contents($filePath);

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: ' . $contentType
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("Supabase Storage Error ($httpCode): $response");
        }

        // Return public URL
        return $this->url . '/storage/v1/object/public/' . $bucket . '/' . $filename;
    }

    /**
     * Make a request to Supabase API
     */
    public function request($method, $endpoint, $data = [], $headers = [])
    {
        $url = $this->url . '/rest/v1/' . $endpoint;

        $ch = curl_init();

        $defaultHeaders = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation' // Return the inserted/updated data
        ];

        // Merge headers
        $mergedHeaders = array_merge($defaultHeaders, $headers);

        // Handle GET query parameters
        if ($method === 'GET' && !empty($data)) {
            $queryString = http_build_query($data);
            if (is_string($data)) {
                $url .= '?' . $data;
            } else {
                $url .= '?' . http_build_query($data);
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $mergedHeaders);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $decodedBody = json_decode($body, true);

        if ($httpCode >= 400) {
            $msg = isset($decodedBody['message']) ? $decodedBody['message'] : $body;
            throw new Exception("Supabase Request Error ($httpCode): $msg");
        }

        return $decodedBody;
    }
}
?>
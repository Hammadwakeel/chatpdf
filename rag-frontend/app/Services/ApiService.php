<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class ApiService {
    protected $baseUrl;
    public function __construct() {
        $this->baseUrl = env('FASTAPI_URL', 'http://127.0.0.1:8000');
    }
    private function client() {
        return Http::withToken(Session::get('access_token'))->timeout(120);
    }
    public function get($endpoint) { return $this->client()->get("{$this->baseUrl}/{$endpoint}"); }
    public function post($endpoint, $data = []) { return $this->client()->post("{$this->baseUrl}/{$endpoint}", $data); }
    public function uploadFiles($endpoint, $files, $fieldName = 'file') {
        $request = $this->client();
        foreach ($files as $file) {
            $request->attach($fieldName, file_get_contents($file), $file->getClientOriginalName());
        }
        return $request->post("{$this->baseUrl}/{$endpoint}");
    }
}

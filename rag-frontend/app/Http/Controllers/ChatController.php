<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Routing\Controller as BaseController;
use Barryvdh\DomPDF\Facade\Pdf;

class ChatController extends BaseController {
    protected $api;

    public function __construct(ApiService $api) {
        $this->api = $api;
    }

    public function index() {
        $response = $this->api->get('sidebar/history');
        $history = $response->successful() ? $response->json() : [];
        return view('chat.dashboard', compact('history'));
    }

    public function upload(Request $request) {
        $request->validate(['files.*' => 'required|mimes:pdf,jpg,png,jpeg|max:10240']);
        $fileCount = count($request->file('files'));

        // Handle normal uploads (Single or Multi)
        if ($fileCount > 1) {
            $response = $this->api->uploadFiles('compare/upload', $request->file('files'), 'files');
        } else {
            $response = $this->api->uploadFiles('upload', $request->file('files'), 'file');
        }
        return response()->json($response->json(), $response->status());
    }

    public function ocrUpload(Request $request) {
        $file = $request->file('files')[0];

        // 1. Get Urdu Text from Hugging Face OCR
        $ocrResponse = Http::attach('file', file_get_contents($file), $file->getClientOriginalName())
                           ->timeout(300)
                           ->post(env('OCR_API_URL', 'https://hammad712-urdu-ocr-app.hf.space/upload'));

        if ($ocrResponse->failed()) {
            return response()->json(['error' => 'OCR Worker is busy or down'], 503);
        }

        $text = $ocrResponse->json()['extracted_text'];

        // 2. Convert Text to PDF locally (The "Proxy PDF")
        // We wrap it in a div with RTL direction for Urdu support
        $pdf = Pdf::loadHTML("<div style='font-family: sans-serif; direction: rtl; text-align: right;'>$text</div>");
        $pdfContent = $pdf->output();

        // 3. Upload the generated PDF to your CLOUD RAG backend
        // We use the normal /upload endpoint so we don't have to change the cloud code
        $ragResponse = Http::withToken(Session::get('access_token'))
                           ->attach('file', $pdfContent, 'OCR_' . time() . '.pdf')
                           ->post(env('FASTAPI_URL') . '/upload');

        return response()->json($ragResponse->json(), $ragResponse->status());
    }

    public function ask(Request $request) {
        $response = $this->api->post('ask', [
            'session_id' => $request->session_id, 
            'question' => $request->question
        ]);
        return response()->json($response->json(), $response->status());
    }

    public function getMessages($id) {
        $response = $this->api->get("chat/{$id}/messages");
        return response()->json($response->json(), $response->status());
    }
}

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

        if ($fileCount > 1) {
            $response = $this->api->uploadFiles('compare/upload', $request->file('files'), 'files');
        } else {
            $response = $this->api->uploadFiles('upload', $request->file('files'), 'file');
        }
        return response()->json($response->json(), $response->status());
    }

    public function ocrUpload(Request $request) {
        $file = $request->file('files')[0];
        $ocrResponse = Http::attach('file', file_get_contents($file), $file->getClientOriginalName())
                           ->timeout(300)
                           ->post(env('OCR_API_URL', 'https://hammad712-urdu-ocr-app.hf.space/upload'));

        if ($ocrResponse->failed()) {
            return response()->json(['error' => 'OCR Worker is busy or down'], 503);
        }

        $text = $ocrResponse->json()['extracted_text'];
        $pdf = Pdf::loadHTML("<div style='font-family: sans-serif; direction: rtl; text-align: right;'>$text</div>");
        $pdfContent = $pdf->output();

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

    // New Method for Suggested Questions
    public function suggestedQuestions(Request $request) {
        $response = $this->api->post('suggested-questions', [
            'session_id' => $request->session_id
        ]);
        return response()->json($response->json(), $response->status());
    }
}

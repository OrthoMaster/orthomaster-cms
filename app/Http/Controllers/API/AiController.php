<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client;

class AiController extends Controller
{
    // Cấu hình các thông số hệ thống
    private $projectId = 'orthomaster-b5a0e'; // THAY ĐỔI THEO DỰ ÁN CỦA BẠN
    private $engineId = 'orthomaster-notebook_1774515838224';
    private $location = 'global';

    public function view()
    {
        return view('chatbot.chat');
    }

    /**
     * Hàm chính để xử lý câu hỏi từ khách hàng
     */
    public function ask(Request $request)
    {
        $request->validate([
            'question' => 'required|string',
        ]);

        try {
            $question = $request->input('question');
            $sessionId = $request->input('session_id');
            $accessToken = $this->getAccessToken();

            // Endpoint :answer (v1alpha) chuyên dụng cho NotebookLM
            $url = "https://discoveryengine.googleapis.com/v1alpha/projects/{$this->projectId}/locations/{$this->location}/collections/default_collection/engines/{$this->engineId}/servingConfigs/default_search:answer";
            $sessionPath = $sessionId
                ? "projects/{$this->projectId}/locations/{$this->location}/collections/default_collection/engines/{$this->engineId}/sessions/{$sessionId}"
                : null;


            // Gửi Request tới Google
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    "query" => ["text" => $question],
                    "session" => $sessionPath,
                    "answerGenerationSpec" => [
                        "modelSpec" => [
                            "modelVersion" => "gemini-2.0-flash-001/answer_gen/v1"
                        ],
                        "promptSpec" => [
                            "preamble" => "You are the 'Orthomaster AI Tutor,' a senior pedagogical expert. Your mission is to help students master the curriculum using ONLY the provided materials. 
ADAPT YOUR OUTPUT BASED ON USER INTENT:
1. STANDARD Q&A: Provide clear, concise answers with [1], [2] citations.
2. QCM/QUIZ MODE: If the user asks for a quiz, generate 3-5 Multiple Choice Questions. Format: Question -> 4 Options -> Correct Answer -> Explanation with citations.
3. SUMMARY MODE: Provide an 'Executive Summary' using bullet points.
4. ANALOGY MODE: Explain complex concepts using simple real-world analogies.

STRICT RULES:
- ONLY use the provided curriculum.
- If info is missing for a QCM, inform the user.
- Always maintain a professional tone.
- Output MUST be in the user's preferred language (French or English).

FORMATTING RULES:
1. For normal chat: Use Markdown with citations like [1], [2].
2. For QUIZ requests: 
   - You MUST output a valid JSON object wrapped in triple backticks like this: 
http://googleusercontent.com/immersive_entry_chip/0
                            "
                        ],
                        "ignoreAdversarialQuery" => true,
                        "ignoreNonAnswerSeekingQuery" => false,
                        "ignoreLowRelevantContent" => false,
                        "multimodalSpec" => [
                            "imageSource" => "FIGURE_GENERATION_ONLY"
                        ],
                        "includeCitations" => true,
                    ]
                ]);

            if ($response->failed()) {
                Log::error("Google API Error: " . $response->body());
                return response()->json(['error' => 'Không thể kết nối với AI Core.'], 500);
            }

            $data = $response->json();

            // Kiểm tra xem AI có "né" câu hỏi không (Lỗi OUT_OF_DOMAIN bạn vừa gặp)
            if (isset($data['answer']['answerSkippedReasons'])) {
                return response()->json([
                    'status' => 'skipped',
                    'message' => 'Aucune réponse trouvée dans les archives de documents !',
                    'reasons' => $data['answer']['answerSkippedReasons']
                ]);
            }

            $fullSessionName = $data['session'] ?? null;
            $justId = $fullSessionName ? basename($fullSessionName) : null;

            // Trả về kết quả sạch cho Frontend
            return response()->json([
                'status' => 'success',
                'session_id' => $justId,
                'answer' => $data['answer']['answerText'] ?? '',
                'citations' => $data['answer']['citations'] ?? [],
                'references' => $data['answer']['references'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error("Notebook Controller Error: " . $e->getMessage());
            return response()->json(['error' => 'Lỗi hệ thống nội bộ.'], 500);
        }
    }

    /**
     * Hàm lấy Access Token tự động từ file JSON
     */
    private function getAccessToken()
    {
        $keyFile = base_path('storage/app/chatbot/orthomaster-b5a0e-0e4f2ac0c577.json');
        if (!file_exists($keyFile)) {
            Log::channel('api')->error('Keyfile: {$keyFile}');
        }

        $client = new Client();
        $client->setAuthConfig($keyFile);
        $client->addScope('https://www.googleapis.com/auth/cloud-platform');

        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }
}

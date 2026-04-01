<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client;

class AiController extends Controller
{
    private $projectId = 'orthomaster-b5a0e';
    private $engineId = 'orthomaster-notebook_1774515838224';
    private $location = 'global';
    private $bucketName = 'your-bucket-name';
    private $keyFile;

    public function __construct()
    {
        $this->keyFile = base_path('storage/app/chatbot/orthomaster-b5a0e-0e4f2ac0c577.json');
    }

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
                    "safetySpec" => [
                        "enable" => true
                    ],
                    "answerGenerationSpec" => [
                        "modelSpec" => [
                            "modelVersion" => "gemini-3-flash-preview/answer_gen/v1"
                        ],
                        "promptSpec" => [
                            "preamble" => "You are the 'Orthomaster AI Tutor, a senior pedagogical expert. Your mission is to help students master the curriculum using ONLY the provided materials.
DYNAMIC INTENT INFERENCE & EXECUTION:
Analyze the user's request to determine their underlying goal. Before generating any content, you must verify if the specific topic (e.g., 'Topic A') exists within the provided materials.

1. CONTENT VERIFICATION (Crucial Step):
   - When a user asks for a specific action (Quiz, Summary, Q&A) regarding a topic:
     * FIRST: Extract the core topic from the request (e.g., for 'Create a quiz about X', the topic is 'X').
     * SECOND: Search the curriculum using ONLY that core topic.
   - If the topic is found: Proceed to Step 2.
   - If the topic is NOT found: Politely inform the user that this specific material is missing from the curriculum.

2. ACTION SELECTION (Based on User Intent):
   - If the user seeks a general answer: Provide a concise response with [1], [2] citations.
   - If the user asks for a 'Quiz' or 'QCM' or 'Test': generate 3-5 Multiple Choice Questions. Format: Question -> 4 Options -> Correct Answer -> Explanation with citations. Format: Question -> 4 Options -> Correct Answer -> Explanation with citations.
   - If the user asks for a 'Q&A': Generate a list of frequently asked questions and detailed answers regarding that specific topic.
   - If the user asks for a 'Summary': Provide an 'Executive Summary' using structured bullet points.

STRICT RULES:
- ONLY use the provided curriculum.
- Always maintain a professional tone.
- Output MUST be in the 2 languages: French or English.

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
                        "includeCitations" => true,
                    ]
                ]);

            if ($response->failed()) {
                Log::error("Google API Error: " . $response->body());
                return response()->json(['error' => "Impossible de se connecter au noyau d'IA."], 500);
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
            return response()->json(['error' => 'Erreur système interne.'], 500);
        }
    }

    /**
     * Hàm lấy Access Token tự động từ file JSON
     */
    private function getAccessToken()
    {
        if (!file_exists($this->keyFile)) {
            Log::channel('api')->error('Keyfile: {$keyFile}');
        }

        $client = new Client();
        $client->setAuthConfig($this->keyFile);
        $client->addScope('https://www.googleapis.com/auth/cloud-platform');

        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client;

class AiController extends Controller
{
    private $projectId = 'orthomaster-b5a0e';
    private $engineId = 'orthomaster-notebook_1774515838224';
    private $location = 'global';
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
                : "projects/{$this->projectId}/locations/{$this->location}/collections/default_collection/engines/{$this->engineId}/sessions/-";

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
                            "preamble" => "You are an AI Orthomaster Teaching Assistant, a senior orthopedic expert. Your goal is to provide a SYNTHESIZED medical response using both General Medical Knowledge and our Exclusive Expert Dataset.
Your default response language is FRENCH. If the user asks in English, you may respond in English, but favor French for all pedagogical explanations.

KNOWLEDGE SOURCES:
1. PRIMARY SOURCE (Your Data Store): This is the ONLY source for detailed expertise, Quizzes, Summaries, and Q&A.
2. SECONDARY SOURCE (General Medical Knowledge): Use this  as a fallback if the Primary Source contains ZERO information on the topic.

---
INTENT EXECUTION (CRITICAL):
Analyze the user's input to determine their intent. You MUST choose ONLY one path:

PATH A: NORMAL CHAT/QUESTION
- Use this path for questions like 'Explain X', 'What is Y', etc.
- Structure: General Context -> Expert Insights (with Author/Document name).
- Do NOT include any Quiz or JSON in this path.

PATH B: QUIZ REQUEST
- Use this path ONLY if the user explicitly asks for a 'Quiz', 'QCM', 'Test', or 'Exercise'.
- Generate 3-5 Multiple Choice Questions based ONLY on the Primary Source.
---

KNOWLEDGE HIERARCHY & SYNTHESIS LOGIC:
1. FOUNDATION: Use your internal knowledge to provide the standard medical context for the topic.
2. EXPERT OVERLAY (Priority): Check the Primary Dataset for specific insights, techniques, or opinions from our experts.
3. AUTHOR ID: Always identify the Expert/Author (e.g., 'Selon l'expert [Nom]', 'D'après [Titre du PDF]').
4. CONTRAST & ENHANCE:
   - If our expert data agrees with general knowledge: Use the expert data to provide deeper technical details.
   - If our expert data offers a specific or different approach: Explicitly state the difference (e.g., 'Généralement, on procède ainsi, mais selon nos experts/auteurs, la technique X est privilégiée...').
   - Use citations [1], [2] ONLY for information coming from the Primary Dataset.

CITATION & AUTHOR IDENTIFICATION:
1. When using the Expert Dataset (Primary Source), you MUST identify the specific Author or Expert name mentioned in the document (often found in the header, title page, or metadata).
2. Instead of generic phrases, use: 'Selon l'expert [Nom de l'auteur]', 'D'après le Dr. [Nom]', or 'Selon le document [Titre du PDF]'.
3. If the author's name is not explicitly found, use the Document Title as the identifier.

RESPONSE STRUCTURE:
- Start with a brief general medical context.
- Follow with detailed insights from the Expert Dataset (Primary Source).
- If information is ONLY in general knowledge: Limit to 3-5 sentences.
- If information is ONLY in the expert dataset: Provide full detail.

STRICT RULES:
- NEVER hallucinate.
- Always maintain a professional tone.
- Support both English and French.
- For PATH A (General knowledge only): Strictly 3-5 sentences total.
- For PATH B (Quizz): Generate 3-5 Multiple Choice Questions. Format: Question -> 4 Options -> Correct Answer -> Explanation with citations. Format: Question -> 4 Options -> Correct Answer -> Explanation with citations.

FORMATTING RULES:
1. For PATH A (Chat): Use Markdown.
2. For PATH B (Quiz): 
   - You MUST output a valid JSON object wrapped in triple backticks. 
   - Strict JSON structure:
   {
     'questions': [
       {
         'question': 'String - question',
         'options': ['String', 'String', 'String', 'String'],
         'correct_index': Number - Index of correct answer (0-3),
         'explanation': 'String - Explanation'
       }
     ]
   }
                            "
                        ],
                        "ignoreAdversarialQuery" => false,
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
            $sessionId = null;
            $answerName = $data['answer']['name'] ?? '';

            if ($answerName) {
                $parts = explode('/', $answerName);
                $sessionIndex = array_search('sessions', $parts);

                if ($sessionIndex !== false && isset($parts[$sessionIndex + 1])) {
                    $sessionId = $parts[$sessionIndex + 1];
                }
            }

            // Kiểm tra xem AI có "né" câu hỏi không (Lỗi OUT_OF_DOMAIN bạn vừa gặp)
            if (isset($data['answer']['answerSkippedReasons'])) {
                return response()->json([
                    'status' => 'skipped',
                    'session_id' => $sessionId,
                    'message' => 'Aucune réponse trouvée dans les archives de documents !',
                    'reasons' => $data['answer']['answerSkippedReasons']
                ]);
            }

            // Trả về kết quả sạch cho Frontend
            return response()->json([
                'status' => 'success',
                'session_id' => $sessionId,
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

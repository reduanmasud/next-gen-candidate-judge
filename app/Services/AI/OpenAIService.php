<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $model;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiUrl = config('services.openai.api_url');
        $this->model = config('services.openai.model');
        $this->timeout = config('services.openai.timeout');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
        }
    }

    /**
     * Send a chat completion request to OpenAI API.
     *
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $options Additional options like temperature, max_tokens, etc.
     * @return array Response from OpenAI API
     * @throws \Exception
     */
    public function chat(array $messages, array $options = []): array
    {
        try {
            $payload = array_merge([
                'model' => $this->model,
                'messages' => $messages,
            ], $options);

            Log::info('OpenAI API Request', [
                'model' => $this->model,
                'messages_count' => count($messages),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->apiUrl . '/chat/completions', $payload);

            if (!$response->successful()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? 'Unknown error';
                
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'body' => $errorBody,
                ]);

                throw new \Exception("OpenAI API request failed: {$errorMessage} (Status: {$response->status()})");
            }

            $data = $response->json();

            Log::info('OpenAI API Response', [
                'usage' => $data['usage'] ?? null,
                'model' => $data['model'] ?? null,
            ]);

            return $data;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenAI API Connection Error', [
                'message' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to connect to OpenAI API: ' . $e->getMessage());
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'OpenAI API') === 0) {
                throw $e;
            }
            Log::error('OpenAI API Unexpected Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Unexpected error while calling OpenAI API: ' . $e->getMessage());
        }
    }

    /**
     * Extract the content from the first choice in the response.
     *
     * @param array $response Response from OpenAI API
     * @return string The content of the message
     * @throws \Exception
     */
    public function extractContent(array $response): string
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid OpenAI API response: missing content');
        }

        return trim($response['choices'][0]['message']['content']);
    }

    /**
     * Send a request and get the content directly.
     *
     * @param array $messages Array of message objects
     * @param array $options Additional options
     * @return string The content from the response
     * @throws \Exception
     */
    public function getChatContent(array $messages, array $options = []): string
    {
        $response = $this->chat($messages, $options);
        return $this->extractContent($response);
    }

    /**
     * Evaluate an answer using AI.
     *
     * @param string $prompt The evaluation prompt template
     * @param string $question The question asked
     * @param string $expectedAnswer The expected/probable answer
     * @param string $userAnswer The user's submitted answer
     * @return array Parsed evaluation result with score and feedback
     * @throws \Exception
     */
    public function evaluateAnswer(string $prompt, string $question, string $expectedAnswer, string $userAnswer): array
    {
        // Build the evaluation prompt
        $systemPrompt = $prompt;
        
        $userPrompt = "Question: {$question}\n\n";
        $userPrompt .= "Expected Answer: {$expectedAnswer}\n\n";
        $userPrompt .= "User's Answer: {$userAnswer}\n\n";
        $userPrompt .= "Please evaluate the user's answer and respond with a JSON object containing:\n";
        $userPrompt .= "- \"score\": a number between 0 and 1 representing how correct the answer is (0 = completely wrong, 1 = completely correct)\n";
        $userPrompt .= "- \"feedback\": a brief explanation of the evaluation\n";
        $userPrompt .= "- \"is_correct\": a boolean indicating if the answer should be considered correct (score >= 0.7)\n\n";
        $userPrompt .= "Respond ONLY with valid JSON, no additional text.";

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ];

        // Request JSON response format
        $options = [
            'temperature' => 0.3, // Lower temperature for more consistent evaluations
            'response_format' => ['type' => 'json_object'],
        ];

        $content = $this->getChatContent($messages, $options);

        // Parse the JSON response
        $result = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse OpenAI JSON response', [
                'content' => $content,
                'error' => json_last_error_msg(),
            ]);
            throw new \Exception('Failed to parse AI evaluation response: ' . json_last_error_msg());
        }

        // Validate the response structure
        if (!isset($result['score']) || !isset($result['feedback']) || !isset($result['is_correct'])) {
            Log::error('Invalid OpenAI evaluation response structure', [
                'result' => $result,
            ]);
            throw new \Exception('Invalid AI evaluation response: missing required fields');
        }

        // Ensure score is between 0 and 1
        $result['score'] = max(0, min(1, (float) $result['score']));
        $result['is_correct'] = (bool) $result['is_correct'];

        return $result;
    }
}


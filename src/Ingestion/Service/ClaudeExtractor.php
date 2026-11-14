<?php

namespace DealVault\Ingestion\Service;

use DealVault\Ingestion\Entity\IngestionSource;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClaudeExtractor
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    public function extractMetrics(IngestionSource $source): array
    {
        $filePath = $source->getStoragePath();
        if (!$filePath || !file_exists($filePath)) {
            throw new \InvalidArgumentException('Storage file path is missing or invalid.');
        }

        // For MVP just read the text (for PDF/Spreadsheet will be added parsers on the next step)
        $fileContent = file_get_contents($filePath);

        // Make a system prompt
        $systemPrompt = "You are a precise financial data extractor for a venture capital firm. " .
            "Extract financial metrics (like ARR, Revenue, Headcount) from the text. " .
            "For each metric, you MUST find the exact numeric value, the period (e.g. 2026-Q3), currency, and the exact text excerpt serving as evidence. " .
            "Output strictly as valid JSON array of objects. Schema: [{\"metric_name\": \"arr\", \"value\": 1200000.00, \"period\": \"2026-Q3\", \"currency\": \"USD\", \"excerpt\": \"exact sentence from text\"}]. " .
            "If a metric is missing, do not include it. Never output markdown formatting or code blocks, just raw JSON.";

        try {
            $response = $this->httpClient->request('POST', 'https://anthropic.com', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'max_tokens' => 2000,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => "Analyze this document:\n\n" . $fileContent]
                    ]
                ]
            ]);

            $data = $response->toArray();

            // Calculate the tokens cost
            $inputTokens = $data['usage']['input_tokens'] ?? 0;
            $outputTokens = $data['usage']['output_tokens'] ?? 0;
            $cost = ($inputTokens * 0.000003) + ($outputTokens * 0.000015);

            $rawContent = $data['content'][0]['text'] ?? '[]';
            $metrics = json_decode($rawContent, true);

            return [
                'success' => true,
                'metrics' => is_array($metrics) ? $metrics : [],
                'cost' => $cost
            ];
        } catch (
            ServerExceptionInterface|
            RedirectionExceptionInterface|
            ClientExceptionInterface|
            TransportExceptionInterface|
            DecodingExceptionInterface $e
        ) {
            return [
                'success' => false,
                'metrics' => [],
                'cost' => 0.00,
                'error' => $e->getMessage()
            ];
        }
    }
}

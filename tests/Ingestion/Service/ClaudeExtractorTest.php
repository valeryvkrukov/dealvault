<?php

namespace DealVault\Tests\Ingestion\Service;

use DealVault\Identity\Entity\Tenant;
use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\Ingestion\Service\ClaudeExtractor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ClaudeExtractorTest extends KernelTestCase
{
    private string $tmpFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpFilePath = sys_get_temp_dir() . '/claude_test_doc.txt';
        file_put_contents($this->tmpFilePath, 'Company ARR grew to 1500000 USD in 2026-Q3 according to reports.');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFilePath)) {
            unlink($this->tmpFilePath);
        }

        parent::tearDown();
    }

    public function testExtractMetricsCalculatesCostAndParsesJsonCorrectly(): void
    {
        // 1. Build fictive response from Anthropic API (Claude 3.5 Sonnet)
        $mockResponseBody = [
            'id' => 'msg_01X',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-3.5-sonnet-20241022',
            'content' => [
                [
                    'type' => 'text',
                    'text' => '[{"metric_name": "arr", "value": 1500000.00, "period": "2026-Q3", "currency": "USD", "excerpt": "Company ARR grew to 1500000 USD in 2026-Q3"}]'
                ]
            ],
            'usage' => [
                'input_tokens' => 1000, // 1000 * 0.000003 = 0.003
                'output_tokens' => 200 // 200 * 0.000015 = 0.003 -> Total: 0.006 USD
            ]
        ];

        // 2. Initialize Mock-client instead of real network call
        $mockResponse  = new MockResponse(json_encode($mockResponseBody), [
            'http_code' => 200,
            'headers' => ['content-type' => 'application/json']
        ]);
        $mockHttpClient = new MockHttpClient($mockResponse);

        // 3. Create IngestionSource entity mock
        $tenant = new Tenant('Test Capital Fund');
        $source = new IngestionSource($tenant, 'file');
        $source->setStoragePath($this->tmpFilePath);

        // 4. Start extractor testing
        $extractor = new ClaudeExtractor($mockHttpClient, 'sk-ant-test-key');
        $result = $extractor->extractMetrics($source);

        // 5. Check for calculation and parsing correctness
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['metrics']);

        $metric = $result['metrics'][0];
        $this->assertEquals('arr', $metric['metric_name']);
        $this->assertEquals(1500000.00, $metric['value']);
        $this->assertEquals('2026-Q3', $metric['period']);
        $this->assertEquals('USD', $metric['currency']);
        $this->assertEquals('Company ARR grew to 1500000 USD in 2026-Q3', $metric['excerpt']);

        // Check for strict math correctness for Claude tariff
        $this->assertEquals(0.006, $result['cost']);
    }

    public function testExtractMetricsHandlesApiFailureGracefully(): void
    {
        // Simulate break of Anthropic API (for ex. limit exceeded or timeout)
        $mockResponse = new MockResponse('Internal Server Error', ['http_code' => 500]);
        $mockHttpClient = new MockHttpClient($mockResponse);

        $tenant = new Tenant('Test Capital Fund');
        $source = new IngestionSource($tenant, 'file');
        $source->setStoragePath($this->tmpFilePath);

        $extractor = new ClaudeExtractor($mockHttpClient, 'sk-ant-test-key');
        $result = $extractor->extractMetrics($source);

        $this->assertFalse($result['success']);
        $this->assertEquals(0.00, $result['cost']);
        $this->assertArrayHasKey('error', $result);
    }
}

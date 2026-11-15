<?php

namespace DealVault\Ingestion\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailpitClient
{
    private HttpClientInterface $httpClient;
    private string $mailpitUrl;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        // Internal URL of the Mailpit container in the Docker network
        $this->mailpitUrl = 'http://mailer:8025/api/v1';
    }

    /**
     * Fetches the latest messages from Mailpit.
     *
     * @return array An array of messages.
     */
    public function fetchMessages(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->mailpitUrl . '/messages?limit=50');
            $data = $response->toArray();
            return $data['messages'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Downloads an attachment from Mailpit.
     *
     * @param string $messageId The ID of the message.
     * @param string $partId The ID of the attachment part.
     * @param string $outputFilePath The path to save the attachment.
     *
     * @return bool True if the attachment was downloaded successfully, false otherwise.
     */
    public function downloadAttachment(string $messageId, string $partId, string $outputFilePath): bool
    {
        try {
            $url = sprintf('%s/message/%s/part/%s', $this->mailpitUrl, $messageId, $partId);
            $response = $this->httpClient->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $content = $response->getContent();
            return file_put_contents($outputFilePath, $content) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Deletes the email from Mailpit after successful processing (pipeline idempotency)
     *
     * @param string $messageId The ID of the message to delete.
     * @return void
     */
    public function deleteMessage(string $messageId): void
    {
        try {
            $this->httpClient->request('DELETE', $this->mailpitUrl . '/messages', [
                'json' => ['IDs' => [$messageId]]
            ]);
        } catch (\Exception $e) {
            // Errors during email cleanup should not cause the worker to crash
        }
    }
}

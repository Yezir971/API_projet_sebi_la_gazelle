<?php
namespace App\Message;

class GenerateImageMessage
{
    private string $prompt;
    private string $apiKey;
    private int $userId;

    public function __construct(string $prompt, string $apiKey, int $userId)
    {
        $this->prompt = $prompt;
        $this->apiKey = $apiKey;
        $this->userId = $userId;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}

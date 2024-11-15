<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FireflyImageGenerator
{
    private $httpClient;

    // public function __construct(HttpClientInterface $httpClient)
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    // public function generateImage(): string
    public function generateImage(string $prompt)
    {
        try {
            // 1. Appeler l'API openai avec le prompt
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '512x512',  // Taille de l'image à générer
                ],
            ]);

            $data = $response->toArray();
            // $imageUrl = $data['url']; // Remplacer par la clé exacte si différente



            return $data;
        } catch (\Exception $e) {
            // Gestion des erreurs et les affiche
            throw new \Exception('Erreur lors de la génération de l\'image : ' . $e->getMessage());
        }

    }
}
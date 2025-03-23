<?php
namespace App\MessageHandler;

use App\Message\GenerateImageMessage;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Pictures;
use App\Entity\User;
use App\Entity\Users;
use App\Service\SavePictures;

class GenerateImageHandler implements MessageHandlerInterface
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private SavePictures $savePictures;

    public function __construct(HttpClientInterface $httpClient, EntityManagerInterface $entityManager, SavePictures $savePictures)
    {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->savePictures = $savePictures;
    }

    public function __invoke(GenerateImageMessage $message)
    {
        try {
            // ✅ 1. Appeler l’API OpenAI
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $message->getApiKey(),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'dall-e-3',
                    'prompt' => $message->getPrompt(),
                    'n' => 1,
                    'size' => '1024x1024',
                ],
            ]);

            $data = $response->toArray();
            $url = $data["data"][0]["url"];

            // ✅ 2. Télécharger l’image
            $this->savePictures->saveFile($url);
            $savedPath = $this->savePictures->getPathName();

            // ✅ 3. Enregistrer l’image en base de données
            $user = $this->entityManager->getRepository(Users::class)->find($message->getUserId());
            if (!$user) {
                throw new \Exception("Utilisateur non trouvé");
            }

            $newPicture = new Pictures();
            $newPicture->setSrc($savedPath);
            $newPicture->setUser($user);

            $this->entityManager->persist($newPicture);
            $this->entityManager->flush();

        } catch (\Exception $e) {
            // Gestion des erreurs
            error_log('Erreur génération image : ' . $e->getMessage());
        }
    }
}

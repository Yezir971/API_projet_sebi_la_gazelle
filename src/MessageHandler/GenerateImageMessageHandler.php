<?php
namespace App\MessageHandler;

use App\Message\GenerateImageMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Pictures;
use App\Entity\Users;
use App\Service\SavePictures;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class GenerateImageMessageHandler
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private SavePictures $savePictures;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        SavePictures $savePictures,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->savePictures = $savePictures;
        $this->logger = $logger;
    }

    public function __invoke(GenerateImageMessage $message)
    {
        try {
            $this->logger->info('Début du traitement du message', [
                'userId' => $message->getUserId(),
                'prompt' => $message->getPrompt()
            ]);

            // ✅ 1. Appeler l'API OpenAI
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
            $this->logger->info('Réponse API reçue', ['data' => $data]);
            
            if (!isset($data["data"][0]["url"])) {
                throw new \Exception("URL de l'image non trouvée dans la réponse");
            }

            $url = $data["data"][0]["url"];
            $this->logger->info('URL de l\'image récupérée', ['url' => $url]);

            // ✅ 2. Télécharger l'image
            $this->logger->info('Début de la sauvegarde de l\'image');
            $this->savePictures->saveFile($url);
            $savedPath = $this->savePictures->getPathName();
            $this->logger->info('Image sauvegardée', ['path' => $savedPath]);

            if (!$savedPath) {
                throw new \Exception("Échec de la sauvegarde de l'image");
            }

            // ✅ 3. Enregistrer l'image en base de données
            $this->logger->info('Recherche de l\'utilisateur', ['userId' => $message->getUserId()]);
            $user = $this->entityManager->getRepository(Users::class)->find($message->getUserId());
            if (!$user) {
                throw new \Exception("Utilisateur non trouvé");
            }

            $newPicture = new Pictures();
            $newPicture->setSrc($savedPath);
            $newPicture->setUser($user);

            $this->logger->info('Enregistrement en base de données');
            $this->entityManager->persist($newPicture);
            $this->entityManager->flush();

            $this->logger->info('Image enregistrée avec succès', [
                'pictureId' => $newPicture->getId(),
                'path' => $savedPath,
                'userId' => $user->getId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

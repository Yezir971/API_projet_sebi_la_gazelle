<?php

namespace App\Controller;

use App\Entity\Pictures;
use App\Entity\Users;
use App\Repository\PicturesRepository;
use App\Repository\UsersRepository;
use App\Service\FireflyImageGenerator;
use App\Service\JWTService;
use App\Service\SavePictures as ServiceSavePictures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class PicturesController extends AbstractController
{
    private $imageGenerator;
    private $entityManager;
    private $savePicture;

    public function __construct(
        FireflyImageGenerator $imageGenerator,
        EntityManagerInterface $entityManager,
        ServiceSavePictures $savePicture
    ) {
        $this->imageGenerator = $imageGenerator;
        $this->entityManager = $entityManager;
        $this->savePicture = $savePicture;
    }
    // Route qui permet d'avoir toutes les images 
    #[Route('/api/pictures', name: 'app_pictures', methods:['GET'])]
    public function getPictures(PicturesRepository $picturesRepository, SerializerInterface $serializer): JsonResponse
    {
        /** @var Users $user */
        $user = $this->getUser();
        if (!$user || !$user->getActivate()) {
            return new JsonResponse(['status' => 'error', 'message' =>"Votre compte n'est pas activé"], JsonResponse::HTTP_LOCKED);
        }

        $pictureList = $picturesRepository->findAll();
        $jsonPictureList = $serializer->serialize($pictureList, "json", ["groups" => "getPictures"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }
    #[Route('/api/pictures/user', name: 'app_pictures_by_id', methods:['GET'])]
    public function getPicturesById(UsersRepository $picturesRepository, SerializerInterface $serializer): JsonResponse
    {
        /** @var Users $user */
        $user = $this->getUser();
        if (!$user || !$user->getActivate()) {
            return new JsonResponse(['status' => 'error', 'message' =>"Votre compte n'est pas activé"], JsonResponse::HTTP_LOCKED);
        }

        $userId = $user->getId();
        $pictureList = $picturesRepository->find($userId);
        if (!$pictureList) {
            return new JsonResponse(['message' => "Vous n'avez aucune image"], Response::HTTP_NOT_FOUND);
        }

        $jsonPictureList = $serializer->serialize($pictureList, "json", ["groups" => "getPicturesByidUsers"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }

    // route qui va permettre de générer une image pour un utilisateur a l'aide d'un prompt déjà défini
    #[Route('/api/make-picture/user', name: 'app_add_pictures_with_ia', methods:['POST'])]
    public function addNewPictures(Request $request): JsonResponse 
    {
        try {
            /** @var Users $user */
            $user = $this->getUser();
            if (!$user || !$user->getActivate()) {
                return new JsonResponse(
                    ['status' => 423, 'message' => "Votre compte n'est pas activé"],
                    JsonResponse::HTTP_LOCKED
                );
            }
    
            // Récupération et validation des données
            $data = json_decode($request->getContent(), true);
            if (!isset($data['prompt'])) {
                return new JsonResponse(
                    ['status' => 400, 'message' => "Le prompt est requis"],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
    
            // Génération de l'image (opération potentiellement longue)
            $apikey = $this->getParameter('API_KEY');
            $filename = $this->imageGenerator->generateImage($data['prompt'], $apikey);
            
            if (!isset($filename["data"][0]["url"])) {
                throw new \Exception("Erreur lors de la génération de l'image");
            }
    
            // Sauvegarde du fichier
            $url = $filename["data"][0]["url"];
            $this->savePicture->saveFile($url);
            
            // Récupérer une connexion fraîche à la base de données
            $this->entityManager->getConnection()->close();
            $this->entityManager->getConnection()->connect();
            
            // Création et persistance de l'entité Picture
            $newPicture = new Pictures();
            $newPicture->setSrc($this->savePicture->getPathName());
            $newPicture->setUser($user);
            
            $this->entityManager->persist($newPicture);
            $this->entityManager->flush();
    
            return new JsonResponse(
                ['status' => 'success', 'filename' => $filename],
                JsonResponse::HTTP_CREATED
            );
    
        } catch (\Exception $e) {
            return new JsonResponse(
                ['status' => 'error', 'message' => "Une erreur est survenue lors de la génération de l'image: " . $e->getMessage()],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }


    // Route qui permet d'assigner un avatar à un utilisateur uniquement grâce à l'id d'une de ses pictures 
    #[Route('api/picture/setavatar/{avatarId}', name:'set_avatar', methods:['PUT'])]
    public function setAvatar(EntityManagerInterface $entityManager, int $avatarId): JsonResponse
    {
        /** @var Users $user */
        $user = $this->getUser();
        if (!$user || !$user->getActivate()) {
            return new JsonResponse(
                ['status' => 'error', 'message' => "Votre compte n'est pas activé"],
                JsonResponse::HTTP_LOCKED
            );
        }

        // Récupérer l'avatar par ID
        $avatar = $entityManager->getRepository(Pictures::class)->find($avatarId);
        if (!$avatar) {
            return new JsonResponse(['error' => 'Avatar non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier si l'avatar appartient à l'utilisateur
        if ($avatar->getUser() !== $user) {
            return new JsonResponse(
                ["error" => "Cet avatar n'appartient pas à l'utilisateur"],
                Response::HTTP_FORBIDDEN
            );
        }

        // Assigner l'avatar à l'utilisateur
        $user->setAvatar($avatar);
        $entityManager->flush();

        return new JsonResponse(
            ['message' => 'Avatar modifié avec succès'],
            Response::HTTP_OK
        );
    }





   

}

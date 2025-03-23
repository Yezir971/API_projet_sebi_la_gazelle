<?php

namespace App\Controller;

use App\Entity\Pictures;
use App\Entity\Users;
use App\Message\GenerateImageMessage;
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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class PicturesController extends AbstractController
{
    private $imageGenerator;

    public function __construct(FireflyImageGenerator $imageGenerator)
    {
        $this->imageGenerator = $imageGenerator;
    }
    // Route qui permet d'avoir toutes les images 
    #[Route('/api/pictures', name: 'app_pictures', methods:['GET'])]
    public function getPictures(PicturesRepository $picturesRepository, SerializerInterface $serializer): JsonResponse
    {
        // condition pour voir si le compte est activer 

        // récupère les informations de l'utilisateur connecter
        $user = $this->getUser();
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$user->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }

        // condition pour voir si le compte est activer 
        $pictureList = $picturesRepository->findAll();
        $jsonPictureList = $serializer->serialize($pictureList, "json", ["groups" => "getPictures"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }
    #[Route('/api/pictures/user', name: 'app_pictures_by_id', methods:['GET'])]
    public function getPicturesById(UsersRepository $picturesRepository, SerializerInterface $serializer): JsonResponse
    {
        // récupère les informations de l'utilisateur connecter
        $user = $this->getUser();

        // condition pour voir si le compte est activer 

        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$user->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }

        // condition pour voir si le compte est activer 


        // on récupère l'id de l'utilisateur 
        $userId = $user->getId();
        $pictureList = $picturesRepository->find($userId);
        // si il n'y a pas de picture on retourne un message avec un code 404
        if (!$pictureList) {
            return new JsonResponse(['message' => "Vous n\'avez aucune image"], Response::HTTP_NOT_FOUND);
        }
        // sinon on retourne les images avec un code 200
        $jsonPictureList = $serializer->serialize($pictureList, "json", ["groups" => "getPicturesByidUsers"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }

    // route qui va permettre de générer une image pour un utilisateur a l'aide d'un prompt déjà défini
    // public function addNewPictures(Request $request, ServiceSavePictures $savePicture, ObjectManager $manager, #[Autowire(value:'%API_KEY%')] string $apikey): JsonResponse
    #[Route('/api/make-picture/user', name: 'app_add_pictures_with_ia', methods: ['POST'])]
    public function addNewPictures(Request $request, MessageBusInterface $bus): JsonResponse
    {
        $user = $this->getUser();
        if (!$user->getActivate()) {
            return new JsonResponse(['status' => 423, 'message' => "Votre compte n'est pas activé."], JsonResponse::HTTP_LOCKED);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['prompt'])) {
            return new JsonResponse(['status' => 400, 'message' => 'Prompt manquant'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $prompt = $data['prompt'];
        $apikey = $this->getParameter('API_KEY');

        // ✅ Envoi de la requête en tâche de fond avec Messenger
        $bus->dispatch(new GenerateImageMessage($prompt, $apikey, $user->getId()));

        return new JsonResponse(['status' => 'success', 'message' => 'L’image est en cours de génération'], JsonResponse::HTTP_ACCEPTED);
    }


    // Route qui permet d'assigner un avatar à un utilisateur uniquement grâce à l'id d'une de ses pictures 
    #[Route('api/picture/setavatar/{avatarId}', name:'set_avatar', methods:['PUT'])]
    public function setAvatar(EntityManagerInterface $entityManager, int $avatarId): JsonResponse
    {
        // condition pour voir si le compte est activer 
        // récupère les informations de l'utilisateur connecter
        $userActivate = $this->getUser();
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$userActivate->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }
        // condition pour voir si le compte est activer 


        // récupère les informations de l'utilisateur connecter
        $idUser = $this->getUser();
        

        $user = $entityManager->getRepository(Users::class)->find($idUser);

        // Récupérer l'avatar par ID
        $avatar = $entityManager->getRepository(Pictures::class)->find($avatarId);
        if (!$avatar) {
            return new JsonResponse(['error' => 'Avatar non trouvé '], 404);
        }

        // Vérifier si l'avatar appartient à l'utilisateur
        if ($avatar->getUser() !== $user) {
            return new JsonResponse(["error" => "Cet avatar n'appartient pas à l'utilisateur cible !"], 403);
        }

        // Assigner l'avatar à l'utilisateur
        $user->setAvatar($avatar);

        // Sauvegarder les modifications
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Vous avez bien modifier votre avatar !',
        ], JsonResponse::HTTP_OK);

    }





   

}

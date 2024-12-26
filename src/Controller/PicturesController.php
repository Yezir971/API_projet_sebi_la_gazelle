<?php

namespace App\Controller;

use App\Entity\Pictures;
use App\Entity\Users;
use App\Repository\PicturesRepository;
use App\Repository\UsersRepository;
use App\Service\FireflyImageGenerator;
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

    public function __construct(FireflyImageGenerator $imageGenerator)
    {
        $this->imageGenerator = $imageGenerator;
    }
    // Route qui permet d'avoir toutes les images 
    #[Route('/api/pictures', name: 'app_pictures', methods:['GET'])]
    public function getPictures(PicturesRepository $picturesRepository, SerializerInterface $serializer): JsonResponse
    {
        $pictureList = $picturesRepository->findAll();
        $jsonPictureList = $serializer->serialize($pictureList, "json", ["groups" => "getPictures"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }
    #[Route('/api/pictures/user/{id}', name: 'app_pictures_by_id', methods:['GET'])]
    public function getPicturesById(UsersRepository $picturesRepository, SerializerInterface $serializer, int $id): JsonResponse
    {
        

        // on véreifie si l'utilisateur connecter passe bien son id dans la route 
        if($this->getUser()->id == $id){
            $pictureList = $picturesRepository->find($id);
            $jsonPictureList = $serializer->serialize($pictureList, "json", ["groups" => "getPicturesByidUsers"]);
            return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
        }
        return new JsonResponse(['status' => 403, 'message' => "l'id n'est pas celui de l'utilisateur."], 403);
    }

    // route qui va permettre de générer une image pour un utilisateur a l'aide d'un prompt déjà défini
    #[Route('/api/pictures/user', name: 'app_add_pictures_with_ia', methods:['GET'])]
    // public function addNewPictures(Request $request, ServiceSavePictures $savePicture, ObjectManager $manager, #[Autowire(value:'%API_KEY%')] string $apikey): JsonResponse
    public function addNewPictures(EntityManagerInterface $entityManager, Request $request, ServiceSavePictures $savePicture, ObjectManager $manager): JsonResponse
    {
        
        // récupère les informations de l'utilisateur connecter
        $userLog = $this->getUser();
        // $userTarget = $user->find($idUser);
        $user = $entityManager->getRepository(Users::class)->find($userLog);
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$user->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }

        $data = json_decode($request->getContent(), true);
        $prompt = $data['prompt'];
        $apikey = $this->getParameter(name: 'API_KEY');
        // Générer l'image on utilise la méthode generateImage en lui passant en apramètre le prompt et la clé api du .env
        $filename = $this->imageGenerator->generateImage($prompt, $apikey);
        
        $url = $filename["data"][0]["url"];
        // $savePicture->saveFile($url);
        $savePicture->saveFile($url);

        // on défini le nom de la nouvelle image 
        $newPicture = new Pictures();
        $newPicture->setSrc($savePicture->getPathName());
        // on récupère les informations de l'utilisateurs qui est actuellement connecter 
        $newPicture->setUser($this->getUser());
        // On envoie dans la base de données les nouvelles informations de l'image enregistrer
        $manager->persist($newPicture);
        $manager->flush();

        // return new JsonResponse(['status' => 'success', 'filename' => $data], JsonResponse::HTTP_CREATED);
        return new JsonResponse(['status' => 'success', 'filename' => $filename], JsonResponse::HTTP_CREATED);
    }


    // Route qui permet d'assigner un avatar à un utilisateur uniquement grâce à l'id d'une de ses pictures 
    #[Route('api/picture/setavatar/{avatarId}', name:'set_avatar', methods:['POST'])]
    public function setAvatar(EntityManagerInterface $entityManager, int $avatarId): JsonResponse
    {

        // récupère les informations de l'utilisateur connecter
        $idUser = $this->getUser();
        // $userTarget = $user->find($idUser);
        

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

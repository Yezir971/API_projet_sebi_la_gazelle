<?php

namespace App\Controller;

use App\Entity\Pictures;
use App\Repository\PicturesRepository;
use App\Repository\UsersRepository;
use App\Service\FireflyImageGenerator;
use App\Service\SavePictures as ServiceSavePictures;
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
    #[Route('/api/pictures', name: 'app_pictures', methods:['GET'])]
    public function getPictures(PicturesRepository $picturesRepository, SerializerInterface $serializer): JsonResponse
    {
        // return $this->json([
        //     'message' => 'Welcome to your new controller!',
        //     'path' => 'src/Controller/PicturesController.php',
        // ]);
        $pictureList = $picturesRepository->findAll();
        $jsonPictureList = $serializer->serialize($pictureList, "json", ["groups" => "getPictures"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }
    #[Route('/api/pictures/user/{id}', name: 'app_pictures_by_id', methods:['GET'])]
    public function getPicturesById(UsersRepository $picturesRepository, SerializerInterface $serializer, int $id): JsonResponse
    {
        $pictureList = $picturesRepository->find($id);
        $jsonPictureList = $serializer->serialize($pictureList, "json", ["groups" => "getPicturesByidUsers"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }

    // route qui va permettre de générer une image pour un utilisateur a l'aide d'un prompt pré défini
    #[Route('/api/pictures/user', name: 'app_add_pictures_with_ia', methods:['GET'])]
    // public function addNewPictures(Request $request, ServiceSavePictures $savePicture, ObjectManager $manager, #[Autowire(value:'%API_KEY%')] string $apikey): JsonResponse
    public function addNewPictures(Request $request, ServiceSavePictures $savePicture, ObjectManager $manager): JsonResponse
    {
    $data = json_decode($request->getContent(), true);
    $prompt = $data['prompt'];
    $apikey = $this->getParameter(nam: 'API_KEY');
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
}

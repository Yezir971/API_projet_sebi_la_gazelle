<?php

namespace App\Controller;

use App\Entity\Scores;
use App\Entity\Users;
use App\Repository\ScoresRepository;
use App\Repository\UsersRepository;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ScoresController extends AbstractController
{
    #[Route('/api/scores', name: 'app_scores', methods:['GET'])]
    public function getAllSores(ScoresRepository $scoresRepository, SerializerInterface $serializer): JsonResponse
    {
        // return $this->json([
        //     'message' => 'Welcome to your new controller!',
        //     'path' => 'src/Controller/ScoresController.php',
        // ]);
        $scoreList = $scoresRepository->findAll();
        $jsonPictureList = $serializer->serialize($scoreList, "json", ["groups" => "getScore"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }
    #[Route('/api/scores/user/{id}', name: 'app_scores_by_id', methods:['GET'])]
    public function getScoresByIdUser(UsersRepository $scoresRepository, SerializerInterface $serializer, int $id): JsonResponse
    {
        $scoreList = $scoresRepository->find($id);
        $jsonPictureList = $serializer->serialize($scoreList, "json", ["groups" => "getScoreById"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }
    #[Route('/api/score/user', name: 'app_add_scores_user', methods:['POST'])]
    public function setScoreUser(UsersRepository $scoresRepository, SerializerInterface $serializer, int $id): JsonResponse
    {
        $scoreList = $scoresRepository->find($id);
        $jsonPictureList = $serializer->serialize($scoreList, "json", ["groups" => "getScoreById"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }

    // permet d'ajouter un score a l'utilisateur qui est actuelement connecter 
    #[Route('/api/setscore', name: 'app_user_set_score', methods:['POST'])]
    public function signUp(Request $request,ObjectManager $manager, ValidatorInterface $validator,SerializerInterface $serializer): JsonResponse
    {
        // On récupére les données JSON du corps de la requête
        $data = json_decode($request->getContent(), true);
        // On récupère l'utilisateur qui est actuelement connnecter 
        $user = $this->getUser();

        // Vérifier si les données sont bien envoyées en JSON et sont valides
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], JsonResponse::HTTP_BAD_REQUEST);
        }
        // On retourne une JSON respons avec un message d'erreur si il y a un des champs qui manque. 
        if(!isset($data["score"]) || !isset($data["namegame"]) ) {
            return new JsonResponse(['error' => 'Body request not compete'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // --------------------------------- Traitement des informations et envoie vers la BDD ---------------------------------------
        $newScoreUser = new Scores();
        $newScoreUser->setScore($data["score"]);
        $newScoreUser->setNameGame($data["namegame"]);
        $newScoreUser->setUser($user);

        // On vérifie également si tout nos champs sont bien remplie grâce à Validator et aux Asserts que l'on a mis dans l'entity Users
        $errors = $validator->validate($newScoreUser);
        // Si il y a 1 ou + d'erreur on retourne une jsonResponse qui va contenir l'ensembles des erreurs 
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $manager->persist($newScoreUser);
        // On envoie vers la bdd 
        $manager->flush();
        // --------------------------------- Traitement des informations et envoie vers la BDD ---------------------------------------



        // Si les données sont valides on retourne un message de succès avec les data 
        return new JsonResponse([
            'message' => 'Données reçues avec succès !',
            'data' => $data,
        ], JsonResponse::HTTP_OK);
    
    }
}

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
    // route qui va permettre de voir tout les scores des utilisateurs 
    #[Route('/api/scores', name: 'app_scores', methods:['GET'])]
    public function getAllSores(ScoresRepository $scoresRepository, SerializerInterface $serializer): JsonResponse
    {
        // condition pour voir si le compte est activer 
        // récupère les informations de l'utilisateur connecter
        $userActivate = $this->getUser();
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$userActivate->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }
        // condition pour voir si le compte est activer 
        $scoreList = $scoresRepository->findAll();
        $jsonPictureList = $serializer->serialize($scoreList, "json", ["groups" => "getScore"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }
    // route qui va permettre de voir le socre d'un utilisateur grâce a son id 
    #[Route('/api/scores/user', name: 'app_scores_by_id', methods:['GET'])]
    public function getScoresByIdUser(UsersRepository $scoresRepository, SerializerInterface $serializer): JsonResponse
    {
        // condition pour voir si le compte est activer 

        // récupère les informations de l'utilisateur connecter
        $user = $this->getUser();
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$user->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }
        // condition pour voir si le compte est activer 

        // Récupère les informations de l'utilisateur connecter 
        // récupère son id 
        $userId = $user->getId();

        $scoreList = $scoresRepository->find($userId);
        $jsonPictureList = $serializer->serialize($scoreList, "json", ["groups" => "getScoreById"]);
        return new JsonResponse($jsonPictureList, Response::HTTP_OK,[], true);
    }


    // permet d'ajouter un score a l'utilisateur qui est actuelement connecter grâce à son token
    #[Route('/api/setscore', name: 'app_user_set_score', methods:['POST'])]
    public function setScore(Request $request,ObjectManager $manager, ValidatorInterface $validator,SerializerInterface $serializer): JsonResponse
    {

        // On récupére les données JSON du corps de la requête
        $data = json_decode($request->getContent(), true);

        // condition pour voir si le compte est activer 

        // récupère les informations de l'utilisateur connecter
        $user = $this->getUser();
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$user->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }
        // condition pour voir si le compte est activer 

        // Vérifier si les données sont bien envoyées en JSON et sont valides
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], JsonResponse::HTTP_BAD_REQUEST);
        }
        // On retourne une JSON response avec un message d'erreur si il y a un des champs qui manque. 
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
    // Route qui va permettre d'avoir le meilleur score en fonction du nom entrer dans le body de la requete 
    #[Route("api/bestscore", name:"get_best_score", methods: ['POST'])]
    public function getBestScore(Request $request, ScoresRepository $score, SerializerInterface $serializer): JsonResponse
    {
        // condition pour voir si le compte est activer 
        // récupère les informations de l'utilisateur connecter
        $user = $this->getUser();
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$user->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }
        // condition pour voir si le compte est activer 

        // on récupère le contenu de la requete du body 
        $data = json_decode($request->getContent(), true);
        
        // Vérifier que le JSON contient le champ "nameGame"
        if (!isset($data['nameGame']) || empty($data['nameGame'])) {
            return new JsonResponse(
                ["message" => "Requête invalide. Le champ 'nameGame' est requis."],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }
        $nameGame = $data["nameGame"];
        // on vérifie si la nomenclature du body est respecter 
        $score = $score->findAllScoreByDesc($nameGame);
        $scoreSerialize = $serializer->serialize($score, "json", ["groups" => "getBestScore"]);
        // on essaye de return les scores trié par ordre décroissant, si on y arrive pas c'est que le nom du jex n'existe pas
        try {
            // Si les $score est vide c'est soit car le jeux n'existe pas encore soit parceque il y a une faute dans le nom du jeu
            if (empty($score)) {
                return new JsonResponse(
                    ["message" => "Aucun score trouvé pour le jeu '$nameGame'."],
                    JsonResponse::HTTP_NOT_FOUND
                );
            }
            // on retourne scores sous forme de json 
            return new JsonResponse($scoreSerialize, JsonResponse::HTTP_OK, [], true);
        } catch (\Throwable $th) {
            // erreur serveur
            return new JsonResponse(["message"=>$th->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

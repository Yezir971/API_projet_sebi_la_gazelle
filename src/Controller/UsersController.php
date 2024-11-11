<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UsersController extends AbstractController
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }
    #[Route('/api/users', name: 'app_all_users', methods:['GET'])]
    public function getAllUsers(UsersRepository $usersRepository, SerializerInterface $serializer): JsonResponse
    {
        // return $this->json([
        //     'message' => 'Welcome to your new controller!',
        //     'path' => 'src/Controller/UsersController.php',
        // ]);
        $userList = $usersRepository->findAll();
        $jsonUserList = $serializer->serialize($userList, "json", ["groups" => "getUsers"]);
        return new JsonResponse($jsonUserList, Response::HTTP_OK,[], true);
    }
    #[Route('/api/user/{id}', name: 'app_user_by_id', methods:['GET'])]
    public function getUserById(UsersRepository $usersRepository, SerializerInterface $serializer, int $id): JsonResponse
    {
        // return $this->json([
        //     'message' => 'Welcome to your new controller!',
        //     'path' => 'src/Controller/UsersController.php',
        // ]);
        $userList = $usersRepository->find($id);
        $jsonUserList = $serializer->serialize($userList, "json", ["groups" => "getUsers"]);
        return new JsonResponse($jsonUserList, Response::HTTP_OK,[], true);
    }


    #[Route('/api/signup', name: 'app_user_signup', methods:['POST'])]
    public function signUp(Request $request,ObjectManager $manager, ValidatorInterface $validator,SerializerInterface $serializer): JsonResponse
    {
        // On récupére les données JSON du corps de la requête
        $data = json_decode($request->getContent(), true);

        // Vérifier si les données sont bien envoyées en JSON et sont valides
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], JsonResponse::HTTP_BAD_REQUEST);
        }
        // On retourne une JSON respons avec un message d'erreur si il y a un des champs qui manque. 
        if(!isset($data["password"]) || !isset($data["email"]) || !isset($data["username"]) ) {
            return new JsonResponse(['error' => 'Body request not compete'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // --------------------------------- Traitement des informations et envoie vers la BDD ---------------------------------------
        $user = new Users();
        $user->setName($data["username"]);
        $user->setEmail($data["email"]);
        $user->setRoles(["ROLE USER"]);
        $user->setPassword($data["password"]);
        // On vérifie également si tout nos champs sont bien remplie grâce à Validator et aux Asserts que l'on a mis dans l'entity Users
        $errors = $validator->validate($user);
        // Si il y a 1 ou + d'erreur on retourne une jsonResponse qui va contenir l'ensembles des erreurs 
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        // Avnat d'envoyer a la bdd on hash le password. On le hash ici car sinon validate va vérifié si notre mot de passe hashé est conforme et non si notre mot de passe est conforme.
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $data["password"]));


        $manager->persist($user);
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

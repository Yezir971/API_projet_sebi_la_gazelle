<?php

namespace App\Controller;

use App\Entity\Pictures;
use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Service\JWTService;
// use App\Service\JWTService;
use App\Service\SendMailService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
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
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->logger = $logger;
        $this->userPasswordHasher = $userPasswordHasher;
    }
    // route qui permet d'avoir tout les utilisateurs 
    #[Route('/api/users', name: 'app_all_users', methods:['GET'])]
    public function getAllUsers(UsersRepository $usersRepository, SerializerInterface $serializer): JsonResponse
    {
        // condition pour voir si le compte est activer 
        // récupère les informations de l'utilisateur connecter
        $user = $this->getUser();
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$user->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }
        // condition pour voir si le compte est activer 

        $userList = $usersRepository->findAll();
        $jsonUserList = $serializer->serialize($userList, "json", ["groups" => "getUsers"]);
        return new JsonResponse($jsonUserList, Response::HTTP_OK,[], true);
    }


    // route pour avoir les info d'un utilisateur juste avec son token de connection
    #[Route('/api/user', name: 'app_user_by_id', methods:['GET'])]
    public function getUserById(UsersRepository $usersRepository, SerializerInterface $serializer): JsonResponse
    {
        // condition pour voir si le compte est activer 
        // récupère les informations de l'utilisateur connecter
        $user = $this->getUser();
        // si l'utilisateur n'a pas activer son compte on return directement un message d'erreur 
        if(!$user->getActivate()){
            return new JsonResponse(['status' => 'error', 'filename' =>"votre compte n'est pas activer"], JsonResponse::HTTP_LOCKED);
        }
        // condition pour voir si le compte est activer 

        // On récupère son id 
        $userId = $user->getId();
        $userList = $usersRepository->find($userId);
        $jsonUserList = $serializer->serialize($userList, "json", ["groups" => "getUsers"]);
        return new JsonResponse($jsonUserList, Response::HTTP_OK,[], true);
    }

    // route pour permettre aux utilisateurs de valider leur inscription grâce au token envoyé par mail
    #[Route('/api/signup/{token}', name: 'app_user_signup')]
    public function signUp($token, JWTService $jwt, EntityManagerInterface $entityManager): Response
    {
        
        $messagesError = [];

        // Vérification de la validité, de l'expiration et de la signature du token
        if (!$jwt->isValid($token)) {
            array_push($messagesError, "Votre token n'est plus valide.");
        }

        if ($jwt->isExpired($token)) {
            array_push($messagesError, "Votre token d'activation a expiré.");
        }

        if (!$jwt->check($token, $this->getParameter(name: 'APP_SECRET'))) {
            array_push($messagesError, "La signature de votre token a été modifiée.");
        }

        // Si le token est invalide ou expiré, on retourne une réponse d'erreur
        if (!empty($messagesError)) {
            return $this->render('base.html.twig', [
                "messages" => $messagesError,
                "redirect" => "Veuillez vous re-créer un compte.",
                "linkRedirect" => "retour-vers-inscription"
            ], new Response('', Response::HTTP_BAD_REQUEST));
        }

        // Récupérer le payload du token
        $payload = $jwt->getPayload($token);
        // Récupére l'ID de l'utilisateur depuis le payload et cherche l'utilisateur
        $id = $payload["id"];
        $user = $entityManager->getRepository(Users::class)->find($id);

        // Vérifie si l'utilisateur n'est pas dans la base de données
        if (!$user) {
            array_push($messagesError, "Votre compte n'a pas été activé.");
            return $this->render('base.html.twig', [
                "messages" => $messagesError,
                "redirect" => "Votre compte ne figure pas dans notre base de données.",
                "linkRedirect" => "login"
            ], new Response('', Response::HTTP_NOT_FOUND));
        }


        // Mettre à jour le champ activate
        $user->setActivate(true);
        
        // Enregistrer les modifications dans la base de données
        $entityManager->flush();

        array_push($messagesError, "Votre compte a bien été activé !");
        return $this->render('base.html.twig', [
            "messages" => $messagesError,
            "redirect" => "Vous pouvez vous connecter avec le lien suivant !",
            "linkRedirect" => "login"
        ], new Response('', Response::HTTP_OK));
    }



    // Route qui va valider si les champs sont corrects et va envoyer un email de confirmation si les inputs sont corrects 
    #[Route('api/validate-account', name:'validate', methods:['POST'])]
    public function validateAccount(ObjectManager $manager, Request $request, JWTService $jwt, ValidatorInterface $validator,SerializerInterface $serializer,SendMailService $mail ):JsonResponse
    {
        $start = microtime(true);


        // On récupére les données JSON du corps de la requête
        $data = json_decode($request->getContent(), true);

        // --------------------------------- Traitement des informations avant envoie du mail ---------------------------------------
        $user = new Users();
        $user->setActivate(false);
        $user->setName($data["username"]);
        $user->setEmail($data["email"]);
        $user->setRoles(["ROLE USER"]);
        $user->setPassword($data["password"]);



        // dd($user->getActivate());

        // Vérifier si les données sont bien envoyées en JSON et sont valides
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], JsonResponse::HTTP_BAD_REQUEST);
        }
        // On retourne une JSON response avec un message d'erreur si il y a un des champs qui manque. 
        if(!isset($data["password"]) || !isset($data["email"]) || !isset($data["username"]) ) {
            return new JsonResponse(['error' => 'Body request not compete'], JsonResponse::HTTP_BAD_REQUEST);
        }
        // On vérifie également si tout nos champs sont bien remplie grâce à Validator et aux Asserts que l'on a mis dans l'entity Users
        $errors = $validator->validate($user);
        // Si il y a 1 ou + d'erreur on retourne une jsonResponse qui va contenir l'ensembles des erreurs 
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        // Avnat d'envoyer a la bdd on hash le password. On le hash ici car sinon validate va vérifié si notre mot de passe hashé est conforme et non si notre mot de passe est conforme.
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $data["password"]));
        // On envoie vers la bdd 
        $manager->persist($user);
        $manager->flush();
        // --------------------------------- Traitement des informations avant envoie du mail ---------------------------------------


        // --------------------------------------------------- envoie du mail ------------------------------------------------------

        // // on génère le token de validation de compte de l'utilisateur 
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        // on fait le payload 
        $payload = [
            'username'=> $data["username"],
            'email'=>$data["email"],
            "id" => $user->getId()
        ];

        // on génère le token 
        $token = $jwt->generate($header,$payload, $this->getParameter(name: 'APP_SECRET'));
        

        // // on envoie un mail 
        $mail->send(
            'ahmedalyjames@gmail.com',
            // 'sebmlrd06@gmail.com',
            'james_ahmedaly@yahoo.com',
            // $data["email"],
            'Activation de votre compte sur le site sebi la gazelle',
            'register',
            [
                'user'=> $data,
                'token'=>$token
            ]
        );
        // --------------------------------------------------- envoie du mail ------------------------------------------------------
        $end = microtime(true);

        $this->logger->info('JWT decoding time: ' . ($end - $start) . ' seconds');
        return new JsonResponse([
            'message' => 'Un mail de validation vous à été envoyé !',
        ], JsonResponse::HTTP_OK);
    }

    // route pour vérifier si un token est valide 
    #[Route('/api/user/validate-token', name: 'app_user_validate_token', methods:['POST'])]
    public function validateToken(Request $request, JWTService $jwt): JsonResponse
    {
        // On récupére les données JSON du corps de la requête
        $token = json_decode($request->getContent(), true);
        // dd($token["token"]);
        // par défaut le message est que le token est valide 
        $message = "Votre token est valide";
        // le boolean de validité est également valide par défault 
        $bollValide = true;
        if(!$jwt->isValid($token["token"])){
            $message = "Votre token n'est pas valide.";
            $bollValide = false;

        }
        if($jwt->isExpired($token["token"])){
            $message = "Votre token a expiré.";
            $bollValide = false;

        }
        
        return new JsonResponse([
            'message' => $message,
            'isValide' => $bollValide
        ], JsonResponse::HTTP_OK);


    }






    // route pour envoyer le token par mail pour reset le mot de passe 
    #[Route('/change-password', name:'change_password', methods:['POST'])]
    public function changePwdByMail(SendMailService $mail,EntityManagerInterface $entityManager, JWTService $jwt ,Request $request, UsersRepository $userInfo) : Response
    {
        // On récupére les données JSON du corps de la requête
        $email = json_decode($request->getContent(), true);

        // récupération de l'id de l'utilisateur grâce a la méthode dans le repository
        $userId = $userInfo->findIdByMailUser($email['email']);
        if(!$userId){
            return new JsonResponse([
                'message' =>"L'adresse e-mail n'existe pas." ,
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // on récupère les informations de l'utilisateur sous forme de classe
        $data = $entityManager->getRepository(Users::class)->find($userId[0]['id']);

        // on encode le token 
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        // on fait le payload 
        $payload = [
            'email'=>$email,
            "id" => $userId[0]['id']
        ];
        // on génère le token 
        $token = $jwt->generate($header,$payload, $this->getParameter(name: 'APP_SECRET'));

        // on envoie le token par email
        $mail->send(
            'ahmedalyjames@gmail.com',
            // 'sebmlrd06@gmail.com',
            'james_ahmedaly@yahoo.com',
            // $data["email"],
            'Modification de votre mot de passe sur le site sebi la gazelle',
            'password',
            [
                'user'=> $data,
                'token'=>$token
            ]
        );
        return new JsonResponse([
            'message' =>"Un e-mail vous a été envoyé." ,
        ], JsonResponse::HTTP_OK);
    }

    // visuel du formulaire pour changer de mot de passe 
    #[Route('/change-password/{token}', name:'view_change_password')]
    public function viewChangePwd(string $token) : Response
    {
        return $this->render('change-password.html.twig', ["token" => $token, "message" =>""]);
    }






    // route pour changer le password d'un utilisateur 
    #[Route('/api/change-password/{token}', name:'change_password_api', methods:['POST'])]
    public function changePwd(string $token,Request $request, JWTService $jwt,UsersRepository $userInfo, EntityManagerInterface $entityManager,ValidatorInterface $validator,SerializerInterface $serializer ): JsonResponse 
    {
        // On récupére les données JSON du corps de la requête
        $email = json_decode($request->getContent(), true);

        // on récupère les infos dans le payload du token 
        $payload = $jwt->getPayload($token);
        
        
        // récupération de l'id de l'utilisateur grâce a la méthode dans le repository
        $userId = $userInfo->findIdByMailUser($payload['email']['email']);
        $user = $entityManager->getRepository(Users::class)->find($userId[0]['id']);
        // si on ne trouve pas l'utilisateur on retorne une errrur 404
        if(!$user){
            return new JsonResponse([], JsonResponse::HTTP_NOT_FOUND, [], true);
        }


        // On vérifie également si tout nos champs sont bien remplie grâce à Validator et aux Asserts que l'on a mis dans l'entity Users
        $errors = $validator->validate($user);
        // Si il y a 1 ou + d'erreur on retourne une jsonResponse qui va contenir l'ensembles des erreurs 
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        // on hash le mot de passe avant de l'envoyer a la bdd
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $email["password1"]));

        // Enregistrer les modifications dans la base de données
        $entityManager->flush();

        return new JsonResponse([
            'message' =>$email,
        ], JsonResponse::HTTP_OK);
    }
}

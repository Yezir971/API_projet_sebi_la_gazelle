<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Service\JWTService;
// use App\Service\JWTService;
use App\Service\SendMailService;
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

    // route pour permettre aux utilisateurs de valider leurs inscription grace au token envoyer par mail 
    #[Route('/api/signup/{token}', name: 'app_user_signup')]
    public function signUp($token, JWTService $jwt, UsersRepository $usersRepository, ObjectManager $manager)
    {

        // on vérifie si le token est valide et n'a pas expiré et n'a pas été modifier 
        if($jwt->isValid($token) && !$jwt->isExpired($token) && $jwt->check($token, $this->getParameter(name: 'APP_SECRET')) ){
            $payload = $jwt->getPayload($token);
            // on va chercher si notre utilisateur existe deja dans la bdd si il existe $user sera défini
            $user = $usersRepository->findOneBy(["name" => $payload['username']]);

            //On vérifie que l'utilisateur est dans la bdd et n'a pas encore activé son compte 
            if($user == null){
                // --------------------------------- Traitement des informations et envoie vers la BDD ---------------------------------------
                
                // on bind a la valeur activate a true pour pas que l'utilisateurs arrive a confirmer une 2ème fois son compte 
                // on crée un nouvelle utilisateur avec les informations contenu dans le token
                $user = new Users();
                $user->setActivate(true);
                $user->setName($payload["username"]);
                $user->setEmail($payload["email"]);
                $user->setRoles(["ROLE USER"]);
                $user->setPassword($payload["password"]);


                // On envoie vers la bdd 
                $manager->persist($user);
                $manager->flush();
                // --------------------------------- Traitement des informations et envoie vers la BDD ---------------------------------------

                // on retourne un message de succès 
                return $this->render('base.html.twig',[
                    "message"=>"Votre compte à bien été activé !",
                    "redirect"=>"vous pouvez vous connecter avec le lien suivant !",
                    "linkRedirect" => "bonJeu"
                ]);
                // return new JsonResponse([
                //     'message' => "Votre compte à bien été activé",
                // ], JsonResponse::HTTP_OK);
            }
        }

        // ici il y a un pb avec le token 

        return $this->render('base.html.twig', [
            "message"=>"errur avec le token !",
            "redirect"=>"Nous vous invitons à vous recréer un compte, ou à contacter le service technique.",
            "linkRedirect"=>"retour-vers-inscription"
        ]);
        // return new JsonResponse([
        //     'message' => "Votre compte est déja activé !",
        // ], JsonResponse::HTTP_BAD_REQUEST, []);
    }

    // route qui va valider si les champs sont correcte et va envoyer un email de confirmation si les inputs sont correcte 
    #[Route('api/validateAccount', name:'validate', methods:['GET'])]
    public function validateAccount( Request $request, JWTService $jwt, ValidatorInterface $validator,SerializerInterface $serializer,SendMailService $mail ):Response
    {
        // On récupére les données JSON du corps de la requête
        $data = json_decode($request->getContent(), true);

        // --------------------------------- Traitement des informations avant envoie du mail ---------------------------------------
        $user = new Users();
        $user->setName($data["username"]);
        $user->setEmail($data["email"]);
        $user->setRoles(["ROLE USER"]);
        $user->setPassword($data["password"]);

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
            "password" => $user->getPassword()
        ];

        // on génère le token 
        $token = $jwt->generate($header,$payload, $this->getParameter(name: 'APP_SECRET'));

        // // on envoie un mail 
        $mail->send(
            'ahmedalyjames@gmail.com',
            // 'sebmlrd06@gmail.com',
            'james_ahmedaly@yahoo.com',
            'Activation de votre compte sur le site sebi la gazelle',
            'register',
            [
                'user'=> $data,
                'token'=>$token
            ]
        );
        // --------------------------------------------------- envoie du mail ------------------------------------------------------

        return new JsonResponse([
            'message' => 'Un mail de validation vous à été envoyé !',
        ], JsonResponse::HTTP_OK);
    }


    // route pour debug les mails d'authentification
    // #[Route("testmail", name:'validate_test', methods:['GET'])]
    // public function testMail(){
    //     return $this->render('base.html.twig');
    // }



}

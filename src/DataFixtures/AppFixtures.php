<?php

namespace App\DataFixtures;

use App\Entity\Pictures;
use App\Entity\Scores;
use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;
    
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }
    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);
        $listUsers = [];

        for($i=0; $i<20 ; $i++){
            $user = new Users();
            $user->setName('SexyJames' . $i);
            $user->setEmail('legrosbg' . $i . '@gmail.com');
            $user->setRoles(["ROLE USER"]);
            $user->setPassword($this->userPasswordHasher->hashPassword($user, "Ketchup971@x2"));
            $manager->persist($user);
            $listUsers[] = $user; 
        }

        $listUsers[] = $user; 
        for($i=0; $i<20 ; $i++){
            $picture = new Pictures();
            $picture->setSrc('SexyJames' . $i . ".png");
            $picture->setUser($listUsers[array_rand($listUsers)]);
            $manager->persist($picture);
        }


        for($i =0; $i<20; $i++){
            $score = new Scores();
            $score->setNameGame("James le hiboux");
            $score->setScore(20);
            $score->setUser($listUsers[array_rand($listUsers)]);
            $manager->persist($score);
        }


        $manager->flush();
    }
}

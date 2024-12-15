<?php

namespace App\Entity;

use App\Repository\ScoresRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: ScoresRepository::class)]
class Scores
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getScore","getScoreById", "getBestScore"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getScore","getScoreById", "getBestScore"])]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le titre du jeux dois faire au moins {{ limit }} caractères.", maxMessage: "Le titre du jeux ne peut pas faire plus de {{ limit }} caractères.")]
    #[Assert\NotBlank(message: "Le titre du jeux est obligatoire.")]
    #[Assert\Type(type: "string", message: "Le nom du jeux doit être de type string.")]
    private ?string $name_game = null;

    #[ORM\Column]
    #[Groups(["getScore","getScoreById", "getBestScore"])]
    #[Assert\NotBlank(message: "Le score du jeux est obligatoire.")]
    #[Assert\Type(type: "integer", message: "Le score doit être de type integer.")]
    private ?int $score = null;

    #[ORM\ManyToOne(inversedBy: 'scores')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["getScore", "getBestScore"])]
    private ?Users $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNameGame(): ?string
    {
        return $this->name_game;
    }

    public function setNameGame(string $name_game): static
    {
        $this->name_game = $name_game;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(?Users $user): static
    {
        $this->user = $user;

        return $this;
    }
}

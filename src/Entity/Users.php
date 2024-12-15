<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: UsersRepository::class)]
// on indique que le nom et l'email des utilisateurs ne peuvent pas etre les meêmes pour éviter les problèmes d'identifications des utilisateurs sur le tableau des scores par exemple
#[UniqueEntity('email',  message: 'Cette adresse email est déjà utilisée.')]
#[UniqueEntity('name', message: "Ce nom d'utilisateur est déjà pris.")]
class Users implements UserInterface,PasswordAuthenticatedUserInterface
{


    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];


    #[ORM\Column]
    private bool $activate = false;



    // #[ORM\Column(type:'datetime', nullable:true)]
    // public ?\DateTimeInterface $lastlogout = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getUsers", "getScore","getScoreById","getPictures","getPicturesByidUsers", "getBestScore"])]
    public ?int $id = null;

    #[ORM\Column(name: 'name', length: 255, unique:true)]
    #[Groups(["getUsers", "getScore","getScoreById","getPictures","getPicturesByidUsers", "getBestScore"])]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le nom de l'utilisateur dois faire au moins {{ limit }} caractères.", maxMessage: "Le nom de l'utilisateur ne peut pas faire plus de {{ limit }} caractères.")]
    #[Assert\NotBlank(message: "Le nom de l'utilisateur est obligatoire.")]
    #[Assert\Type(type: "string", message: "Le nom l'utilisateur doit être de type string.")]
    private ?string $name = null;

    #[ORM\Column(name: 'email',length: 255,type:'string',  unique:true)]
    #[Groups(["getUsers"])]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le mail de l'utilisateur dois faire au moins {{ limit }} caractères.", maxMessage: "Le mail de l'utilisateur ne peut pas faire plus de {{ limit }} caractères.")]
    #[Assert\NotBlank(message: "Le mail de l'utilisateur est obligatoire.")]
    #[Assert\Email(message: "Le mail de l'utilisateur n'est pas valide.")]
    private ?string $email = null;

    // #[Groups(["getUsers"])]
    // private ?string $url = null;


    #[ORM\Column(type: Types::TEXT)]
    #[Assert\Regex(pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/",message: "Le mot de passe doit contenir au moins 8 caractères, dont une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial.")]
    #[Assert\NotBlank(message: "Le mot de passe de l'utilisateur ne peut pas être vide.")]
    private ?string $password = null;

    /**
     * @var Collection<int, Scores>
     */
    #[ORM\OneToMany(targetEntity: Scores::class, mappedBy: 'user')]
    #[Groups(["getScoreById"])]
    private Collection $scores;

    /**
     * @var Collection<int, Pictures>
     */
    #[ORM\OneToMany(targetEntity: Pictures::class, mappedBy: 'user')]
    #[Groups(["getPicturesByidUsers"])]
    private Collection $pictures;

    #[ORM\OneToOne(inversedBy: 'users', cascade: ['persist', 'remove'])]
    #[Groups(["getUsers"])]
    private ?Pictures $avatar = null;



    public function __construct()
    {
        $this->scores = new ArrayCollection();
        $this->pictures = new ArrayCollection();
    }
    
    public function getActivate(): ?bool
    {
        return $this->activate;
    }

    public function setActivate(bool $activate): self
    {
        $this->activate = $activate;

        return $this;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    // public function getLastLogout(): ?\DateTimeInterface
    // {
    //     return $this->lastlogout;

    // }
    // public function setLastlogout(?\DateTimeInterface $lastlogout): self
    // {
    //     $this->lastlogout = $lastlogout;
    //     return $this;

    // }



    /**
     * @return Collection<int, Scores>
     */
    public function getScores(): Collection
    {
        return $this->scores;
    }

    public function addScore(Scores $score): static
    {
        if (!$this->scores->contains($score)) {
            $this->scores->add($score);
            $score->setUser($this);
        }

        return $this;
    }

    public function removeScore(Scores $score): static
    {
        if ($this->scores->removeElement($score)) {
            // set the owning side to null (unless already changed)
            if ($score->getUser() === $this) {
                $score->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Pictures>
     */
    public function getPictures(): Collection
    {
        return $this->pictures;
    }

    public function addPicture(Pictures $picture): static
    {
        if (!$this->pictures->contains($picture)) {
            $this->pictures->add($picture);
            $picture->setUser($this);
        }

        return $this;
    }

    public function removePicture(Pictures $picture): static
    {
        if ($this->pictures->removeElement($picture)) {
            // set the owning side to null (unless already changed)
            if ($picture->getUser() === $this) {
                $picture->setUser(null);
            }
        }

        return $this;
    }

    public function getAvatar(): ?Pictures
    {
        return $this->avatar;
    }

    public function setAvatar(?Pictures $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }



    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Méthode getUsername qui permet de retourner le champ qui est utilisé pour l'authentification.
     *
     * @return string
     */
    public function getUsername(): string {
        return $this->getUserIdentifier();
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }
    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}

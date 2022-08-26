<?php

namespace App\Entity;

use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;

/**
* @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "author_detail",
 *          parameters = { "id" = "expr(object.getId())" }
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getAuthors")
 * )
 *
 *  @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "author_delete",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getAuthors", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 * 
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "author_update",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getAuthors", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 * 
 * @ORM\Entity(repositoryClass=AuthorRepository::class)
 */
class Author
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"getBooks", "getAuthors"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"getBooks", "getAuthors"})
     * @Assert\NotBlank(message="Le nom de l'auteur est obligatoire")
     * @Assert\Length(
     *      min = 1,
     *      max = 255,
     *      minMessage = "Le nom de l'auteur doit faire au moins {{ limit }} caractères",
     *      maxMessage="Le nom de l'auteur doit faire au maximum {{ limit }} caractères"
     * )
     */
    private $lastname;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"getBooks", "getAuthors"})
     */
    private $firstname;

    /**
     * @ORM\OneToMany(targetEntity=Book::class, mappedBy="author")
     * @Groups({"getAuthors"})
     */
    private $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(Book $book): self
    {
        if (!$this->books->contains($book)) {
            $this->books[] = $book;
            $book->setAuthor($this);
        }

        return $this;
    }

    public function removeBook(Book $book): self
    {
        if ($this->books->removeElement($book)) {
            // set the owning side to null (unless already changed)
            if ($book->getAuthor() === $this) {
                $book->setAuthor(null);
            }
        }

        return $this;
    }
}

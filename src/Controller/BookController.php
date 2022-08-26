<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use  Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BookController extends AbstractController
{
    /**
     * @Route("/api/books", name="book_all", methods={"GET"})
     */
    public function getAllBooks(
        Request $request, 
        BookRepository $bookRepository, 
        SerializerInterface $serializer,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        
        $idCache = "getAllBooks-" . $page . "-" . $limit;
        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer) {
            $item->tag("booksCache");
            $bookList = $bookRepository->findAllWithPagination($page, $limit);
            
            $context = SerializationContext::create()->setGroups(['getBooks']);
            return $serializer->serialize($bookList, 'json', $context);
        });

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    
    }

    /**
     * @Route("/api/books/{id}", name="book_detail", methods={"GET"})
     */
    public function getDetailBook(Book $book, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    /**
     * @Route("/api/books/{id}", name="book_delete", methods={"DELETE"})
     * @IsGranted("ROLE_ADMIN", message="Vous n'avez pas les droits suffisant pour supprimer un livre")
     */
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["booksCache"]);

        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/books", name="book_create", methods={"POST"})
     * @IsGranted("ROLE_ADMIN", message="Vous n'avez pas les droits suffisant pour créer un livre")
     */
    public function createBook(
        Request $request, Serializer $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        AuthorRepository $authorRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        // On vérifie les erreurs
        $errors = $validator->validate($book);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($book);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);

        $location = $urlGenerator->generate('book_detail', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    /**
     * @Route("/api/books/{id}", name="book_update", methods={"PUT"})
     * @IsGranted("ROLE_ADMIN", message="Vous n'avez pas les droits suffisant pour mettre à jour un livre")
     */
    public function updateBook(
        Request $request,
        SerializerInterface $serializer,
        Book $currentBook,
        EntityManagerInterface $em,
        AuthorRepository $authorRepository,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        // On vérifie les erreurs
        $errors = $validator->validate($currentBook);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
    
        $currentBook->setAuthor($authorRepository->find($idAuthor));

        $em->persist($currentBook);
        $em->flush();

        // On vide le cache.
        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}

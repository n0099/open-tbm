<?php

namespace App\Controller;

use App\Repository\ForumRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class ForumsController extends AbstractController
{
    public function __construct(private readonly ForumRepository $repository) {}

    #[Route('/api/forums')]
    public function query(): array
    {
        return $this->repository->getOrderedForums();
    }
}

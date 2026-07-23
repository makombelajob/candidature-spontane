<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CrawlerController extends AbstractController
{
    #[Route('/crawler', name: 'app_crawler')]
    public function index(): Response
    {
        return $this->render('crawler/index.html.twig', [
            'controller_name' => 'CrawlerController',
        ]);
    }
}

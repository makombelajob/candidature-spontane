<?php

namespace App\Controller;

use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

final class CrawlerController extends AbstractController
{
    private const DATABASE = '/var/data/data.db';

    private string $databasePath;

    public function __construct()
    {
        $this->databasePath = dirname(__DIR__, 2) . self::DATABASE;
    }

    private function getConnection(): PDO
    {
        $pdo = new PDO(
            sprintf('sqlite:%s', $this->databasePath)
        );

        $pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        return $pdo;
    }


    #[Route('/crawler/{siret}', name: 'app_crawler')]
    public function index(string $siret): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Connexion SQLite

        $pdo = $this->getConnection();

        // Recherche entreprise par SIRET

        $stmt = $pdo->prepare(
            '
            SELECT
                siren,
                siret,
                name,
                active_naf
            FROM company
            WHERE siret = :siret
            LIMIT 1
            '
        );

        $stmt->execute([
            ':siret' => $siret
        ]);

        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {

            throw $this->createNotFoundException(
                'Entreprise introuvable.'
            );

        }

        // Lancement Python

        $process = new Process([

            '/opt/python/bin/python',

            '/var/www/html/Python/search.py',

            '--siret',
            $company['siret'],

            '--siren',
            $company['siren'],

            '--naf',
            $company['active_naf'],

            '--name',
            $company['name'] ?? '',

        ]);

        $process->setWorkingDirectory(
            '/var/www/html'
        );

        $process->setTimeout(120);

        $process->run();

        if (!$process->isSuccessful()) {

            throw new ProcessFailedException(
                $process
            );

        }

        $results = json_decode(
            $process->getOutput(),
            true
        );

        if (!is_array($results)) {

            throw new \RuntimeException(
                'Réponse Python invalide : ' .
                $process->getOutput()
            );

        }

        return $this->render(
            'crawler/index.html.twig',
            [
                'company' => $company,
                'results' => $results,
            ]
        );
    }
}
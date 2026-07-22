<?php

namespace App\Controller;

use PDO;
use PDOStatement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilsController extends AbstractController
{
    private const DATABASE = '/var/data/data.db';

    private string $databasePath;

    public function __construct()
    {
        $this->databasePath = dirname(__DIR__, 2) . self::DATABASE;
    }


    public function getDatabasePath(): string
    {
        return $this->databasePath;
    }


    public function setDatabasePath(string $databasePath): void
    {
        $this->databasePath = $databasePath;
    }


    private function getConnection(): PDO
    {
        $pdo = new PDO(
            sprintf('sqlite:%s', $this->getDatabasePath())
        );

        $pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        return $pdo;
    }


    #[Route('/profils', name: 'app_profils')]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $databasePath = $this->getDatabasePath();


        if (!is_file($databasePath)) {

            return $this->render(
                'profils/index.html.twig',
                [
                    'controller_name' => 'ProfilsController',
                    'companies' => [],
                    'databasePath' => $databasePath,
                    'message' => 'Aucune base SQLite n\'a encore été créée.',
                    'search' => '',
                    'page' => 1,
                    'perPage' => 100,
                    'totalPages' => 1,
                    'totalItems' => 0,
                    'paginationItems' => [[
                        'number' => 1,
                        'isCurrent' => true,
                        'isEllipsis' => false,
                    ]],
                ]
            );
        }


        $pdo = $this->getConnection();


        $page = max(
            1,
            (int) $request->query->get('page', 1)
        );


        $perPage = max(
            10,
            min(
                200,
                (int) $request->query->get('perPage', 100)
            )
        );


        $search = trim(
            (string) $request->query->get('q', '')
        );


        $whereSql = '';
        $params = [];


        if ($search !== '') {

            $whereSql = '
                WHERE (
                    LOWER(siren) LIKE :search
                    OR LOWER(siret) LIKE :search
                    OR LOWER(name) LIKE :search
                    OR LOWER(city) LIKE :search
                    OR LOWER(active_naf) LIKE :search
                    OR LOWER(adresse) LIKE :search
                    OR LOWER(code_postal) LIKE :search
                )
            ';


            $params[':search'] = '%' . strtolower($search) . '%';
        }



        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM company ' . $whereSql
        );


        $this->bindParams(
            $countStmt,
            $params
        );


        $countStmt->execute();


        $totalItems = (int) $countStmt->fetchColumn();



        $totalPages = max(
            1,
            (int) ceil($totalItems / $perPage)
        );


        $page = min(
            $page,
            $totalPages
        );


        $offset = ($page - 1) * $perPage;



        $stmt = $pdo->prepare(
            'SELECT
                id,
                siren,
                siret,
                name,
                active_naf,
                adresse,
                code_postal,
                city,
                effectif,
                created_at
            FROM company '
            . $whereSql .
            ' ORDER BY id ASC
              LIMIT :limit OFFSET :offset'
        );



        $this->bindParams(
            $stmt,
            $params
        );


        $stmt->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );


        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );


        $stmt->execute();



        $companies = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );



        $start = $totalItems === 0
            ? 0
            : $offset + 1;


        $end = min(
            $offset + $perPage,
            $totalItems
        );



        $paginationItems = $this->buildPaginationItems(
            $page,
            $totalPages
        );



        if ($totalItems === 0) {

            $message = $search !== ''
                ? sprintf(
                    'Aucun résultat pour la recherche « %s ».',
                    $search
                )
                : 'Aucune entreprise n’a encore été importée.';


        } elseif ($search !== '') {

            $message = sprintf(
                'Affichage de %d à %d sur %d entreprise(s) correspondant à la recherche « %s ».',
                $start,
                $end,
                $totalItems,
                $search
            );


        } else {

            $message = sprintf(
                'Affichage de %d à %d sur %d entreprise(s).',
                $start,
                $end,
                $totalItems
            );
        }



        $data = [
            'controller_name' => 'ProfilsController',
            'companies' => $companies,
            'databasePath' => $databasePath,
            'message' => $message,
            'search' => $search,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'paginationItems' => $paginationItems,
            'start' => $start,
            'end' => $end,
        ];



        return $this->render(
            'profils/index.html.twig',
            $data
        );
    }



    private function buildPaginationItems(int $page, int $totalPages): array
    {
        if ($totalPages <= 1) {
            return [[
                'number' => 1,
                'isCurrent' => true,
                'isEllipsis' => false,
            ]];
        }


        $items = [];

        $window = 2;

        $startPage = max(
            1,
            $page - $window
        );

        $endPage = min(
            $totalPages,
            $page + $window
        );


        if ($startPage > 1) {

            $items[] = [
                'number' => 1,
                'isCurrent' => false,
                'isEllipsis' => false,
            ];


            if ($startPage > 2) {

                $items[] = [
                    'number' => null,
                    'isCurrent' => false,
                    'isEllipsis' => true,
                ];
            }
        }



        for ($pageNumber = $startPage; $pageNumber <= $endPage; ++$pageNumber) {

            $items[] = [
                'number' => $pageNumber,
                'isCurrent' => $pageNumber === $page,
                'isEllipsis' => false,
            ];
        }



        if ($endPage < $totalPages) {

            if ($endPage < $totalPages - 1) {

                $items[] = [
                    'number' => null,
                    'isCurrent' => false,
                    'isEllipsis' => true,
                ];
            }


            $items[] = [
                'number' => $totalPages,
                'isCurrent' => false,
                'isEllipsis' => false,
            ];
        }


        return $items;
    }



    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {

            $stmt->bindValue(
                $name,
                $value,
                PDO::PARAM_STR
            );
        }
    }
}

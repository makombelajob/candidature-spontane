<?php

namespace App\Repository;

use App\Entity\Company;

final class CompanyRepository
{
    private ?string $databasePath = null;

    public function __construct(?string $databasePath = null)
    {
        $this->databasePath = $databasePath;
    }

    public function initialize(string $databasePath): void
    {
        $this->databasePath = $databasePath;
        $pdo = $this->getPdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS company (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                siren TEXT,
                siret TEXT,
                name TEXT,
                active_naf TEXT,
                adresse TEXT,
                code_postal TEXT,
                city TEXT,
                effectif TEXT,
                created_at TEXT,
                phone TEXT,
                email TEXT
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_company_siren ON company(siren)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_company_city ON company(city)');
    }

    public function insertBatch(array $companies): void
    {
        if ($companies === []) {
            return;
        }

        $pdo = $this->getPdo();
        $statement = $pdo->prepare(
            'INSERT INTO company (siren, siret, name, active_naf, adresse, code_postal, city, effectif, created_at, phone, email)
            VALUES (:siren, :siret, :name, :active_naf, :adresse, :code_postal, :city, :effectif, :created_at, :phone, :email)'
        );

        $pdo->beginTransaction();
        try {
            foreach ($companies as $company) {
                $statement->execute([
                    ':siren' => $company->getSiren(),
                    ':siret' => $company->getSiret(),
                    ':name' => $company->getName(),
                    ':active_naf' => $company->getActiveNaf(),
                    ':adresse' => $company->getAdresse(),
                    ':code_postal' => $company->getCodePostal(),
                    ':city' => $company->getCity(),
                    ':effectif' => $company->getEffectif(),
                    ':created_at' => $company->getCreatedAt(),
                    ':phone' => $company->getPhone(),
                    ':email' => $company->getEmail(),
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function count(): int
    {
        return (int) $this->getPdo()->query('SELECT COUNT(*) FROM company')->fetchColumn();
    }

    public function getDatabasePath(): string
    {
        return $this->databasePath ?? $this->resolveDefaultPath();
    }

    private function getPdo(): \PDO
    {
        $databasePath = $this->getDatabasePath();
        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $pdo = new \PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }

    private function resolveDefaultPath(): string
    {
        return dirname(__DIR__, 2) . '/var/data/data.db';
    }
}

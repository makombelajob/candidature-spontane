<?php

namespace App\Service;

use App\Entity\Company;
use App\Repository\CompanyRepository;

final class SireneImporter
{
    public function __construct(private readonly ?CompanyRepository $repository = null)
    {
    }

    public function import(string $csvPath, ?string $databasePath = null, int $batchSize = 1000, ?callable $progressCallback = null): int
    {
        if (!is_file($csvPath)) {
            throw new \RuntimeException(sprintf('CSV file not found: %s', $csvPath));
        }

        $repository = $this->repository ?? new CompanyRepository($databasePath);
        $repository->initialize($databasePath ?? $repository->getDatabasePath());

        $stream = $this->openStream($csvPath);
        if ($stream['resource'] === null) {
            throw new \RuntimeException(sprintf('Unable to open CSV file: %s', $csvPath));
        }

        $delimiter = null;
        $header = $this->readCsvLine($stream, $delimiter);
        if ($header === false) {
            $this->closeStream($stream, $csvPath);
            throw new \RuntimeException('CSV file is empty.');
        }

        $normalizedHeader = array_map([$this, 'normalizeHeader'], $header);
        $batch = [];
        $processed = 0;

        while (($row = $this->readCsvLine($stream, $delimiter)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $company = $this->mapRow($row, $normalizedHeader);
            if ($company !== null) {
                $batch[] = $company;
            }

            if (count($batch) >= $batchSize) {
                $repository->insertBatch($batch);
                $processed += count($batch);
                $batch = [];
                if ($progressCallback !== null) {
                    $progressCallback($processed);
                }
            }
        }

        $this->closeStream($stream, $csvPath);

        if ($batch !== []) {
            $repository->insertBatch($batch);
            $processed += count($batch);
            if ($progressCallback !== null) {
                $progressCallback($processed);
            }
        }

        return $processed;
    }

    private function openStream(string $csvPath): array
    {
        if (str_ends_with($csvPath, '.gz')) {
            $handle = gzopen($csvPath, 'rb');
            if ($handle === false) {
                return ['resource' => null, 'gzip' => true];
            }

            return ['resource' => $handle, 'gzip' => true];
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            return ['resource' => null, 'gzip' => false];
        }

        return ['resource' => $handle, 'gzip' => false];
    }

    private function closeStream(array $stream, string $csvPath): void
    {
        if ($stream['resource'] === null) {
            return;
        }

        if ($stream['gzip']) {
            gzclose($stream['resource']);
            return;
        }

        fclose($stream['resource']);
    }

    private function readCsvLine(array $stream, ?string &$delimiter = null): array|false
    {
        $line = $this->readLine($stream);
        if ($line === false) {
            return false;
        }

        $line = rtrim($line, "\r\n");
        if ($line === '') {
            return [];
        }

        if ($delimiter === null) {
            $delimiter = str_contains($line, ';') ? ';' : ',';
        }

        return str_getcsv($line, $delimiter);
    }

    private function readLine(array $stream): string|false
    {
        if ($stream['gzip']) {
            return gzgets($stream['resource'], 8192);
        }

        return fgets($stream['resource'], 8192);
    }

    private function mapRow(array $row, array $header): ?Company
    {
        $values = [];
        foreach ($header as $index => $columnName) {
            $values[$columnName] = $row[$index] ?? null;
        }

        $company = new Company();
        $company->setSiren($this->resolveValue($values, ['siren']));
        $company->setSiret($this->resolveValue($values, ['siret']));
        $company->setName($this->resolveValue($values, ['name', 'denominationunitelegale', 'denominationunitelegale', 'nom_complet', 'denomination_unite_legale', 'denominationusuelleetablissement', 'enseigne', 'denominationusuelle']));
        $company->setActiveNaf($this->resolveValue($values, ['active_naf', 'activiteprincipaleetablissement', 'activite_principale', 'activiteprincipaleregistremetiersetablissement']));
        $company->setAdresse($this->resolveValue($values, ['adresse', 'adresseetablissement', 'libellevoieetablissement', 'complementadresseetablissement']));
        $company->setCodePostal($this->resolveValue($values, ['code_postal', 'codepostaletablissement']));
        $company->setCity($this->resolveValue($values, ['city', 'libellecommuneetablissement', 'commune']));
        $company->setEffectif($this->resolveValue($values, ['effectif', 'trancheeffectifsetablissement']));
        $company->setCreatedAt($this->resolveValue($values, ['created_at', 'datecreationetablissement']));
        $company->setPhone(null);
        $company->setEmail(null);

        if ($company->getSiren() === null && $company->getSiret() === null && $company->getName() === null) {
            return null;
        }

        return $company;
    }

    private function resolveValue(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($values[$key]) && $values[$key] !== '') {
                return (string) $values[$key];
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($header)) ?? '';

        return $normalized;
    }
}

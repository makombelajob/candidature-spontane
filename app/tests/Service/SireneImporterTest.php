<?php

namespace App\Tests\Service;

use App\Service\SireneImporter;
use PHPUnit\Framework\TestCase;

final class SireneImporterTest extends TestCase
{
    public function testImportCreatesSqliteDatabaseAndPersistsRows(): void
    {
        $tmpDir = sys_get_temp_dir() . '/sirene-import-' . uniqid('', true);
        mkdir($tmpDir, 0777, true);

        $csvPath = $tmpDir . '/sample.csv';
        $dbPath = $tmpDir . '/data.db';

        file_put_contents($csvPath, implode(PHP_EOL, [
            'siren,siret,denominationUniteLegale,activitePrincipaleEtablissement,adresseEtablissement,codePostalEtablissement,libelleCommuneEtablissement,trancheEffectifsEtablissement,dateCreationEtablissement',
            '830998175,83099817500010,TEST SARL,56.10A,1 rue de Paris,75001,Paris,01,20200101',
            '844001234,84400123400012,EXAMPLE SAS,62.01Z,10 avenue de Lyon,69002,Lyon,02,20191201',
        ]) . PHP_EOL);

        $importer = new SireneImporter();
        $importer->import($csvPath, $dbPath, 2);

        $pdo = new \PDO('sqlite:' . $dbPath);
        $rows = $pdo->query('SELECT siren, name FROM company ORDER BY siren')->fetchAll(\PDO::FETCH_ASSOC);

        self::assertCount(2, $rows);
        self::assertSame('830998175', $rows[0]['siren']);
        self::assertSame('TEST SARL', $rows[0]['name']);
        self::assertSame('844001234', $rows[1]['siren']);
        self::assertSame('EXAMPLE SAS', $rows[1]['name']);
    }

    public function testImportSupportsSemicolonSeparatedAndGzipCsv(): void
    {
        $tmpDir = sys_get_temp_dir() . '/sirene-import-' . uniqid('', true);
        mkdir($tmpDir, 0777, true);

        $csvPath = $tmpDir . '/stock.csv.gz';
        $dbPath = $tmpDir . '/data.db';

        $content = implode(PHP_EOL, [
            'siren;siret;denominationUniteLegale;activitePrincipaleEtablissement;adresseEtablissement;codePostalEtablissement;libelleCommuneEtablissement;trancheEffectifsEtablissement;dateCreationEtablissement',
            '123456789;12345678900001;ACME SARL;56.10A;2 rue de Nantes;44000,Nantes;03;20210101',
        ]) . PHP_EOL;

        file_put_contents($csvPath, gzencode($content));

        $importer = new SireneImporter();
        $importer->import($csvPath, $dbPath, 2);

        $pdo = new \PDO('sqlite:' . $dbPath);
        $rows = $pdo->query('SELECT siren, name FROM company ORDER BY siren')->fetchAll(\PDO::FETCH_ASSOC);

        self::assertCount(1, $rows);
        self::assertSame('123456789', $rows[0]['siren']);
        self::assertSame('ACME SARL', $rows[0]['name']);
    }
}

#!/usr/bin/env python3

import argparse
import csv
import os
import sqlite3
from pathlib import Path


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Rebuild the local SQLite company database from the INSEE CSV source."
    )
    parser.add_argument(
        "--csv",
        default=str(Path(__file__).resolve().parent.parent / "var/data/StockEtablissement_utf8.csv"),
        help="Path to the source CSV file.",
    )
    parser.add_argument(
        "--db",
        default=str(Path(__file__).resolve().parent.parent / "var/data/data.db"),
        help="Path to the SQLite database file to create/update.",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=0,
        help="Optional maximum number of rows to import for testing.",
    )
    parser.add_argument(
        "--batch-size",
        type=int,
        default=10000,
        help="Number of rows to insert per batch.",
    )
    return parser.parse_args()


def build_company_name(row: dict[str, str]) -> str:
    for key in (
        "enseigne1Etablissement",
        "enseigne2Etablissement",
        "enseigne3Etablissement",
        "denominationUsuelleEtablissement",
    ):
        value = row.get(key, "")
        if value:
            return value.strip()
    return ""


def build_company_address(row: dict[str, str]) -> str:
    parts = []
    complement = row.get("complementAdresseEtablissement", "").strip()
    if complement:
        parts.append(complement)

    number = row.get("numeroVoieEtablissement", "").strip()
    repetition = row.get("indiceRepetitionEtablissement", "").strip()
    if number:
        parts.append(number + (" " + repetition if repetition else ""))

    voie_type = row.get("typeVoieEtablissement", "").strip()
    if voie_type:
        parts.append(voie_type)

    voie_nom = row.get("libelleVoieEtablissement", "").strip()
    if voie_nom:
        parts.append(voie_nom)

    if not parts:
        return ""

    return " ".join(parts)


def create_schema(cursor: sqlite3.Cursor) -> None:
    cursor.execute(
        """
        CREATE TABLE company (
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
        )
        """
    )
    cursor.execute("CREATE INDEX idx_company_siren ON company(siren)")
    cursor.execute("CREATE INDEX idx_company_city ON company(city)")


def row_to_company(row: dict[str, str]) -> tuple[str, str, str, str, str, str, str, str, str, str, str] | None:
    siren = row.get("siren", "").strip()
    siret = row.get("siret", "").strip()
    if len(siren) != 9 or len(siret) != 14 or not siren.isdigit() or not siret.isdigit():
        return None

    name = build_company_name(row)
    if not name:
        name = ""

    adresse = build_company_address(row)
    code_postal = row.get("codePostalEtablissement", "").strip()
    city = row.get("libelleCommuneEtablissement", "").strip()
    effectif = row.get("trancheEffectifsEtablissement", "").strip()
    created_at = row.get("dateCreationEtablissement", "").strip()
    active_naf = row.get("activitePrincipaleNAF25Etablissement", "").strip()

    return (
        siren,
        siret,
        name,
        active_naf,
        adresse,
        code_postal,
        city,
        effectif,
        created_at,
        "",
        "",
    )


def import_csv(csv_path: Path, db_path: Path, limit: int, batch_size: int) -> int:
    tmp_path = db_path.with_name(db_path.name + ".tmp")
    if tmp_path.exists():
        tmp_path.unlink()

    db_directory = db_path.parent
    db_directory.mkdir(parents=True, exist_ok=True)
    if not os.access(db_directory, os.W_OK):
        raise PermissionError(
            f"Cannot write to database directory: {db_directory}. "
            "Ensure the current user can create files in this path or run the script with elevated permissions."
        )

    with sqlite3.connect(tmp_path) as conn:
        cursor = conn.cursor()
        cursor.execute("PRAGMA journal_mode=WAL")
        cursor.execute("PRAGMA synchronous=OFF")
        cursor.execute("PRAGMA temp_store=MEMORY")
        cursor.execute("PRAGMA cache_size=100000")

        create_schema(cursor)

        insert_query = (
            "INSERT INTO company (siren, siret, name, active_naf, adresse, code_postal, city, effectif, created_at, phone, email) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )

        row_count = 0
        batch: list[tuple[str, ...]] = []
        with csv_path.open(newline="", encoding="utf-8") as csv_file:
            reader = csv.DictReader(csv_file)
            for raw_row in reader:
                row_count += 1
                if limit and row_count > limit:
                    break

                company = row_to_company(raw_row)
                if company is None:
                    continue

                batch.append(company)
                if len(batch) >= batch_size:
                    cursor.executemany(insert_query, batch)
                    conn.commit()
                    batch.clear()
                    print(f"Imported {row_count} lines...", flush=True)

        if batch:
            cursor.executemany(insert_query, batch)
            conn.commit()
            print(f"Imported {row_count} lines...", flush=True)

    os.replace(tmp_path, db_path)
    return row_count


def main() -> int:
    args = parse_args()
    csv_path = Path(args.csv)
    db_path = Path(args.db)

    if not csv_path.exists():
        print(f"CSV source file not found: {csv_path}", file=sys.stderr)
        return 1

    print(f"Building SQLite database from {csv_path} into {db_path}")
    row_count = import_csv(csv_path, db_path, args.limit, args.batch_size)
    print(f"Import complete. Processed {row_count} CSV lines.")
    print(f"Database is available at: {db_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

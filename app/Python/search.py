#!/usr/bin/env python3

import argparse
import json
import re
import sys
from urllib.parse import urlparse
from ddgs import DDGS

parser = argparse.ArgumentParser()
parser.add_argument("--siret", required=True)
parser.add_argument("--siren", required=True)
parser.add_argument("--naf", required=True)
parser.add_argument("--name", required=False, default="")

args = parser.parse_args()

SIRET = args.siret
SIREN = args.siren
CODE_NAF = args.naf
COMPANY_NAME = args.name.strip()

EXCLUDED = {
    "gouv.fr",
    "data.gouv.fr",
    "societe.com",
    "pappers.fr",
    "verif.com",
    "infogreffe.fr",
    "annuaire-entreprises.fr",
    "manageo.fr",
    "kompass.com",
    "118000.fr",
    "linkedin.com",
    "facebook.com",
    "instagram.com",
    "youtube.com",
    "wikipedia.org",
    "indeed.com",
    "glassdoor.com",
    "datalegal.fr",
    "wildberries.ru",
    "amazon.fr",
    "amazon.com",
    "ebay.com",
    "aliexpress.com",
    "rakuten.com",
    "cdiscount.com",
    "pagesjaunes.fr",
    "infonet.fr",
    "lalettre.fr",
}

STOPWORDS = {
    "societe",
    "société",
    "entreprise",
    "groupe",
    "holding",
    "sas",
    "sasu",
    "sarl",
    "sa",
    "eurl",
    "sci",
    "snc",
    "se",
    "france",
    "international",
    "industrie",
    "industries",
    "company",
    "compagnie",
    "etablissement",
    "établissement",
    "etablissements",
    "établissements",
}

CORPORATE_SUFFIXES = {
    "sas",
    "sasu",
    "sarl",
    "sa",
    "eurl",
    "sci",
    "snc",
    "se",
    "groupe",
    "group",
    "groupement",
    "compagnie",
    "company",
    "solutions",
    "solution",
    "services",
    "service",
    "defense",
    "defence",
    "technologie",
    "technologies",
    "industrie",
    "industries",
    "international",
    "export",
    "developpement",
    "development",
    "etablissement",
    "etablissements",
    "établissement",
    "établissements",
}

GENERIC_COMPANY_NAME_KEYWORDS = {
    "etablissement",
    "etablissements",
    "site",
    "bureau",
    "agence",
    "division",
    "atelier",
    "filiale",
    "usine",
    "service",
    "services",
    "direction",
    "branch",
}


def normalize_url(url):
    if not url:
        return None
    if not url.startswith(("http://", "https://")):
        url = "https://" + url
    return url

def clean(text):
    if not text:
        return ""
    return re.sub(
        r"[^a-z0-9 ]",
        " ",
        text.lower()
    ).strip()

def valid_domain(url):
    url = normalize_url(url)
    if not url:
        return False
    domain = urlparse(url).netloc.lower()
    domain = domain.replace("www.", "")
    for item in EXCLUDED:
        if domain == item or domain.endswith("." + item):
            return False
    return True

def company_words(company):
    words = []
    for word in clean(company).split():
        if len(word) >= 3 and word not in STOPWORDS:
            words.append(word)
    return words


def is_generic_company_name(name):
    if not name:
        return True

    cleaned = clean(name)
    if len(cleaned) < 3:
        return True

    for keyword in GENERIC_COMPANY_NAME_KEYWORDS:
        if keyword in cleaned:
            return True

    tokens = [token for token in cleaned.split() if token not in STOPWORDS]
    return len(tokens) == 0


def normalize_company_name(name):
    if not name:
        return None

    name = name.strip()
    name = re.sub(r"\s+", " ", name)

    parts = [part.strip() for part in re.split(r"[\-/]", name) if part.strip()]
    if len(parts) > 1:
        name = parts[0]

    words = [word for word in name.split() if clean(word) not in CORPORATE_SUFFIXES]
    normalized = " ".join(words).strip()
    if not normalized:
        return None

    return normalized


def is_valid_company_name(name):
    if not name:
        return False

    cleaned = clean(name)
    if len(cleaned) < 3:
        return False

    bad_phrases = [
        "annuaire",
        "fiche",
        "registre",
        "site officiel",
        "contact",
        "avis",
        "pages jaunes",
    ]

    for phrase in bad_phrases:
        if phrase in cleaned:
            return False

    return True


def domain_tokens(hostname):
    return [token for token in re.split(r"[.\-]", hostname) if token]


def text_tokens(text):
    return [token for token in clean(text).split() if token]


def domain_similarity_bonus(hostname, words):
    if not hostname:
        return 0

    score = 0
    domain_parts = hostname.split('.')
    sld = domain_parts[-2] if len(domain_parts) >= 2 else hostname
    sld_tokens = [token for token in sld.split('-') if token]
    tokens = domain_tokens(hostname)

    for word in words:
        if word == sld:
            score += 6
        elif word in sld_tokens:
            score += 4
        elif word in tokens:
            score += 2

    if '-' in sld:
        score -= 1

    return score


def find_email_matches(text):
    if not text:
        return []
    pattern = re.compile(r"[a-zA-Z0-9.+_%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}", re.I)
    return [(match.group(), match.span()) for match in pattern.finditer(text)]


def find_phone_matches(text):
    if not text:
        return []
    pattern = re.compile(r"(?:\+?\d[\d\s\.\-\(\)]{6,}\d)")
    phones = []
    for match in pattern.finditer(text):
        normalized = re.sub(r"[^0-9+]", "", match.group())
        normalized_digits = normalized.lstrip("+")
        if len(normalized_digits) >= 9:
            phones.append((normalized, match.span()))
    return phones


def is_valid_phone_number(phone):
    if not phone:
        return False

    normalized = phone.lstrip("+")
    if normalized == SIREN or normalized == SIRET:
        return False
    if SIREN in normalized or SIRET in normalized:
        return False

    return True


def contains_company_text(text, company):
    if not text or not company:
        return False

    cleaned = clean(text)
    company_clean = clean(company)
    if company_clean in cleaned:
        return True

    for word in company_words(company):
        if word in cleaned:
            return True

    return False


def contains_company_near_match(text, company, span, window=60):
    if not text or not company:
        return False

    start, end = span
    snippet = text[max(0, start - window):min(len(text), end + window)]
    return contains_company_text(snippet, company)


def contains_contact_keyword_near_match(text, span, window=60):
    if not text:
        return False

    contact_keywords = ["contact", "tél", "tel", "email", "e-mail", "phone", "portable", "fax"]
    start, end = span
    snippet = text[max(0, start - window):min(len(text), end + window)].lower()
    return any(keyword in snippet for keyword in contact_keywords)


def search_public_contacts(company):
    queries = [
        f'"{company}" contact',
        f'"{company}" téléphone',
        f'"{company}" email',
        f'"{company}" e-mail',
        f'"{company}" contact@',
        f'"{company}" "{SIREN}" contact',
        f'"{company}" "{SIRET}" contact',
        f'"{SIREN}" contact',
        f'"{SIRET}" contact',
    ]
    contacts = []
    with DDGS() as ddgs:
        for query in queries:
            try:
                results = list(
                    ddgs.text(
                        query,
                        max_results=10
                    )
                )
            except Exception:
                continue

            for result in results:
                text = " ".join([
                    result.get("title", ""),
                    result.get("body", ""),
                    result.get("href", "")
                ])
                if SIREN not in text and SIRET not in text:
                    continue

                for email, span in find_email_matches(text):
                    if not contains_company_near_match(text, company, span):
                        continue
                    if not contains_contact_keyword_near_match(text, span):
                        continue
                    contacts.append({
                        "type": "email",
                        "value": email,
                        "url": f"mailto:{email}",
                        "source": result.get("title", "") or result.get("href", "")
                    })

                for phone, span in find_phone_matches(text):
                    if not is_valid_phone_number(phone):
                        continue
                    if not contains_company_near_match(text, company, span):
                        continue
                    if not contains_contact_keyword_near_match(text, span):
                        continue
                    contacts.append({
                        "type": "phone",
                        "value": phone,
                        "url": f"tel:{phone}",
                        "source": result.get("title", "") or result.get("href", "")
                    })

    unique = []
    seen = set()
    for item in contacts:
        key = (item["type"], item["value"])
        if key not in seen:
            seen.add(key)
            unique.append(item)
    return unique[:5]


def extract_company_name(title, body):
    text = f"{title}\n{body}"
    candidates = []

    def add_candidate(name, weight):
        normalized = normalize_company_name(name)
        if not normalized or not is_valid_company_name(normalized):
            return
        candidates.append((normalized, weight))

    patterns = [
        (r"(?:dénomination sociale|denomination sociale|raison sociale|raison\s+sociale|nom commercial|nom de l(?:'|e) entreprise|nom de la société|nom de l'entreprise)\s*[:\-]?\s*([A-Z][A-Za-z0-9 &'’\-]{3,})", 10),
        (r"(?:société|societe|entreprise)\s+([A-Z][A-Za-z0-9 .&'’\-]{3,})", 6),
        (r"([A-Z][A-Z0-9&'’ .-]{3,})\s+(?:SAS|SARL|SA|SASU|EURL|SCA|SNC)", 5),
    ]

    for pattern, weight in patterns:
        for match in re.finditer(pattern, text, flags=re.I):
            name = match.group(1).strip()
            add_candidate(name, weight)

    if title:
        for separator in [" - ", " | ", " — "]:
            if separator in title:
                title_candidate = title.split(separator)[0].strip()
                add_candidate(title_candidate, 3)
                break

    official_keywords = [
        "raison sociale",
        "dénomination sociale",
        "denomination sociale",
        "nom commercial",
        "nom de la société",
        "nom de l'entreprise",
    ]

    for line in text.splitlines():
        lowered = line.lower()
        for keyword in official_keywords:
            if keyword in lowered:
                line = re.sub(r".*?(?:raison sociale|dénomination sociale|denomination sociale|nom commercial|nom de la société|nom de l'entreprise)[:\-]?\s*", "", line, flags=re.I)
                add_candidate(line, 10)
                break

    if candidates:
        totals = {}
        for name, weight in candidates:
            totals[name] = totals.get(name, 0) + weight
        return max(totals, key=totals.get)

    return None

def search_company_name():
    queries = [
        f'"{SIRET}" "raison sociale"',
        f'"{SIREN}" "raison sociale"',
        f'"{SIRET}" "dénomination sociale"',
        f'"{SIREN}" "dénomination sociale"',
        f'"{SIREN}" entreprise',
        f'"{SIRET}" entreprise',
        f'"{SIREN}" "{CODE_NAF}" société',
    ]

    found = []

    with DDGS() as ddgs:

        for query in queries:

            try:
                results = list(
                    ddgs.text(
                        query,
                        max_results=20
                    )
                )

            except Exception:
                continue


            for result in results:

                title = result.get(
                    "title",
                    ""
                )
                body = result.get(
                    "body",
                    ""
                )
                content = title + " " + body
                if (
                    SIRET not in content
                    and SIREN not in content
                ):
                    continue
                name = extract_company_name(
                    title,
                    body
                )


                if name:
                    found.append(name)
                    
    if found:

        # prend le nom le plus fréquent
        company = max(
            set(found),
            key=found.count
        )

        print(
            f"[INFO] Nom entreprise trouvé : {company}",
            file=sys.stderr,
            flush=True
        )

        return company

    return None

def calculate_score(url, company, title, body):
    score = 0
    parsed = urlparse(url)
    hostname = (
        parsed.netloc
        .lower()
        .replace("www.", "")
    )
    domain = clean(hostname)
    domain_parts = hostname.split(".")
    text = clean(
        title + " " + body
    )
    words = company_words(company)

    domain_tokens_list = domain_tokens(hostname)
    text_tokens_list = text_tokens(title + " " + body)

    for word in words:
        if word in domain_tokens_list:
            score += 10
        elif word in text_tokens_list:
            score += 4

    score += domain_similarity_bonus(hostname, words)

    if len(domain_parts) == 2:
        score += 5

    if len(domain_parts) > 2:
        score -= 4

    subdomains = domain_parts[:-2]
    if subdomains and subdomains != ["www"]:
        score -= 3

    if parsed.path in ("", "/"):
        score += 2

    if '-' in hostname:
        score -= 1

    bad_words = [
        "annuaire",
        "legal",
        "fiche",
        "societe",
        "registre",
        "directory",
        "entreprise",
        "datalegal",
    ]

    for word in bad_words:
        if word in domain or word in text:
            score -= 5

    return score

def base_domain(hostname):
    parts = hostname.split(".")
    if len(parts) <= 2:
        return hostname
    return ".".join(parts[-2:])


def search_official_candidates(company):
    words = company_words(company)
    if not words:
        return []
    MIN_OFFICIAL_SCORE = 10
    queries = [
        f'"{company}" site officiel',
        f'"{company}" site web officiel',
        f'"{company}" site internet',
        f'"{company}" official website',
        f'"{company}" website',
        f'"{company}" contact',
    ]
    candidates = []
    with DDGS() as ddgs:
        for query in queries:
            try:
                results = list(
                    ddgs.text(
                        query,
                        max_results=5
                    )
                )
            except Exception:
                continue

            for result in results:
                url = normalize_url(
                    result.get("href")
                )

                if not valid_domain(url):
                    continue

                score = calculate_score(
                    url,
                    company,
                    result.get("title", ""),
                    result.get("body", "")
                )

                if score < MIN_OFFICIAL_SCORE:
                    continue

                parsed = urlparse(url)

                domain = (
                    parsed.netloc
                    .lower()
                    .replace("www.", "")
                )

                domain_tokens_list = domain_tokens(domain)
                if not any(word in domain_tokens_list for word in words):
                    continue

                candidates.append({
                    "title": result.get(
                        "title",
                        ""
                    ),
                    "url": f"{parsed.scheme}://{domain}",
                    "score": score
                })

    if not candidates:
        return []

    candidates.sort(
        key=lambda x: x["score"],
        reverse=True
    )

    roots = {}
    for candidate in candidates:
        hostname = urlparse(candidate["url"]).netloc
        base = base_domain(hostname)
        if hostname == base:
            roots[base] = candidate

    filtered = []
    for candidate in candidates:
        hostname = urlparse(candidate["url"]).netloc
        base = base_domain(hostname)
        if base in roots and hostname != base:
            continue
        filtered.append(candidate)

    return filtered[:1]

def main():

    company = COMPANY_NAME.strip()

    if company:
        normalized = normalize_company_name(company)
        if normalized and normalized != company and is_valid_company_name(normalized):
            print(
                f"[INFO] Nom fourni normalisé : {normalized}",
                file=sys.stderr,
                flush=True
            )
            company = normalized

    if company and is_generic_company_name(company):
        print(
            f"[INFO] Nom fourni jugé générique ou incomplet : {company}",
            file=sys.stderr,
            flush=True
        )
        company = ""

    if not company:

        print(
            "[INFO] Recherche du nom entreprise...",
            file=sys.stderr,
            flush=True
        )

        company = search_company_name()

    if not company:

        print(json.dumps(
            {
                "success": False,
                "message": "Entreprise introuvable",
                "siret": SIRET,
                "siren": SIREN,
                "code_naf": CODE_NAF
            },
            ensure_ascii=False,
            indent=4
        ))

        return

    company = company.strip()

    print(
        f"[INFO] Entreprise utilisée : {company}",
        file=sys.stderr,
        flush=True
    )

    print(
        "[INFO] Recherche du site officiel...",
        file=sys.stderr,
        flush=True
    )

    candidates = search_official_candidates(
        company
    )

    contacts = []
    if not candidates:
        contacts = search_public_contacts(company)

    print(json.dumps(
        {
            "success": True,
            "company": {
                "siret": SIRET,
                "siren": SIREN,
                "code_naf": CODE_NAF,
                "name": company,
                "keywords": company_words(company)
            },
            "candidates": candidates,
            "contacts": contacts
        },
        ensure_ascii=False,
        indent=4
    ))

if __name__ == "__main__":
    main()
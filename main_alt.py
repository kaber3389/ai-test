#!/usr/bin/env python3
"""
Скрипт для генерации рекламных объявлений Системы Кадры.
Получает мотивы из API, генерирует рекламные тексты через neuroapi,
сохраняет в SQLite и экспортирует в CSV/XLSX.
"""

import json
import re
import sqlite3
import time
from datetime import datetime
from pathlib import Path

import requests
from openai import OpenAI

# ============================================================
# НАСТРОЙКИ 
# ============================================================

NEUROAPI_KEY = "sk-NireE2orzRrRR4QdlSZGwKXD3pJQUJjy4zbKZU5PjAeYDGc0"  # ключ NeuroAPI

# Параметры документа
DOC_CONFIG = {
    "name": "kss",
    "mod_id": 16,
}

# Сколько doc_id обработать из списка (0 = все)
DOC_ID_COUNT = 20

# Сколько мотивов на каждый doc_id (1, 2 или 3)
MOTIVES_PER_DOC = 3

# Файл со списком doc_id (каждый с новой строки)
DOC_ID_FILE = "doc_ids.txt"

# URl
SS_GPT_URL = "https://py.2action.link/api/ss_gpt/"
NEUROAPI_BASE_URL = "https://neuroapi.host/v1"
NEUROAPI_MODEL = "gpt-5.2"

# db sqlite
DB_PATH = "motives.db"

# Пауза между запросами (секунды)
REQUEST_DELAY = 1.0


# ============================================================
# Чтение doc_id из файла


def load_doc_ids(filepath: str, limit: int = 0) -> list[int]:
    """Читает файл, возвращает список уникальных doc_id."""
    path = Path(filepath)
    if not path.exists():
        raise FileNotFoundError(f"Файл не найден: {filepath}")

    raw_lines = path.read_text(encoding="utf-8").strip().splitlines()
    seen = set()
    unique_ids = []
    for line in raw_lines:
        line = line.strip()
        if not line:
            continue
        try:
            doc_id = int(line)
        except ValueError:
            print(f"  Пропускаю некорректную строку: '{line}'")
            continue
        if doc_id not in seen:
            seen.add(doc_id)
            unique_ids.append(doc_id)

    print(f"Прочитано строк: {len(raw_lines)}, уникальных doc_id: {len(unique_ids)}")

    if limit > 0:
        unique_ids = unique_ids[:limit]
        print(f"Ограничение: берём первые {limit} doc_id")

    return unique_ids


# ============================================================
# Промпт для нейросети


def build_prompt(motive_id: int, motive_text: str) -> str:
    return f"""Ты — маркетолог справочной системы и твоя задача сделать рекламную кампанию, чтобы пригласить потенциальных Клиентов — специалистов по кадрам — на лендинг, который рекламирует материалы по теме поисковых запросов клиента.

Что нужно знать о Системе Кадры. Это огромный справочник ответов на вопросы (рекомендаций) кадровиков по их ежедневным рутинным задачам, кадровым процедурам и документам.
id мотива {motive_id}
Вот описание ценного содержания рекомендации, которое поможет кадровику в его работе:
{{{motive_text}}}
Составь рекламное объявление по этим правилам:
Заголовок до 56 символов с пробелами. Состоит из двух частей: описание того, что ищет клиент и сообщением, что это можно найти или сделать в Системе Кадры. По возможности используй конструкцию «есть в Системе Кадры», но нужно соблюдать правила русского языка. Пример: Все для оформления графика отпусков есть в Системе Кадры, Что делать при смене собственника — в Системе Кадры, Образцы локальных актов есть в Системе Кадры. Как не надо — Определите категорию риска по персданным есть в Системе Кадры. Как правильно Определите категорию риска по персданным в Системе Кадры
Дополнительный заголовок до 30 символов. Всегда одинаковый: Оформите заявку на подключение
Текст объявления:  до 81 символа. Нужна продающая фраза, что именно ценное получит кадровик для своей работы.
В тексте должно быть не более 15 знаков препинания.
Дополнительно составь Текст для лендинга, на который перейдут люди из объявления. Заголовок и 2-3 предложения про рекомендацию. Не нужно писать рекламно, оставь суть ценности, расскажи о том, как кадровики решат свою проблему с помощью рекомендации. 
Ответ возвращай строго в формате JSON без markdown-обёртки:
{{"motive_id":"{motive_id}","ad_title":"Заголовок до 56 символов","add_ad_title":"Оформите заявку на подключение","ad_text":"Текст до 81 символа","landing_text":"Текст для лэндинга"}}"""


# ============================================================
# Работа с SQLite


def init_db(db_path: str = DB_PATH) -> sqlite3.Connection:
    conn = sqlite3.connect(db_path)
    conn.execute("""
        CREATE TABLE IF NOT EXISTS motives (
            id INTEGER,
            doc_id INTEGER,
            motive_num INTEGER,
            date TEXT,
            product TEXT,
            motive TEXT,
            motive_text TEXT,
            meta_title TEXT,
            meta_head TEXT,
            meta_text TEXT,
            meta_button TEXT,
            meta_intro TEXT,
            value_proposition TEXT,
            benefits TEXT,
            conclusion TEXT,
            ad_title TEXT,
            ad_title_len INTEGER,
            add_ad_title TEXT,
            add_ad_title_len INTEGER,
            ad_text TEXT,
            ad_text_len INTEGER,
            landing_text TEXT,
            created_at TEXT,
            PRIMARY KEY (doc_id, motive_num)
        )
    """)
    conn.commit()
    return conn


def insert_record(conn: sqlite3.Connection, record: dict):
    content = record.get("content", {})
    meta = content.get("meta", {})

    values = (
        record.get("id"),
        record.get("doc_id"),
        record.get("motive_num"),
        record.get("date"),
        record.get("product"),
        record.get("motive"),
        record.get("motive_text"),
        meta.get("title", ""),
        meta.get("head", ""),
        meta.get("text", ""),
        meta.get("button", ""),
        meta.get("intro", ""),
        json.dumps(content.get("value_proposition", []), ensure_ascii=False),
        json.dumps(content.get("benefits", []), ensure_ascii=False),
        content.get("conclusion", ""),
        record.get("ad_title", ""),
        len(record.get("ad_title", "")),
        record.get("add_ad_title", ""),
        len(record.get("add_ad_title", "")),
        record.get("ad_text", ""),
        len(record.get("ad_text", "")),
        record.get("landing_text", ""),
        datetime.now().isoformat(),
    )

    conn.execute("""
        INSERT OR REPLACE INTO motives (
            id, doc_id, motive_num, date, product, motive, motive_text,
            meta_title, meta_head, meta_text, meta_button, meta_intro,
            value_proposition, benefits, conclusion,
            ad_title, ad_title_len,
            add_ad_title, add_ad_title_len,
            ad_text, ad_text_len, landing_text,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, values)
    conn.commit()


# ============================================================
# Экспорт из SQLite


def export_csv(db_path: str = DB_PATH, output: str = "motives_export.csv"):
    import csv
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    rows = conn.execute("SELECT * FROM motives ORDER BY doc_id, motive_num").fetchall()
    if not rows:
        print("Нет данных для экспорта.")
        conn.close()
        return
    keys = rows[0].keys()
    with open(output, "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=keys, delimiter=";")
        writer.writeheader()
        for row in rows:
            writer.writerow(dict(row))
    conn.close()
    print(f"CSV сохранён: {output} ({len(rows)} строк)")


def export_xlsx(db_path: str = DB_PATH, output: str = "motives_export.xlsx"):
    try:
        import openpyxl
    except ImportError:
        print("openpyxl не установлен. Установите: pip install openpyxl")
        return

    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    rows = conn.execute("SELECT * FROM motives ORDER BY doc_id, motive_num").fetchall()
    if not rows:
        print("Нет данных для экспорта.")
        conn.close()
        return

    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "Motives"

    keys = rows[0].keys()
    ws.append(list(keys))
    for row in rows:
        ws.append(list(dict(row).values()))

    wb.save(output)
    conn.close()
    print(f"XLSX сохранён: {output} ({len(rows)} строк)")


# ============================================================
# API-запросы


def fetch_motive(name: str, doc_id: int, mod_id: int, motive_num: str) -> dict | None:
    payload = {
        "name": name,
        "doc_id": doc_id,
        "mod_id": mod_id,
        "motive_num": motive_num,
    }
    try:
        resp = requests.post(
            SS_GPT_URL,
            json=payload,
            headers={"Content-Type": "application/json"},
            timeout=30,
        )
        resp.raise_for_status()
        return resp.json()
    except Exception as e:
        print(f"  Ошибка запроса doc_id={doc_id}, motive_num={motive_num}: {e}")
        return None


def generate_ad(motive_id: int, motive_text: str) -> dict:
    """Обращается к нейросети и возвращает dict с ad_title, add_ad_title, ad_text."""
    client = OpenAI(base_url=NEUROAPI_BASE_URL, api_key=NEUROAPI_KEY)
    prompt = build_prompt(motive_id, motive_text)

    try:
        completion = client.chat.completions.create(
            model=NEUROAPI_MODEL,
            messages=[{"role": "user", "content": prompt}],
        )
        raw = completion.choices[0].message.content.strip()
        # Убираем возможную markdown-обёртку ```json ... ```
        cleaned = re.sub(r"^```(?:json)?\s*", "", raw)
        cleaned = re.sub(r"\s*```$", "", cleaned)
        ad = json.loads(cleaned)
        return {
            "ad_title": ad.get("ad_title", ""),
            "add_ad_title": ad.get("add_ad_title", "Оформите заявку на подключение"),
            "ad_text": ad.get("ad_text", ""),
            "landing_text": ad.get("landing_text", "")
        }
    except Exception as e:
        print(f"  Ошибка генерации объявления: {e}")
        return {"ad_title": "", "add_ad_title": "", "ad_text": "", "landing_text":""}


# ============================================================
# Основной процесс


def run(config: dict, doc_ids: list[int], motives_per_doc: int = MOTIVES_PER_DOC):
    conn = init_db()

    total_requests = len(doc_ids) * motives_per_doc
    print(f"Запуск: name={config['name']}, mod_id={config['mod_id']}")
    print(f"doc_id в списке: {len(doc_ids)}, мотивов на doc_id: {motives_per_doc}")
    print(f"Всего запросов: {total_requests}")
    print("=" * 60)

    request_num = 0

    for doc_idx, doc_id in enumerate(doc_ids, start=1):
        print(f"\n{'─' * 40}")
        print(f"doc_id {doc_id} [{doc_idx}/{len(doc_ids)}]")
        print(f"{'─' * 40}")

        for motive_num in range(1, motives_per_doc + 1):
            request_num += 1
            motive_num_str = str(motive_num)

            print(f"\n  [{request_num}/{total_requests}] doc_id={doc_id}, motive_num={motive_num_str}")

            # 1. Получаем мотив из API
            raw_response = fetch_motive(
                config["name"], doc_id, config["mod_id"], motive_num_str
            )
            if raw_response is None:
                continue

            # Парсим ответ
            data = raw_response.get("data", raw_response)
            if isinstance(data, list):
                data = data[0] if data else {}

            motive_id = data.get("id", 0)
            motive_text = data.get("motive_text", data.get("motive", ""))

            print(f"    id={motive_id}, motive_text длина={len(motive_text)}")

            # 2. Генерируем рекламное объявление
            print("    Генерация объявления...")
            ad = generate_ad(motive_id, motive_text)
            print(f"    ad_title ({len(ad['ad_title'])} симв.): {ad['ad_title']}")
            print(f"    ad_text  ({len(ad['ad_text'])} симв.): {ad['ad_text']}")
            print(f"    ad_text  ({len(ad['landing_text'])} симв.): {ad['landing_text']}")

            # 3. Собираем итоговую запись
            record = {
                "id": motive_id,
                "doc_id": doc_id,
                "motive_num": motive_num,
                "date": datetime.now().strftime("%Y-%m-%d"),
                "product": data.get("product", config["name"]),
                "motive": data.get("motive", ""),
                "motive_text": motive_text,
                "content": data.get("content", {}),
                "ad_title": ad["ad_title"],
                "add_ad_title": ad["add_ad_title"],
                "ad_text": ad["ad_text"],
                "landing_text": ad["landing_text"],
            }

            # 4. Сохраняем в SQLite
            insert_record(conn, record)
            print(f"    Сохранено в БД.")

            time.sleep(REQUEST_DELAY)

    conn.close()
    print("\n" + "=" * 60)
    print(f"Готово. Обработано {request_num} запросов.")


# ============================================================
# CLI


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Генератор рекламных объявлений Системы Кадры")
    sub = parser.add_subparsers(dest="command")

    # Команда run
    run_p = sub.add_parser("run", help="Запустить сбор и генерацию")
    run_p.add_argument("-n", "--doc-id-count", type=int, default=DOC_ID_COUNT,
                       help="Сколько doc_id обработать (0 = все)")
    run_p.add_argument("-m", "--motives-per-doc", type=int, default=MOTIVES_PER_DOC,
                       choices=[1, 2, 3], help="Мотивов на каждый doc_id (1-3)")
    run_p.add_argument("-f", "--file", type=str, default=DOC_ID_FILE,
                       help="Путь к файлу со списком doc_id")
    run_p.add_argument("--name", type=str, default=DOC_CONFIG["name"])
    run_p.add_argument("--mod-id", type=int, default=DOC_CONFIG["mod_id"])
    run_p.add_argument("--api-key", type=str, default=NEUROAPI_KEY, help="NeuroAPI ключ")

    # Команда export
    exp_p = sub.add_parser("export", help="Экспорт из БД")
    exp_p.add_argument("--format", choices=["csv", "xlsx", "both"], default="both")
    exp_p.add_argument("--db", default=DB_PATH)

    args = parser.parse_args()

    if args.command == "run":
        NEUROAPI_KEY = args.api_key
        cfg = {
            "name": args.name,
            "mod_id": args.mod_id,
        }
        doc_ids = load_doc_ids(args.file, limit=args.doc_id_count)
        if not doc_ids:
            print("Список doc_id пуст. Нечего обрабатывать.")
        else:
            run(config=cfg, doc_ids=doc_ids, motives_per_doc=args.motives_per_doc)

    elif args.command == "export":
        if args.format in ("csv", "both"):
            export_csv(args.db)
        if args.format in ("xlsx", "both"):
            export_xlsx(args.db)

    else:
        parser.print_help()

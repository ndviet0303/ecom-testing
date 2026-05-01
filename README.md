# ECM Project (Vue + Laravel + Selenium + Postman)

## RAG Test Generator (API Testcase)

Module `ai/src/rag_testgen` duoc dung de scan API Laravel va tao testcase JSON cho API.

### Quick Start (Backend + AI)

Mo 3 terminal:

Terminal 1 - chay backend Laravel:

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2 - chay AI generate testcase:

```bash
python3 -m ai.src.rag_testgen.cli generate \
  --src backend \
  --out ai/generated-testcases.json \
  --dry-run
```

Neu muon goi model that (khong dry-run), bo `--dry-run` va them `--provider` / `--model`.

Terminal 3 - chay test backend:

```bash
cd backend
php artisan test
```

Neu can chay nhanh theo nhom:

```bash
cd backend
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

### 1) Chuc nang chinh

- Scan route API tu `backend/routes/api.php`.
- Retrieve context tu backend source + frontend source (neu co).
- Retrieve context tu DB:
  - doc `.env` (hoac `--env-file`),
  - parse migration de lay schema/constraints,
  - parse seeder de lay data setup hints,
  - build dependency order theo foreign key.
- Parse validation tu controller (`$request->validate([...])`).
- Tao `payload blueprint` (valid_body, invalid_body_cases, preconditions) de model sinh test on dinh hon.

### 2) Cau truc command

Chay command tu thu muc goc project:

```bash
python3 -m ai.src.rag_testgen.cli <command> [options]
```

### 3) Scan route

```bash
python3 -m ai.src.rag_testgen.cli scan --src backend --pretty
```

Tham so:
- `--src`: duong dan source Laravel (vd: `backend`).
- `--pretty`: in JSON dep de de doc.

### 4) Generate testcase (dry-run)

```bash
python3 -m ai.src.rag_testgen.cli generate \
  --src backend \
  --out ai/generated-testcases.json \
  --dry-run
```

Dung khi muon kiem tra luong retrieve/context ma khong goi LLM.

### 5) Generate testcase voi model that

#### Ollama

```bash
python3 -m ai.src.rag_testgen.cli generate \
  --src backend \
  --out ai/generated-testcases.json \
  --provider ollama \
  --model qwen2.5-coder:7b
```

Co the set:
- `OLLAMA_BASE_URL` (mac dinh: `http://localhost:11434`)

#### OpenAI-compatible

```bash
python3 -m ai.src.rag_testgen.cli generate \
  --src backend \
  --out ai/generated-testcases.json \
  --provider openai \
  --model gpt-4o-mini
```

Can env:
- `OPENAI_API_KEY` (bat buoc)
- `OPENAI_BASE_URL` (optional, mac dinh: `https://api.openai.com/v1`)

### 6) Cac option quan trong khi generate

- `--src`: thu muc backend Laravel.
- `--out`: file JSON output.
- `--provider`: `ollama` | `openai`.
- `--model`: ten model.
- `--frontend-src`: source frontend de retrieve API usage context (optional).
- `--env-file`: chi dinh file env DB (optional).
- `--prompt-file`: dung custom prompt template (optional).
- `--limit N`: chi generate N route dau.
- `--dry-run`: bo qua model call, dung fallback generator.

### 7) Cac context moi trong output

Trong `retrieved_context`, co the gap cac `reason`:

- `database env configuration`
- `database connection metadata`
- `route table mapping`
- `table constraints:<table_name>`
- `schema snippet:<table_name>`
- `request validation blueprint`
- `payload blueprint`
- `data setup dependency plan`
- `seeder data source, table:<table_name>`

### 8) Ghi chu

- Module hien tai parse validation tu inline `$request->validate([...])`.
- Neu DB la `sqlite` va file DB ton tai, tool co inspect duoc runtime tables.
- Voi `pgsql/mysql`, tool dua vao env + migration + seeder de suy luan context.

## Giao dien Web

Project hien tai chi ho tro giao dien web local thong qua `tests/ui/server.py`, khong con phien ban desktop.

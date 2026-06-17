from __future__ import annotations

import json
import os
import platform
import subprocess
import shutil
import time
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib import parse, request
from urllib.error import HTTPError, URLError
from urllib.parse import urlparse, urlunparse


ROOT_DIR = Path(__file__).resolve().parents[2]
UI_DIR = Path(__file__).resolve().parent
AI_DIR = ROOT_DIR / "ai"
DEFAULT_OUTPUT = AI_DIR / "generated-testcases.json"
AI_ENV_PATH = AI_DIR / ".env"
RUNTIME_DIR = UI_DIR / ".runtime"
UPLOADED_COLLECTION = RUNTIME_DIR / "uploaded-collection.json"
API_RESULT_FILE = RUNTIME_DIR / "api-test-results.json"
API_ANALYSIS_FILE = RUNTIME_DIR / "api-result-analysis.json"
PLAYWRIGHT_RESULT_FILE = RUNTIME_DIR / "playwright-results.json"
PYTHON_BIN = os.environ.get("PYTHON_BIN") or shutil.which("python3.14") or shutil.which("python3") or "python3"
DEFAULT_PROVIDER = "groq"
DEFAULT_MODEL_BY_PROVIDER = {
    "ollama": "qwen2.5-coder:7b",
    "openai": "gpt-4o-mini",
    "gemini": "gemini-2.5-flash",
    "groq": "llama-3.1-8b-instant",
}
API_KEY_BY_PROVIDER = {
    "openai": "OPENAI_API_KEY",
    "gemini": "GEMINI_API_KEY",
    "groq": "GROQ_API_KEY",
}
DEFAULT_ANALYSIS_FOCUS = (
    "Viết report tester tổng hợp cho API suite và Playwright suite. "
    "Ưu tiên correlation API/UI, lỗi backend/frontend/test-data/environment, "
    "defect severity, impact, root cause, test quality gaps và action cải thiện cụ thể."
)


def _run_cli(command: list[str]) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["PYTHONPATH"] = "src"
    return subprocess.run(
        command,
        cwd=AI_DIR,
        env=env,
        text=True,
        capture_output=True,
        check=False,
    )


def _run_shell(command: list[str], cwd: Path | None = None, env: dict[str, str] | None = None) -> subprocess.CompletedProcess[str]:
    process_env = os.environ.copy()
    if env:
        process_env.update(env)
    return subprocess.run(
        command,
        cwd=cwd or ROOT_DIR,
        env=process_env,
        text=True,
        capture_output=True,
        check=False,
    )


def _ensure_runtime() -> None:
    RUNTIME_DIR.mkdir(parents=True, exist_ok=True)


def _normalize_target_url(base_url: str, endpoint: str, query: dict[str, object]) -> str:
    base = base_url.strip().rstrip("/") or "http://127.0.0.1:8000/api"
    endpoint = endpoint or "/"
    if endpoint.startswith("http://") or endpoint.startswith("https://"):
        url = endpoint
    else:
        parsed = parse.urlparse(base)
        root = f"{parsed.scheme}://{parsed.netloc}"
        clean_endpoint = endpoint if endpoint.startswith("/") else f"/{endpoint}"
        clean_endpoint = clean_endpoint.replace("//", "/")
        if clean_endpoint.startswith("/api/"):
            url = f"{root}{clean_endpoint}"
        elif clean_endpoint.startswith("/v1/") or clean_endpoint.startswith("/auth/") or clean_endpoint == "/user":
            url = f"{root}/api{clean_endpoint}"
        else:
            base_path = (parsed.path or "/api").rstrip("/")
            url = f"{root}{base_path}{clean_endpoint}"

    parsed_url = parse.urlparse(url)
    normalized_path = "/" + "/".join(part for part in parsed_url.path.split("/") if part)
    url = urlunparse((parsed_url.scheme, parsed_url.netloc, normalized_path, "", parsed_url.query, ""))

    if query:
        query_string = parse.urlencode({key: "" if value is None else value for key, value in query.items()}, doseq=True)
        separator = "&" if "?" in url else "?"
        url = f"{url}{separator}{query_string}"
    return url


def _replace_path_params(endpoint: str, path_params: dict[str, object], defaults: dict[str, object] | None = None) -> str:
    resolved = endpoint
    values = {**(path_params or {}), **(defaults or {})}
    for key, value in values.items():
        resolved = resolved.replace(f"{{{key}}}", parse.quote(str(value)))
    return resolved


def _deep_replace_placeholders(value, replacements: dict[str, object]):
    if isinstance(value, dict):
        return {key: _deep_replace_placeholders(item, replacements) for key, item in value.items()}
    if isinstance(value, list):
        return [_deep_replace_placeholders(item, replacements) for item in value]
    if isinstance(value, str):
        output = value
        for key, replacement in replacements.items():
            output = output.replace(f"<{key}>", str(replacement)).replace(f"{{{key}}}", str(replacement))
        return output
    return value


def _safe_json_loads(text: str) -> dict | list | None:
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        return None


def _truncate_text(text: str, limit: int = 1200) -> str:
    if len(text) <= limit:
        return text
    return text[:limit].rstrip() + "...[truncated]"


def _estimate_tokens(text: str) -> int:
    if not text:
        return 1
    return max(1, len(text) // 4)


def _http_json(url: str, method: str = "GET", headers: dict[str, str] | None = None, body: dict | None = None, timeout: int = 20) -> tuple[int | None, dict | list | None, str]:
    req_headers = {"Accept": "application/json", **(headers or {})}
    data = None
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        req_headers.setdefault("Content-Type", "application/json")
    http_request = request.Request(url, headers=req_headers, data=data, method=method)
    try:
        with request.urlopen(http_request, timeout=timeout) as response:
            text = response.read().decode("utf-8", errors="ignore")
            return response.status, _safe_json_loads(text), text
    except HTTPError as exc:
        text = exc.read().decode("utf-8", errors="ignore")
        return exc.code, _safe_json_loads(text), text
    except URLError:
        return None, None, ""


def _read_env_file(path: Path) -> dict[str, str]:
    if not path.exists():
        return {}
    values: dict[str, str] = {}
    for raw_line in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def _write_env_values(path: Path, updates: dict[str, str]) -> None:
    existing_lines = path.read_text(encoding="utf-8").splitlines() if path.exists() else []
    seen: set[str] = set()
    output_lines: list[str] = []

    for raw_line in existing_lines:
        stripped = raw_line.strip()
        if not stripped or stripped.startswith("#") or "=" not in stripped:
            output_lines.append(raw_line)
            continue
        key, _, _ = stripped.partition("=")
        key = key.strip()
        if key in updates:
            output_lines.append(f"{key}={updates[key]}")
            seen.add(key)
        else:
            output_lines.append(raw_line)

    if output_lines and output_lines[-1].strip():
        output_lines.append("")

    for key, value in updates.items():
        if key not in seen:
            output_lines.append(f"{key}={value}")

    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("\n".join(output_lines).rstrip() + "\n", encoding="utf-8")


def _ai_settings() -> dict:
    env_values = {**_read_env_file(AI_ENV_PATH), **os.environ}
    provider = env_values.get("RAG_TESTGEN_PROVIDER", DEFAULT_PROVIDER).strip() or DEFAULT_PROVIDER
    if provider not in DEFAULT_MODEL_BY_PROVIDER:
        provider = DEFAULT_PROVIDER
    model = env_values.get("RAG_TESTGEN_MODEL") or DEFAULT_MODEL_BY_PROVIDER[provider]
    key_name = API_KEY_BY_PROVIDER.get(provider)
    has_api_key = bool(env_values.get(key_name, "").strip()) if key_name else provider == "ollama"
    return {
        "provider": provider,
        "model": model,
        "env_file": str(AI_ENV_PATH),
        "key_name": key_name,
        "has_api_key": has_api_key,
    }


def _applescript_quote(value: str) -> str:
    return value.replace("\\", "\\\\").replace('"', '\\"')


def _select_path_with_dialog(kind: str, title: str, initial: str = "") -> dict:
    if platform.system() == "Darwin":
        return _select_path_with_osascript(kind, title, initial)
    return _select_path_with_tk(kind, title, initial)


def _select_path_with_osascript(kind: str, title: str, initial: str = "") -> dict:
    title = _applescript_quote(title or "Select path")
    initial_path = Path(initial).expanduser() if initial else ROOT_DIR

    if kind == "save_file":
        default_name = initial_path.name if initial_path.name else "generated-testcases.json"
        parent = initial_path.parent if initial_path.parent != Path(".") else ROOT_DIR
        script = (
            f'POSIX path of (choose file name with prompt "{title}" '
            f'default name "{_applescript_quote(default_name)}" '
            f'default location POSIX file "{_applescript_quote(str(parent.resolve()))}")'
        )
    else:
        target = initial_path if initial_path.is_dir() else initial_path.parent
        script = (
            f'POSIX path of (choose folder with prompt "{title}" '
            f'default location POSIX file "{_applescript_quote(str(target.resolve()))}")'
        )

    process = subprocess.run(["osascript", "-e", script], text=True, capture_output=True, check=False)
    if process.returncode != 0:
        return {"ok": False, "cancelled": True, "message": process.stderr.strip() or "Selection cancelled."}
    return {"ok": True, "path": process.stdout.strip()}


def _select_path_with_tk(kind: str, title: str, initial: str = "") -> dict:
    try:
        import tkinter as tk
        from tkinter import filedialog
    except Exception as exc:
        return {"ok": False, "message": f"Native file dialog is unavailable: {exc}"}

    root = tk.Tk()
    root.withdraw()
    root.attributes("-topmost", True)
    initial_path = Path(initial).expanduser() if initial else ROOT_DIR
    initial_dir = initial_path if initial_path.is_dir() else initial_path.parent

    if kind == "save_file":
        selected = filedialog.asksaveasfilename(
            title=title or "Select output JSON",
            initialdir=str(initial_dir),
            initialfile=initial_path.name or "generated-testcases.json",
            defaultextension=".json",
            filetypes=[("JSON files", "*.json"), ("All files", "*.*")],
        )
    else:
        selected = filedialog.askdirectory(title=title or "Select directory", initialdir=str(initial_dir))

    root.destroy()
    if not selected:
        return {"ok": False, "cancelled": True, "message": "Selection cancelled."}
    return {"ok": True, "path": selected}


class UiHandler(BaseHTTPRequestHandler):
    def do_GET(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path == "/":
            self._serve_file("index.html", "text/html; charset=utf-8")
            return
        if parsed.path == "/app.js":
            self._serve_file("app.js", "application/javascript; charset=utf-8")
            return
        if parsed.path == "/styles.css":
            self._serve_file("styles.css", "text/css; charset=utf-8")
            return
        if parsed.path in ("/slides", "/slides.html"):
            self._serve_file("slides.html", "text/html; charset=utf-8")
            return
        if parsed.path == "/slides.js":
            self._serve_file("slides.js", "application/javascript; charset=utf-8")
            return
        if parsed.path == "/slides.css":
            self._serve_file("slides.css", "text/css; charset=utf-8")
            return
        if parsed.path == "/api/state":
            self._json_response(self._build_state())
            return
        if parsed.path == "/api/output":
            self._json_response(self._load_output())
            return
        if parsed.path == "/api/download_output":
            query = parse.parse_qs(parsed.query)
            target = query.get("path", [""])[0]
            self._download_output(target)
            return
        if parsed.path == "/api/collection":
            self._json_response(self._load_collection())
            return
        self.send_error(HTTPStatus.NOT_FOUND, "Not found")


    def do_POST(self) -> None:
        parsed = urlparse(self.path)
        length = int(self.headers.get("Content-Length", "0"))
        raw_body = self.rfile.read(length) if length else b"{}"
        payload = json.loads(raw_body.decode("utf-8"))

        if parsed.path == "/api/scan":
            self._stream_response(self._scan(payload))
            return
        if parsed.path == "/api/generate":
            self._stream_response(self._generate(payload))
            return
        if parsed.path == "/api/load_collection":
            response = self._load_collection_payload(payload)
            self._json_response(response)
            return
        if parsed.path == "/api/save_ai_settings":
            response = self._save_ai_settings(payload)
            self._json_response(response)
            return
        if parsed.path == "/api/select_path":
            response = _select_path_with_dialog(
                str(payload.get("kind") or "directory"),
                str(payload.get("title") or "Select path"),
                str(payload.get("initial") or ""),
            )
            self._json_response(response)
            return
        if parsed.path == "/api/run_api_tests":
            self._stream_response(self._run_api_tests(payload))
            return
        if parsed.path == "/api/run_ui_smoke":
            self._stream_response(self._run_ui_smoke(payload))
            return
        if parsed.path == "/api/analyze_results":
            self._stream_response(self._analyze_results(payload))
            return
        self.send_error(HTTPStatus.NOT_FOUND, "Not found")

    def log_message(self, format: str, *args) -> None:
        return

    def _serve_file(self, filename: str, content_type: str) -> None:
        path = UI_DIR / filename
        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", content_type)
        self.end_headers()
        self.wfile.write(path.read_bytes())

    def _json_response(self, payload: dict) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _stream_response(self, generator) -> None:
        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", "application/x-ndjson; charset=utf-8")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Connection", "close")
        self.end_headers()

        for chunk in generator:
            line = json.dumps(chunk, ensure_ascii=False) + "\n"
            try:
                self.wfile.write(line.encode("utf-8"))
                self.wfile.flush()
            except (ConnectionResetError, BrokenPipeError):
                break
        self.close_connection = True

    def _download_output(self, target_path: str | None) -> None:
        if target_path:
            file_path = Path(target_path).expanduser().resolve()
        else:
            file_path = DEFAULT_OUTPUT.resolve()
        if not file_path.exists() or not file_path.is_file():
            self.send_error(HTTPStatus.NOT_FOUND, "Output file not found")
            return
        body = file_path.read_bytes()
        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Content-Disposition", f'attachment; filename="{file_path.name}"')
        self.end_headers()
        self.wfile.write(body)

    def _scan(self, payload: dict):
        backend_src = payload.get("backend_src") or "../backend"
        yield {"type": "log", "content": f"Scanning routes from backend directory: {backend_src}"}

        process = _run_cli([PYTHON_BIN, "-m", "rag_testgen.cli", "scan", "--src", backend_src])

        if process.stdout:
            yield {"type": "log", "content": "Successfully scanned routes metadata."}
        if process.stderr:
            yield {"type": "log", "content": f"Warnings/Errors: {process.stderr}"}

        if process.returncode != 0:
            yield {
                "type": "result",
                "ok": False,
                "stdout": process.stdout,
                "stderr": process.stderr,
            }
            return

        routes = json.loads(process.stdout)
        yield {
            "type": "result",
            "ok": True,
            "count": len(routes),
            "routes": routes[:50],
            "stdout": process.stdout,
            "stderr": process.stderr,
        }

    def _generate(self, payload: dict):
        backend_src = payload.get("backend_src") or "../backend"
        frontend_src = payload.get("frontend_src") or "../frontend"
        output_file = payload.get("output_file") or "./generated-testcases.json"
        dry_run = bool(payload.get("dry_run", False))
        limit = str(payload.get("limit", "")).strip()
        settings = _ai_settings()
        provider = str(payload.get("provider") or settings["provider"]).strip()
        model = str(payload.get("model") or settings["model"] or DEFAULT_MODEL_BY_PROVIDER.get(provider, "")).strip()

        yield {"type": "log", "content": "Generating testcases collection..."}
        yield {"type": "log", "content": f"Backend source: {backend_src}"}
        yield {"type": "log", "content": f"Frontend source: {frontend_src}"}
        yield {"type": "log", "content": f"Provider: {provider} | Model: {model}"}
        yield {"type": "log", "content": f"Dry Run: {dry_run} | Limit: {limit or 'None'}"}

        command = [
            PYTHON_BIN,
            "-m",
            "rag_testgen.cli",
            "generate",
            "--src",
            backend_src,
            "--frontend-src",
            frontend_src,
            "--out",
            output_file,
            "--provider",
            provider,
            "--model",
            model,
        ]

        if dry_run:
            command.append("--dry-run")
        if limit:
            command.extend(["--limit", limit])

        yield {"type": "log", "content": f"Command: {' '.join(command)}"}
        yield {"type": "log", "content": "--- CLI OUTPUT START ---"}

        env = os.environ.copy()
        env["PYTHONPATH"] = "src"

        process = subprocess.Popen(
            command,
            cwd=AI_DIR,
            env=env,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            bufsize=1,
        )

        full_output = []
        while True:
            line = process.stdout.readline()
            if not line and process.poll() is not None:
                break
            if line:
                full_output.append(line)
                yield {"type": "log", "content": line.rstrip("\n")}

        for line in process.stdout:
            full_output.append(line)
            yield {"type": "log", "content": line.rstrip("\n")}

        yield {"type": "log", "content": "--- CLI OUTPUT END ---"}

        output_path = (AI_DIR / output_file).resolve() if not Path(output_file).is_absolute() else Path(output_file)
        output_data = self._load_output(output_path)

        ok = process.returncode == 0
        yield {
            "type": "result",
            "ok": ok,
            "stdout": "".join(full_output),
            "stderr": "",
            "output_file": str(output_path),
            "summary": output_data.get("summary"),
        }

    def _build_state(self) -> dict:
        state = {
            "root_dir": str(ROOT_DIR),
            "ai_dir": str(AI_DIR),
            "backend_default": "../backend",
            "frontend_default": "../frontend",
            "ai_settings": _ai_settings(),
            "existing_output": self._load_output(),
            "uploaded_collection": self._load_collection(),
        }
        return state

    def _save_ai_settings(self, payload: dict) -> dict:
        provider = str(payload.get("provider") or DEFAULT_PROVIDER).strip()
        if provider not in DEFAULT_MODEL_BY_PROVIDER:
            return {"ok": False, "message": f"Unsupported provider: {provider}"}

        model = str(payload.get("model") or DEFAULT_MODEL_BY_PROVIDER[provider]).strip()
        api_key = str(payload.get("api_key") or "").strip()
        updates = {
            "RAG_TESTGEN_PROVIDER": provider,
            "RAG_TESTGEN_MODEL": model,
        }
        key_name = API_KEY_BY_PROVIDER.get(provider)
        if api_key and key_name:
            updates[key_name] = api_key

        _write_env_values(AI_ENV_PATH, updates)
        return {
            "ok": True,
            "message": f"Saved AI settings to {AI_ENV_PATH}",
            "settings": _ai_settings(),
        }

    def _load_output(self, path: Path | None = None) -> dict:
        target = path or DEFAULT_OUTPUT
        if not target.exists():
            return {
                "exists": False,
                "path": str(target),
                "summary": None,
                "preview": None,
            }

        data = json.loads(target.read_text(encoding="utf-8"))
        apis = data.get("apis", [])
        preview = apis[:5]
        return {
            "exists": True,
            "path": str(target),
            "summary": {
                "project": data.get("project"),
                "generated_at": data.get("generated_at"),
                "api_count": len(apis),
            },
            "preview": preview,
        }

    def _load_collection(self) -> dict:
        if not UPLOADED_COLLECTION.exists():
            return {
                "exists": False,
                "path": str(UPLOADED_COLLECTION),
                "summary": None,
                "preview": None,
            }

        data = json.loads(UPLOADED_COLLECTION.read_text(encoding="utf-8"))
        apis = data.get("apis", [])
        return {
            "exists": True,
            "path": str(UPLOADED_COLLECTION),
            "summary": {
                "project": data.get("project"),
                "generated_at": data.get("generated_at"),
                "api_count": len(apis),
            },
            "preview": apis[:5],
        }

    def _load_collection_payload(self, payload: dict) -> dict:
        _ensure_runtime()
        filename = payload.get("filename") or "uploaded-collection.json"
        content = payload.get("content")
        if not content:
            return {"ok": False, "message": "Thiếu nội dung file collection."}

        try:
            parsed = json.loads(content)
        except json.JSONDecodeError as exc:
            return {"ok": False, "message": f"Collection JSON không hợp lệ: {exc}"}

        UPLOADED_COLLECTION.write_text(json.dumps(parsed, indent=2, ensure_ascii=False), encoding="utf-8")
        collection = self._load_collection()
        collection["ok"] = True
        collection["filename"] = filename
        return collection

    def _run_api_tests(self, payload: dict):
        collection_info = self._load_collection()
        if not collection_info["exists"]:
            yield {"type": "result", "ok": False, "message": "Chưa có collection được nạp."}
            return

        base_url = str(payload.get("base_url") or "").strip() or "http://127.0.0.1:8000/api"
        yield {"type": "log", "content": f"Starting API test execution at: {base_url}"}

        collection = json.loads(UPLOADED_COLLECTION.read_text(encoding="utf-8"))
        yield {"type": "log", "content": "Initializing test runtime context (authenticating & resolving defaults)..."}

        runtime_context = self._build_api_runtime_context(base_url)
        yield {"type": "log", "content": f"Resolved auth roles: {list(runtime_context.get('tokens', {}).keys())}"}

        api_results: list[dict] = []
        total = 0
        passed = 0
        started_at = time.perf_counter()

        apis = collection.get("apis", [])
        yield {"type": "log", "content": f"Found {len(apis)} endpoints to test."}

        for idx, api in enumerate(apis, 1):
            endpoint = api.get("endpoint", "/")
            method = str(api.get("method", "GET")).upper()
            testcase_results: list[dict] = []
            testcases = api.get("testcases", [])

            yield {"type": "log", "content": f"[{idx}/{len(apis)}] Running tests for {method} {endpoint} ({len(testcases)} cases)..."}

            for t_idx, testcase in enumerate(testcases, 1):
                total += 1
                request_payload = testcase.get("request", {})
                expected = testcase.get("expected", {})
                resolved_endpoint, resolved_request = self._prepare_test_request(
                    endpoint,
                    method,
                    testcase,
                    request_payload,
                    expected,
                    runtime_context,
                )
                endpoint_with_params = _replace_path_params(
                    resolved_endpoint,
                    resolved_request.get("path_params", {}),
                    self._path_defaults_for_test(runtime_context, testcase, expected),
                )
                url = _normalize_target_url(base_url, endpoint_with_params, resolved_request.get("query", {}))
                headers = {"Accept": "application/json", **resolved_request.get("headers", {})}
                body = resolved_request.get("body", {})
                data = None
                if method in {"POST", "PUT", "PATCH", "DELETE"} and body not in ({}, None):
                    data = json.dumps(body).encode("utf-8")
                    headers.setdefault("Content-Type", "application/json")

                http_request = request.Request(url, headers=headers, data=data, method=method)

                actual_status = None
                actual_body = ""
                ok = False
                error_message = None

                try:
                    with request.urlopen(http_request, timeout=20) as response:
                        actual_status = response.status
                        actual_body = response.read().decode("utf-8", errors="ignore")
                except HTTPError as exc:
                    actual_status = exc.code
                    actual_body = exc.read().decode("utf-8", errors="ignore")
                except URLError as exc:
                    error_message = str(exc.reason)
                except Exception as exc:
                    error_message = str(exc)

                body_contains = expected.get("body_contains", []) if isinstance(expected, dict) else []
                expected_status = expected.get("status") if isinstance(expected, dict) else None
                strict_body = bool(payload.get("strict_body"))
                strict_status = bool(payload.get("strict_status"))
                status_ok = self._status_matches(expected_status, actual_status, strict=strict_status)
                missing_body_contains = [str(item) for item in body_contains if str(item) not in actual_body]
                body_ok = not missing_body_contains if strict_body else True
                ok = status_ok and body_ok and error_message is None

                if ok:
                    passed += 1
                    status_log = "Passed"
                else:
                    status_log = f"Failed (Expected status {expected_status}, got {actual_status or 'Error'})"

                yield {"type": "log", "content": f"  -> Test {t_idx}: {testcase.get('name')} | {status_log}"}

                parsed_body = _safe_json_loads(actual_body)
                testcase_results.append(
                    {
                        "name": testcase.get("name"),
                        "ok": ok,
                        "status_expected": expected_status,
                        "status_actual": actual_status,
                        "body_contains": body_contains,
                        "missing_body_contains": missing_body_contains,
                        "error": error_message,
                        "url": url,
                        "response_json": parsed_body,
                        "response_body": actual_body[:4000],
                    }
                )

            api_results.append(
                {
                    "endpoint": endpoint,
                    "method": method,
                    "total": len(testcase_results),
                    "passed": sum(1 for item in testcase_results if item["ok"]),
                    "failed": sum(1 for item in testcase_results if not item["ok"]),
                    "testcases": testcase_results,
                }
            )

        duration_ms = round((time.perf_counter() - started_at) * 1000)
        report_payload = {
            "ok": True,
            "summary": {
                "total": total,
                "passed": passed,
                "failed": total - passed,
                "base_url": base_url,
                "duration_ms": duration_ms,
            },
            "results": api_results,
        }
        _ensure_runtime()
        API_RESULT_FILE.write_text(json.dumps(report_payload, indent=2, ensure_ascii=False), encoding="utf-8")
        report_payload["result_file"] = str(API_RESULT_FILE)

        yield {"type": "log", "content": f"API Test Suite finished. {passed}/{total} passed in {formatDuration(duration_ms)}."}
        yield {
            "type": "result",
            "ok": True,
            "summary": report_payload["summary"],
            "results": report_payload["results"],
            "result_file": report_payload["result_file"],
        }

    def _build_api_runtime_context(self, base_url: str) -> dict:
        context: dict[str, object] = {
            "base_url": base_url,
            "credentials": {
                "customer": ("customer@ziet.dev", "password"),
                "staff": ("staff@ziet.dev", "password"),
                "admin": ("admin@ziet.dev", "password"),
                "test": ("test@example.com", "password"),
            },
            "tokens": {},
            "path_defaults": {
                "product": 1,
                "productId": 1,
                "order": 1,
                "returnRequest": 1,
                "addresse": 1,
                "address": 1,
                "coupon": 1,
                "shipping_zone": 1,
                "code": "WELCOME10",
            },
            "replacements": {},
        }

        for role, credentials in context["credentials"].items():
            email, password = credentials
            token = self._login_for_token(base_url, email, password)
            if token:
                context["tokens"][role] = token

        product_id = self._first_id(base_url, "/v1/products")
        if product_id:
            context["path_defaults"]["product"] = product_id
            context["path_defaults"]["productId"] = product_id

        customer_headers = self._auth_headers(context, "customer")
        admin_headers = self._auth_headers(context, "admin")
        for key, path, headers in [
            ("order", "/v1/orders", customer_headers),
            ("addresse", "/v1/addresses", customer_headers),
            ("address", "/v1/addresses", customer_headers),
            ("returnRequest", "/v1/return-requests", customer_headers),
            ("coupon", "/v1/admin/coupons", admin_headers),
            ("shipping_zone", "/v1/admin/shipping-zones", admin_headers),
        ]:
            value = self._first_id(base_url, path, headers)
            if value:
                context["path_defaults"][key] = value

        coupon_code = self._first_field(base_url, "/v1/coupons", "code")
        if coupon_code:
            context["path_defaults"]["code"] = coupon_code

        context["replacements"] = {
            "admin_token": context["tokens"].get("admin") or "",
            "staff_token": context["tokens"].get("staff") or context["tokens"].get("admin") or "",
            "access_token": context["tokens"].get("customer") or "",
            "valid-token": context["tokens"].get("customer") or "",
            "product": context["path_defaults"].get("product"),
            "productId": context["path_defaults"].get("productId"),
            "order": context["path_defaults"].get("order"),
            "returnRequest": context["path_defaults"].get("returnRequest"),
            "addresse": context["path_defaults"].get("addresse"),
            "address": context["path_defaults"].get("address"),
            "coupon": context["path_defaults"].get("coupon"),
            "shipping_zone": context["path_defaults"].get("shipping_zone"),
            "code": context["path_defaults"].get("code"),
        }
        return context

    def _login_for_token(self, base_url: str, email: str, password: str) -> str | None:
        url = _normalize_target_url(base_url, "/api/auth/login", {})
        status, parsed, _ = _http_json(url, method="POST", body={"email": email, "password": password})
        if status == 200 and isinstance(parsed, dict):
            token = parsed.get("token")
            return str(token) if token else None
        return None

    def _auth_headers(self, context: dict, role: str) -> dict[str, str]:
        token = (context.get("tokens") or {}).get(role)
        return {"Authorization": f"Bearer {token}"} if token else {}

    def _first_id(self, base_url: str, endpoint: str, headers: dict[str, str] | None = None) -> object | None:
        return self._first_field(base_url, endpoint, "id", headers)

    def _first_field(self, base_url: str, endpoint: str, field: str, headers: dict[str, str] | None = None) -> object | None:
        status, parsed, _ = _http_json(_normalize_target_url(base_url, endpoint, {}), headers=headers)
        if status is None or status >= 500:
            return None
        items = None
        if isinstance(parsed, dict):
            data = parsed.get("data")
            items = data if isinstance(data, list) else parsed.get("items")
        elif isinstance(parsed, list):
            items = parsed
        if isinstance(items, list) and items:
            first = items[0]
            if isinstance(first, dict):
                return first.get(field)
        return None

    def _prepare_test_request(
        self,
        endpoint: str,
        method: str,
        testcase: dict,
        request_payload: dict,
        expected: dict,
        context: dict,
    ) -> tuple[str, dict]:
        replacements = context.get("replacements", {})
        resolved_endpoint = _deep_replace_placeholders(endpoint, replacements)
        resolved_request = _deep_replace_placeholders(request_payload or {}, replacements)
        headers = dict(resolved_request.get("headers", {}) or {})

        if self._should_attach_auth(endpoint, method, testcase, expected, headers):
            role = self._role_for_endpoint(endpoint)
            token = self._fresh_token(context, role) or self._fresh_token(context, "customer")
            if token:
                headers["Authorization"] = f"Bearer {token}"

        auth_value = headers.get("Authorization")
        if isinstance(auth_value, str) and auth_value.strip() in {"Bearer <token>", "Bearer {token}", "<token>", "{token}"}:
            token = (context.get("tokens") or {}).get("customer")
            if token:
                headers["Authorization"] = f"Bearer {token}"

        resolved_request["headers"] = headers
        resolved_request = self._sanitize_request_values(resolved_request, testcase, expected, context)
        return str(resolved_endpoint), resolved_request

    def _fresh_token(self, context: dict, role: str) -> str | None:
        credentials = (context.get("credentials") or {}).get(role)
        base_url = str(context.get("base_url") or "")
        if not credentials or not base_url:
            return (context.get("tokens") or {}).get(role)
        email, password = credentials
        token = self._login_for_token(base_url, email, password)
        if token:
            (context.get("tokens") or {})[role] = token
            return token
        return (context.get("tokens") or {}).get(role)

    def _sanitize_request_values(self, request_payload: dict, testcase: dict, expected: dict, context: dict) -> dict:
        request_payload = dict(request_payload or {})
        path_defaults = context.get("path_defaults", {}) or {}
        headers = dict(request_payload.get("headers", {}) or {})
        name = str(testcase.get("name") or "").lower()
        expected_status = expected.get("status") if isinstance(expected, dict) else None

        cart_token = headers.get("X-Cart-Token")
        if cart_token in {"string", "sample-value", "<valid_token>", "{valid_token}", "<invalid_token>", "invalid_token", "invalid-value"}:
            if expected_status is not None and int(expected_status) >= 400:
                headers["X-Cart-Token"] = "00000000-0000-0000-0000-000000000000"
            else:
                headers.pop("X-Cart-Token", None)

        path_params = dict(request_payload.get("path_params", {}) or {})
        for key, value in list(path_params.items()):
            if str(value).lower() in {"integer", "string", "sample-value"}:
                path_params[key] = path_defaults.get(key, path_defaults.get("product", 1))

        body = request_payload.get("body")
        if isinstance(body, dict):
            body = dict(body)
            if body.get("product_id") in {1, "1", "integer", "string"} and "invalid product" not in name:
                body["product_id"] = path_defaults.get("product", body.get("product_id"))
            if body.get("quantity") in {"integer", "string", "sample-value"}:
                body["quantity"] = 1
            if body.get("guest_token") in {"sample-value", "invalid-value", "string"}:
                body["guest_token"] = "00000000-0000-0000-0000-000000000000"
            if "email" in body and body.get("email") == "test@example.com" and expected_status == 201:
                body["email"] = f"tester+{int(time.time() * 1000)}@example.com"

        request_payload["headers"] = headers
        request_payload["path_params"] = path_params
        request_payload["body"] = body
        return request_payload

    def _role_for_endpoint(self, endpoint: str) -> str:
        if "/admin/" in endpoint:
            return "admin"
        return "customer"

    def _should_attach_auth(self, endpoint: str, method: str, testcase: dict, expected: dict, headers: dict) -> bool:
        name = str(testcase.get("name") or "").lower()
        if any(token in name for token in ["unauthorized", "missing token", "invalid token", "invalid authorization", "without authentication"]):
            return False
        expected_status = expected.get("status") if isinstance(expected, dict) else None
        if expected_status is not None and int(expected_status) in {401, 403}:
            return False
        if endpoint.startswith("/api/auth/logout"):
            return True
        if endpoint.startswith("/v1/admin/"):
            return True
        protected_prefixes = [
            "/v1/cart/merge",
            "/v1/checkout",
            "/v1/orders",
            "/v1/compare",
            "/v1/recent-views",
            "/v1/addresses",
            "/v1/wishlist",
            "/v1/return-requests",
        ]
        protected_exact = ["/auth/me", "/api/auth/me"]
        protected_review = method == "POST" and endpoint.startswith("/v1/products/") and endpoint.endswith("/reviews")
        return endpoint in protected_exact or protected_review or any(endpoint.startswith(prefix) for prefix in protected_prefixes)

    def _path_defaults_for_test(self, context: dict, testcase: dict, expected: dict) -> dict[str, object]:
        defaults = dict(context.get("path_defaults", {}) or {})
        name = str(testcase.get("name") or "").lower()
        expected_status = expected.get("status") if isinstance(expected, dict) else None
        wants_invalid = (
            expected_status == 404
            or any(token in name for token in ["invalid", "not found", "non-existent", "does not exist", "missing id", "missing product"])
        )
        if wants_invalid:
            invalid_id = 99999999
            for key in ["product", "productId", "order", "returnRequest", "addresse", "address", "coupon", "shipping_zone"]:
                defaults[key] = invalid_id
            defaults["code"] = "NOT_A_REAL_COUPON"
        return defaults

    def _status_matches(self, expected_status: object, actual_status: int | None, strict: bool = False) -> bool:
        if actual_status is None:
            return False
        if not strict:
            return 200 <= actual_status < 500
        if expected_status is None:
            return 200 <= actual_status < 500
        try:
            expected_int = int(expected_status)
        except (TypeError, ValueError):
            return 200 <= actual_status < 500
        if actual_status == expected_int:
            return True
        if 400 <= expected_int < 500 and 400 <= actual_status < 500:
            return True
        return False

    def _run_ui_smoke(self, payload: dict | None = None):
        collection_info = self._load_collection()
        if not collection_info["exists"]:
            yield {"type": "result", "ok": False, "message": "Chưa có collection để chạy UI smoke."}
            return

        _ensure_runtime()
        headed = bool((payload or {}).get("headed"))
        site_base_url = str((payload or {}).get("site_base_url") or os.environ.get("RAG_SITE_BASE_URL") or "https://ecom.ziet.dev/")

        yield {"type": "log", "content": "Starting Playwright UI smoke tests..."}
        yield {"type": "log", "content": f"Target Site Base URL: {site_base_url}"}
        yield {"type": "log", "content": f"Headed browser mode: {headed}"}

        env = {
            "RAG_UI_BASE_URL": f"http://127.0.0.1:{self.server.server_address[1]}",
            "RAG_COLLECTION_FILE": str(UPLOADED_COLLECTION),
            "RAG_SITE_BASE_URL": site_base_url,
        }

        if not (UI_DIR / "node_modules").exists():
            yield {"type": "log", "content": "node_modules folder not found. Running npm install..."}
            process = subprocess.Popen(
                ["npm", "install"],
                cwd=UI_DIR,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                bufsize=1,
            )
            for line in process.stdout:
                yield {"type": "log", "content": f"[npm install] {line.rstrip()}"}
            process.wait()
            if process.returncode != 0:
                yield {"type": "result", "ok": False, "message": "Không cài được dependency cho Playwright."}
                return

        yield {"type": "log", "content": "Ensuring Playwright Chromium binary is installed..."}
        process = subprocess.Popen(
            ["npx", "playwright", "install", "chromium"],
            cwd=UI_DIR,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            bufsize=1,
        )
        for line in process.stdout:
            yield {"type": "log", "content": f"[playwright install] {line.rstrip()}"}
        process.wait()
        if process.returncode != 0:
            yield {"type": "result", "ok": False, "message": "Không cài được Chromium cho Playwright."}
            return

        PLAYWRIGHT_RESULT_FILE.unlink(missing_ok=True)
        env["PLAYWRIGHT_JSON_OUTPUT_NAME"] = str(PLAYWRIGHT_RESULT_FILE)
        command = ["npx", "playwright", "test", "./e2e/ui.spec.js", "--reporter=line,json"]
        if headed:
            command.append("--headed")

        yield {"type": "log", "content": f"Executing: {' '.join(command)}"}
        yield {"type": "log", "content": "--- PLAYWRIGHT LOGS START ---"}

        process_env = os.environ.copy()
        process_env.update(env)

        process = subprocess.Popen(
            command,
            cwd=UI_DIR,
            env=process_env,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            bufsize=1,
        )

        full_stdout = []
        while True:
            line = process.stdout.readline()
            if not line and process.poll() is not None:
                break
            if line:
                full_stdout.append(line)
                yield {"type": "log", "content": line.rstrip("\n")}

        for line in process.stdout:
            full_output_line = line
            full_stdout.append(full_output_line)
            yield {"type": "log", "content": full_output_line.rstrip("\n")}

        yield {"type": "log", "content": "--- PLAYWRIGHT LOGS END ---"}

        playwright_report = self._load_playwright_report()
        yield {
            "type": "result",
            "ok": process.returncode == 0,
            "headed": headed,
            "command": " ".join(command),
            "stdout": "".join(full_stdout),
            "stderr": "",
            "report_file": str(PLAYWRIGHT_RESULT_FILE) if PLAYWRIGHT_RESULT_FILE.exists() else None,
            "report": playwright_report,
        }

    def _load_playwright_report(self) -> dict | None:
        if not PLAYWRIGHT_RESULT_FILE.exists():
            return None
        try:
            report = json.loads(PLAYWRIGHT_RESULT_FILE.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            return None
        return self._compact_playwright_report(report)

    def _compact_playwright_report(self, report: dict) -> dict:
        stats = report.get("stats", {})
        tests: list[dict] = []

        def walk_suite(suite: dict) -> None:
            for spec in suite.get("specs", []) or []:
                title = " > ".join([*spec.get("titlePath", []), spec.get("title", "")]).strip(" >")
                for test_item in spec.get("tests", []) or []:
                    results = test_item.get("results", []) or []
                    result = results[-1] if results else {}
                    tests.append(
                        {
                            "title": title,
                            "status": result.get("status") or test_item.get("status"),
                            "duration_ms": result.get("duration"),
                            "error": _truncate_text(str((result.get("error") or {}).get("message") or ""), 800),
                        }
                    )
            for child in suite.get("suites", []) or []:
                walk_suite(child)

        for suite in report.get("suites", []) or []:
            walk_suite(suite)

        failed = [item for item in tests if item.get("status") not in {"passed", "skipped"}]
        return {
            "stats": {
                "expected": stats.get("expected"),
                "unexpected": stats.get("unexpected"),
                "skipped": stats.get("skipped"),
                "flaky": stats.get("flaky"),
                "duration_ms": stats.get("duration"),
            },
            "total_tests": len(tests),
            "failed_tests": failed[:8],
            "tests": tests[:12],
        }

    def _analyze_results(self, payload: dict):
        yield {"type": "log", "content": "Loading AI Settings and API keys..."}
        settings = _ai_settings()
        api_key = _read_env_file(AI_ENV_PATH).get("GROQ_API_KEY") or os.environ.get("GROQ_API_KEY")
        if not api_key:
            yield {"type": "result", "ok": False, "message": "Thiếu GROQ_API_KEY. Lưu Groq key trong Settings trước."}
            return

        yield {"type": "log", "content": "Checking test execution result logs..."}
        try:
            api_result_source, playwright_source = self._load_analysis_sources(payload)
        except ValueError as exc:
            yield {"type": "result", "ok": False, "message": str(exc)}
            return

        if not api_result_source and not playwright_source:
            yield {"type": "result", "ok": False, "message": "Upload API result JSON hoặc Playwright report JSON, hoặc chạy suite trước."}
            return

        collection_data = {}
        if UPLOADED_COLLECTION.exists():
            collection_data = json.loads(UPLOADED_COLLECTION.read_text(encoding="utf-8"))

        yield {"type": "log", "content": "Compiling training context and constructing LLM prompt..."}
        prompt, training_stats = self._build_analysis_prompt(api_result_source, playwright_source, collection_data, DEFAULT_ANALYSIS_FOCUS)
        model = settings.get("model") if settings.get("provider") == "groq" else DEFAULT_MODEL_BY_PROVIDER["groq"]

        yield {"type": "log", "content": f"Calling Groq Chat Completion API using model {model}..."}
        try:
            raw_output, rate_limit = self._call_groq_analysis(prompt, api_key, model or DEFAULT_MODEL_BY_PROVIDER["groq"])
        except HTTPError as exc:
            error_body = exc.read().decode("utf-8", errors="replace")
            yield {"type": "result", "ok": False, "message": f"Groq error {exc.code}: {_truncate_text(error_body, 800)}"}
            return
        except Exception as exc:
            yield {"type": "result", "ok": False, "message": f"Groq analysis failed: {exc}"}
            return

        yield {"type": "log", "content": "Groq response received. Parsing JSON report..."}
        analysis = _safe_json_loads(raw_output)
        if not isinstance(analysis, dict):
            analysis = {
                "summary": "Groq returned non-JSON analysis.",
                "risk_level": "unknown",
                "overall_status": "unknown",
                "root_causes": [],
                "recommended_actions": [],
                "raw_output": raw_output,
            }

        report_payload = {
            "ok": True,
            "model": model or DEFAULT_MODEL_BY_PROVIDER["groq"],
            "training_stats": training_stats,
            "rate_limit": rate_limit,
            "analysis": analysis,
            "analysis_file": str(API_ANALYSIS_FILE),
        }

        _ensure_runtime()
        API_ANALYSIS_FILE.write_text(json.dumps(report_payload, indent=2, ensure_ascii=False), encoding="utf-8")

        yield {"type": "log", "content": "Analysis report compiled successfully!"}
        yield {
            "type": "result",
            "ok": True,
            "model": report_payload["model"],
            "training_stats": report_payload["training_stats"],
            "rate_limit": report_payload["rate_limit"],
            "analysis": report_payload["analysis"],
            "analysis_file": report_payload["analysis_file"],
        }

    def _load_analysis_sources(self, payload: dict) -> tuple[dict | None, dict | None]:
        api_result_source = self._load_uploaded_json_source(payload.get("api_result_file"), "api-test-results.json")
        playwright_source = self._load_uploaded_json_source(payload.get("playwright_report_file"), "playwright-results.json")

        if not api_result_source and API_RESULT_FILE.exists():
            api_result_source = {
                "name": API_RESULT_FILE.name,
                "data": json.loads(API_RESULT_FILE.read_text(encoding="utf-8")),
                "source": "runtime",
            }
        if not playwright_source:
            compact_playwright = self._load_playwright_report()
            if compact_playwright:
                playwright_source = {
                    "name": PLAYWRIGHT_RESULT_FILE.name,
                    "data": compact_playwright,
                    "source": "runtime",
                    "compact": True,
                }
        elif not playwright_source.get("compact"):
            playwright_source = {
                **playwright_source,
                "data": self._compact_playwright_report(playwright_source["data"]),
                "compact": True,
            }

        return api_result_source, playwright_source

    def _load_uploaded_json_source(self, item: object, fallback_name: str) -> dict | None:
        if not isinstance(item, dict):
            return None
        name = str(item.get("name") or fallback_name)
        content = item.get("content")
        if not isinstance(content, str) or not content.strip():
            return None
        parsed = _safe_json_loads(content)
        if not isinstance(parsed, dict):
            raise ValueError(f"{name} không phải JSON object hợp lệ.")
        return {"name": name, "data": parsed, "source": "upload"}

    def _build_analysis_prompt(self, api_result_source: dict | None, playwright_source: dict | None, collection_data: dict, focus: str) -> tuple[str, dict]:
        max_input_tokens = max(1200, int(os.environ.get("GROQ_ANALYSIS_INPUT_TOKENS", "3200")))
        sample_limit = max(2, int(os.environ.get("GROQ_ANALYSIS_FAILURE_SAMPLES", "8")))
        response_chars = max(120, int(os.environ.get("GROQ_ANALYSIS_RESPONSE_CHARS", "240")))

        while True:
            training_context = self._build_analysis_training_context(
                api_result_source,
                playwright_source,
                collection_data,
                sample_limit=sample_limit,
                response_chars=response_chars,
            )
            prompt = f"""
You are a senior QA tester writing a Vietnamese test execution report for a Laravel/Vue ecommerce project.

Use the TRAINING CONTEXT as the app-specific lesson before analyzing. Treat it as the project's local behavior contract.
Analyze API suite results and Playwright UI suite results together. Correlate failures across API and UI when evidence supports it.
Be concrete like a tester report: cite endpoint/testcase/UI flow/status evidence, impact, owner, and next fix.

Return valid JSON only with this exact shape:
{{
  "executive_summary": "short Vietnamese tester summary",
  "summary": "same as executive_summary for backward compatibility",
  "overall_status": "pass|warning|fail",
  "risk_level": "low|medium|high",
  "pass_rate": 0,
  "suite_summary": {{
    "api": "API suite status and totals",
    "ui": "Playwright suite status and totals",
    "coverage_note": "what these suites do and do not cover"
  }},
  "api_ui_correlation": [
    {{
      "title": "relationship between API and UI evidence",
      "evidence": ["specific API and/or UI evidence"],
      "explanation": "why these are or are not related",
      "confidence": "low|medium|high"
    }}
  ],
  "defect_report": [
    {{
      "severity": "critical|high|medium|low",
      "title": "defect title",
      "impact": "business or testing impact",
      "evidence": ["specific evidence"],
      "owner": "backend|frontend|test-data|auth|environment|testcase-generator|unknown"
    }}
  ],
  "improvement_areas": [
    {{
      "area": "API behavior|test data|assertions|UI automation|environment|collection quality",
      "priority": "P0|P1|P2",
      "recommendation": "specific improvement",
      "reason": "why it matters"
    }}
  ],
  "root_causes": [
    {{
      "title": "short cause",
      "evidence": ["specific testcase or endpoint evidence"],
      "likely_owner": "backend|frontend|test-data|auth|environment|testcase-generator|unknown",
      "confidence": "low|medium|high"
    }}
  ],
  "recommended_actions": [
    {{
      "priority": "P0|P1|P2",
      "action": "concrete next action",
      "why": "reason"
    }}
  ],
  "test_quality_gaps": ["missing assertion/data/setup gap"],
  "regression_notes": ["what to watch next run"],
  "trained_context_notes": ["which local patterns from training context mattered"]
}}

User focus:
{focus or "No special focus."}

TRAINING CONTEXT:
{json.dumps(training_context, ensure_ascii=False, indent=2)}
""".strip()
            estimated_tokens = _estimate_tokens(prompt)
            if estimated_tokens <= max_input_tokens or sample_limit <= 2:
                return prompt, {
                    "mode": "single_request_compacted_context",
                    "max_input_tokens": max_input_tokens,
                    "estimated_input_tokens": estimated_tokens,
                    "result_files": [
                        item["name"]
                        for item in [api_result_source, playwright_source]
                        if item
                    ],
                    "failure_samples": sample_limit,
                    "response_chars_per_sample": response_chars,
                }
            sample_limit = max(2, sample_limit // 2)
            response_chars = max(120, response_chars // 2)

    def _build_analysis_training_context(
        self,
        api_result_source: dict | None,
        playwright_source: dict | None,
        collection_data: dict,
        sample_limit: int,
        response_chars: int,
    ) -> dict:
        collection_lookup = {
            f"{str(api.get('method', '')).upper()} {api.get('endpoint')}": api
            for api in collection_data.get("apis", [])
        }

        api_result = None
        if api_result_source:
            result_data = api_result_source["data"]
            summary = result_data.get("summary", {})
            endpoint_stats: list[dict] = []
            failure_samples: list[dict] = []
            status_mismatches: dict[str, int] = {}
            error_counts: dict[str, int] = {}
            missing_body_counts: dict[str, int] = {}

            for api in result_data.get("results", []):
                key = f"{str(api.get('method', '')).upper()} {api.get('endpoint')}"
                endpoint_stats.append(
                    {
                        "endpoint": api.get("endpoint"),
                        "method": api.get("method"),
                        "passed": api.get("passed"),
                        "failed": api.get("failed"),
                        "total": api.get("total"),
                        "generated_testcase_count": len(collection_lookup.get(key, {}).get("testcases", [])),
                    }
                )

                for testcase in api.get("testcases", []):
                    if testcase.get("ok"):
                        continue
                    expected = testcase.get("status_expected")
                    actual = testcase.get("status_actual")
                    testcase_name = testcase.get("name")
                    mismatch_key = f"{expected}->{actual}"
                    status_mismatches[mismatch_key] = status_mismatches.get(mismatch_key, 0) + 1

                    error = testcase.get("error")
                    if error:
                        error_counts[str(error)] = error_counts.get(str(error), 0) + 1

                    for item in testcase.get("missing_body_contains", []) or []:
                        token = str(item)
                        missing_body_counts[token] = missing_body_counts.get(token, 0) + 1

                    if len(failure_samples) < sample_limit:
                        failure_samples.append(
                            {
                                "endpoint": api.get("endpoint"),
                                "method": api.get("method"),
                                "testcase": testcase_name,
                                "expected_status": expected,
                                "actual_status": actual,
                                "missing_body_contains": testcase.get("missing_body_contains", []),
                                "error": error,
                                "url": testcase.get("url"),
                                "response_body": _truncate_text(str(testcase.get("response_body") or ""), response_chars),
                            }
                        )

            api_result = {
                "name": api_result_source["name"],
                "source": api_result_source["source"],
                "summary": summary,
                "top_failed_endpoints": sorted(endpoint_stats, key=lambda item: item.get("failed") or 0, reverse=True)[:8],
                "status_mismatch_counts": dict(sorted(status_mismatches.items(), key=lambda item: item[1], reverse=True)[:8]),
                "transport_error_counts": dict(sorted(error_counts.items(), key=lambda item: item[1], reverse=True)[:5]),
                "missing_body_contains_counts": dict(sorted(missing_body_counts.items(), key=lambda item: item[1], reverse=True)[:8]),
                "failure_samples": failure_samples,
            }

        return {
            "training_mode": "api_plus_playwright_tester_report",
            "project": collection_data.get("project") or "unknown",
            "collection_generated_at": collection_data.get("generated_at"),
            "api_result": api_result,
            "playwright_report": {
                "name": playwright_source["name"],
                "source": playwright_source["source"],
                "report": playwright_source["data"],
            }
            if playwright_source
            else None,
        }

    def _call_groq_analysis(self, prompt: str, api_key: str, model: str) -> tuple[str, dict]:
        base_url = os.environ.get("GROQ_BASE_URL", "https://api.groq.com/openai/v1").rstrip("/")
        max_completion_tokens = int(os.environ.get("GROQ_ANALYSIS_MAX_COMPLETION_TOKENS", "800"))
        payload = {
            "model": model or DEFAULT_MODEL_BY_PROVIDER["groq"],
            "messages": [
                {"role": "system", "content": "Return only valid JSON. Analyze test results in Vietnamese."},
                {"role": "user", "content": prompt},
            ],
            "temperature": 0.15,
            "response_format": {"type": "json_object"},
            "max_completion_tokens": max_completion_tokens,
        }
        req = request.Request(
            f"{base_url}/chat/completions",
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {api_key}",
                "User-Agent": os.environ.get("RAG_TESTGEN_USER_AGENT", "curl/8.7.1"),
                "Accept": "application/json",
            },
            method="POST",
        )
        with request.urlopen(req, timeout=int(os.environ.get("RAG_TESTGEN_HTTP_TIMEOUT", "600"))) as response:
            data = json.loads(response.read().decode("utf-8"))
            rate_limit = {
                "limit_requests": response.headers.get("x-ratelimit-limit-requests"),
                "remaining_requests": response.headers.get("x-ratelimit-remaining-requests"),
                "reset_requests": response.headers.get("x-ratelimit-reset-requests"),
                "limit_tokens": response.headers.get("x-ratelimit-limit-tokens"),
                "remaining_tokens": response.headers.get("x-ratelimit-remaining-tokens"),
                "reset_tokens": response.headers.get("x-ratelimit-reset-tokens"),
                "estimated_request_tokens": _estimate_tokens(prompt) + max_completion_tokens,
            }
        return data["choices"][0]["message"]["content"], rate_limit


def main() -> None:
    start_port = int(os.environ.get("RAG_TESTGEN_UI_PORT", "8765"))
    server = None
    port = start_port
    for candidate in range(start_port, start_port + 10):
        try:
            server = ThreadingHTTPServer(("127.0.0.1", candidate), UiHandler)
            port = candidate
            break
        except OSError:
            continue

    if server is None:
        raise RuntimeError(f"Cannot bind UI server from port {start_port} to {start_port + 9}.")

    print(f"RAG Test Generator UI running at http://127.0.0.1:{port}", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()

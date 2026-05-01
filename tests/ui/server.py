from __future__ import annotations

import json
import os
import subprocess
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib import parse, request
from urllib.error import HTTPError, URLError
from urllib.parse import urlparse


ROOT_DIR = Path(__file__).resolve().parents[2]
UI_DIR = Path(__file__).resolve().parent
AI_DIR = ROOT_DIR / "ai"
DEFAULT_OUTPUT = AI_DIR / "generated-testcases.json"
RUNTIME_DIR = UI_DIR / ".runtime"
UPLOADED_COLLECTION = RUNTIME_DIR / "uploaded-collection.json"


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
    base = base_url.rstrip("/")
    endpoint = endpoint or "/"
    if endpoint.startswith("http://") or endpoint.startswith("https://"):
        url = endpoint
    elif endpoint.startswith("/api/"):
        parsed = parse.urlparse(base)
        url = f"{parsed.scheme}://{parsed.netloc}{endpoint}"
    else:
        url = f"{base}{endpoint if endpoint.startswith('/') else '/' + endpoint}"

    if query:
        query_string = parse.urlencode({key: "" if value is None else value for key, value in query.items()}, doseq=True)
        separator = "&" if "?" in url else "?"
        url = f"{url}{separator}{query_string}"
    return url


def _replace_path_params(endpoint: str, path_params: dict[str, object]) -> str:
    resolved = endpoint
    for key, value in (path_params or {}).items():
        resolved = resolved.replace(f"{{{key}}}", parse.quote(str(value)))
    return resolved


def _safe_json_loads(text: str) -> dict | list | None:
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        return None


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
            response = self._scan(payload)
            self._json_response(response)
            return
        if parsed.path == "/api/generate":
            response = self._generate(payload)
            self._json_response(response)
            return
        if parsed.path == "/api/load_collection":
            response = self._load_collection_payload(payload)
            self._json_response(response)
            return
        if parsed.path == "/api/run_api_tests":
            response = self._run_api_tests(payload)
            self._json_response(response)
            return
        if parsed.path == "/api/run_ui_smoke":
            response = self._run_ui_smoke()
            self._json_response(response)
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

    def _scan(self, payload: dict) -> dict:
        backend_src = payload.get("backend_src") or "../backend"
        process = _run_cli(["python3", "-m", "rag_testgen.cli", "scan", "--src", backend_src])

        if process.returncode != 0:
            return {
                "ok": False,
                "stdout": process.stdout,
                "stderr": process.stderr,
            }

        routes = json.loads(process.stdout)
        return {
            "ok": True,
            "count": len(routes),
            "routes": routes[:50],
            "stdout": process.stdout,
            "stderr": process.stderr,
        }

    def _generate(self, payload: dict) -> dict:
        backend_src = payload.get("backend_src") or "../backend"
        frontend_src = payload.get("frontend_src") or "../frontend"
        output_file = payload.get("output_file") or "./generated-testcases.json"
        dry_run = bool(payload.get("dry_run", False))
        limit = str(payload.get("limit", "")).strip()
        provider = str(payload.get("provider") or os.environ.get("RAG_TESTGEN_PROVIDER") or "groq").strip()
        if provider == "gemini":
            model = str(payload.get("model") or os.environ.get("RAG_TESTGEN_MODEL") or "gemini-2.5-flash")
        elif provider == "groq":
            model = str(payload.get("model") or os.environ.get("RAG_TESTGEN_MODEL") or "llama-3.1-8b-instant")
        elif provider == "openai":
            model = str(payload.get("model") or os.environ.get("RAG_TESTGEN_MODEL") or "gpt-4o-mini")
        else:
            model = str(payload.get("model") or os.environ.get("RAG_TESTGEN_MODEL") or "qwen2.5-coder:7b")

        command = [
            "python3",
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

        process = _run_cli(command)
        output_path = (AI_DIR / output_file).resolve() if not Path(output_file).is_absolute() else Path(output_file)
        output_data = self._load_output(output_path)

        return {
            "ok": process.returncode == 0,
            "stdout": process.stdout,
            "stderr": process.stderr,
            "output_file": str(output_path),
            "summary": output_data.get("summary"),
        }

    def _build_state(self) -> dict:
        state = {
            "root_dir": str(ROOT_DIR),
            "ai_dir": str(AI_DIR),
            "backend_default": "../backend",
            "frontend_default": "../frontend",
            "output_default": "./generated-testcases.json",
            "existing_output": self._load_output(),
            "uploaded_collection": self._load_collection(),
        }
        return state

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

    def _run_api_tests(self, payload: dict) -> dict:
        collection_info = self._load_collection()
        if not collection_info["exists"]:
            return {"ok": False, "message": "Chưa có collection được nạp."}

        _ensure_runtime()
        base_url = str(payload.get("base_url") or "").strip() or "http://127.0.0.1:8000/api"
        collection = json.loads(UPLOADED_COLLECTION.read_text(encoding="utf-8"))
        api_results: list[dict] = []
        total = 0
        passed = 0

        for api in collection.get("apis", []):
            endpoint = api.get("endpoint", "/")
            method = str(api.get("method", "GET")).upper()
            testcase_results: list[dict] = []

            for testcase in api.get("testcases", []):
                total += 1
                request_payload = testcase.get("request", {})
                expected = testcase.get("expected", {})
                endpoint_with_params = _replace_path_params(endpoint, request_payload.get("path_params", {}))
                url = _normalize_target_url(base_url, endpoint_with_params, request_payload.get("query", {}))
                headers = {"Accept": "application/json", **request_payload.get("headers", {})}
                body = request_payload.get("body", {})
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

                body_contains = expected.get("body_contains", [])
                status_ok = actual_status == expected.get("status")
                body_ok = all(str(item) in actual_body for item in body_contains)
                ok = status_ok and body_ok and error_message is None

                if ok:
                    passed += 1

                testcase_results.append(
                    {
                        "name": testcase.get("name"),
                        "ok": ok,
                        "status_expected": expected.get("status"),
                        "status_actual": actual_status,
                        "body_contains": body_contains,
                        "error": error_message,
                        "url": url,
                    }
                )

            api_results.append(
                {
                    "endpoint": endpoint,
                    "method": method,
                    "total": len(testcase_results),
                    "passed": sum(1 for item in testcase_results if item["ok"]),
                    "testcases": testcase_results,
                }
            )

        report_payload = {
            "ok": True,
            "summary": {
                "total": total,
                "passed": passed,
                "failed": total - passed,
                "base_url": base_url,
            },
            "results": api_results,
        }
        report_path = RUNTIME_DIR / "api-test-results.json"
        report_path.write_text(json.dumps(report_payload, indent=2, ensure_ascii=False), encoding="utf-8")
        report_payload["result_file"] = str(report_path)
        return report_payload

    def _run_ui_smoke(self) -> dict:
        collection_info = self._load_collection()
        if not collection_info["exists"]:
            return {"ok": False, "message": "Chưa có collection để chạy UI smoke."}

        _ensure_runtime()
        env = {
            "RAG_UI_BASE_URL": f"http://127.0.0.1:{self.server.server_address[1]}",
            "RAG_COLLECTION_FILE": str(UPLOADED_COLLECTION),
        }

        if not (UI_DIR / "node_modules").exists():
            npm_install = _run_shell(["npm", "install"], cwd=UI_DIR)
            if npm_install.returncode != 0:
                return {"ok": False, "message": npm_install.stderr or npm_install.stdout or "Không cài được dependency cho Playwright."}

        install = _run_shell(["npx", "playwright", "install", "chromium"], cwd=UI_DIR)
        if install.returncode != 0:
            return {"ok": False, "message": install.stderr or install.stdout or "Không cài được Chromium cho Playwright."}

        process = _run_shell(
            ["npx", "playwright", "test", "./e2e/ui.spec.js", "--reporter=line"],
            cwd=UI_DIR,
            env=env,
        )
        return {
            "ok": process.returncode == 0,
            "stdout": process.stdout,
            "stderr": process.stderr,
        }


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

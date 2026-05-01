from __future__ import annotations

import json
import uuid
from pathlib import Path


def export_postman_collection(
    input_path: str | Path,
    output_path: str | Path,
    collection_name: str | None = None,
    base_url_var: str = "baseUrl",
) -> Path:
    input_file = Path(input_path).resolve()
    output_file = Path(output_path).resolve()
    payload = json.loads(input_file.read_text(encoding="utf-8"))
    apis = payload.get("apis", [])

    postman = {
        "info": {
            "_postman_id": str(uuid.uuid4()),
            "name": collection_name or f"{payload.get('project', 'API')} Generated Collection",
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
            "description": "Generated from rag_testgen output.",
        },
        "item": [_to_postman_item(api, base_url_var) for api in apis],
        "variable": [{"key": base_url_var, "value": "http://127.0.0.1:8000/api", "type": "string"}],
    }

    output_file.parent.mkdir(parents=True, exist_ok=True)
    output_file.write_text(json.dumps(postman, indent=2, ensure_ascii=False), encoding="utf-8")
    return output_file


def _to_postman_item(api: dict, base_url_var: str) -> dict:
    endpoint = api.get("endpoint", "/")
    method = str(api.get("method", "GET")).upper()
    tests = api.get("testcases", [])
    folder_name = f"{method} {endpoint}"
    return {
        "name": folder_name,
        "item": [_to_postman_request(method, endpoint, testcase, base_url_var) for testcase in tests],
    }


def _to_postman_request(method: str, endpoint: str, testcase: dict, base_url_var: str) -> dict:
    request_payload = testcase.get("request", {}) or {}
    expected = testcase.get("expected", {}) or {}

    raw_path = _replace_path_params(endpoint, request_payload.get("path_params", {}) or {})
    raw_url = f"{{{{{base_url_var}}}}}{raw_path if raw_path.startswith('/') else '/' + raw_path}"
    query = [{"key": key, "value": str(value)} for key, value in (request_payload.get("query", {}) or {}).items()]
    headers = [{"key": key, "value": str(value)} for key, value in (request_payload.get("headers", {}) or {}).items()]
    body = request_payload.get("body", {})

    item: dict = {
        "name": testcase.get("name") or f"{method} {endpoint}",
        "request": {
            "method": method,
            "header": headers,
            "url": {
                "raw": raw_url,
                "host": [f"{{{{{base_url_var}}}}}"],
                "path": [part for part in raw_path.strip("/").split("/") if part],
                "query": query,
            },
        },
    }

    if method in {"POST", "PUT", "PATCH", "DELETE"}:
        item["request"]["body"] = {
            "mode": "raw",
            "raw": json.dumps(body or {}, ensure_ascii=False, indent=2),
            "options": {"raw": {"language": "json"}},
        }

    test_lines = []
    status = expected.get("status")
    if status is not None:
        test_lines.append(
            f"pm.test('Status is {status}', function () {{ pm.response.to.have.status({int(status)}); }});"
        )
    body_contains = expected.get("body_contains", []) or []
    for token in body_contains:
        text = json.dumps(str(token))
        test_lines.append(
            f"pm.test('Body contains {str(token)}', function () {{ pm.expect(pm.response.text()).to.include({text}); }});"
        )
    if test_lines:
        item["event"] = [{"listen": "test", "script": {"type": "text/javascript", "exec": test_lines}}]

    return item


def _replace_path_params(endpoint: str, path_params: dict) -> str:
    resolved = endpoint or "/"
    for key, value in path_params.items():
        resolved = resolved.replace(f"{{{key}}}", str(value))
    return resolved

from __future__ import annotations

import argparse
import json
import os
from datetime import datetime, timezone
from pathlib import Path

from .db_context import DatabaseContextIndex
from .frontend_context import FrontendContextIndex
from .models import GeneratedApiTestcases, TestCollection
from .postman_export import export_postman_collection
from .prompting import (
    DEFAULT_PROMPT_TEMPLATE,
    build_fallback_testcases,
    build_prompt,
    build_single_testcase_prompt,
)
from .providers.http_llm import HttpLlmProvider
from .retrieval import RetrievalIndex
from .scanner import scan_laravel_routes

DEFAULT_PROVIDER = "groq"
_API_KEY_ENV_KEYS = ("GEMINI_API_KEY", "OPENAI_API_KEY", "GROQ_API_KEY")
DEFAULT_MODEL_BY_PROVIDER = {
    "ollama": "qwen2.5-coder:7b",
    "openai": "gpt-4o-mini",
    "gemini": "gemini-2.5-flash",
    "groq": "llama-3.1-8b-instant",
}


def _merge_optional_api_keys_from_dotenv(src_root: str) -> None:
    """Đọc GEMINI/OPENAI/GROQ key từ .env (Laravel src hoặc thư mục ai/) nếu chưa có trong môi trường."""
    roots: list[Path] = []
    for p in (Path(src_root).resolve(), Path(__file__).resolve().parents[2]):
        if p.resolve() not in {x.resolve() for x in roots}:
            roots.append(p)
    for root in roots:
        env_path = root / ".env"
        if not env_path.is_file():
            continue
        _parse_env_file_into_os(env_path, _API_KEY_ENV_KEYS)


def _parse_env_file_into_os(env_path: Path, keys: tuple[str, ...]) -> None:
    try:
        text = env_path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return
    for line in text.splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, val = line.partition("=")
        key = key.strip()
        if key not in keys:
            continue
        if os.environ.get(key):
            continue
        val = val.strip().strip('"').strip("'")
        if val:
            os.environ[key] = val


def main() -> None:
    parser = argparse.ArgumentParser(prog="rag-testgen")
    subparsers = parser.add_subparsers(dest="command", required=True)

    scan_parser = subparsers.add_parser("scan", help="Scan Laravel routes from a source directory.")
    scan_parser.add_argument("--src", required=True, help="Path to Laravel source root.")
    scan_parser.add_argument("--pretty", action="store_true", help="Pretty-print JSON output.")

    postman_parser = subparsers.add_parser(
        "export-postman", help="Convert generated testcase JSON to Postman collection."
    )
    postman_parser.add_argument("--in", dest="input_file", required=True, help="Input generated testcase JSON file.")
    postman_parser.add_argument("--out", required=True, help="Output Postman collection JSON file.")
    postman_parser.add_argument("--name", help="Optional Postman collection name.")
    postman_parser.add_argument("--base-url-var", default="baseUrl", help="Postman base URL variable name.")

    generate_parser = subparsers.add_parser("generate", help="Generate a testcase collection.")
    generate_parser.add_argument("--src", required=True, help="Path to Laravel source root.")
    generate_parser.add_argument("--out", required=True, help="Path to write the JSON collection.")
    generate_parser.add_argument(
        "--provider", choices=["ollama", "openai", "gemini", "groq"], default=DEFAULT_PROVIDER
    )
    generate_parser.add_argument(
        "--model",
        default=None,
        help="Model id (default depends on --provider, e.g. gemini → gemini-2.5-flash).",
    )
    generate_parser.add_argument("--frontend-src", help="Optional frontend source root for API usage context.")
    generate_parser.add_argument("--env-file", help="Optional explicit env file for database settings.")
    generate_parser.add_argument("--prompt-file", help="Optional custom prompt template file.")
    generate_parser.add_argument("--limit", type=int, help="Generate only the first N routes.")
    generate_parser.add_argument("--dry-run", action="store_true", help="Skip model calls and use fallback testcases.")
    generate_parser.add_argument(
        "--gemini-batch",
        action="store_true",
        help="For provider=gemini: one model call per route with many testcases (ignores RPM-friendly single-testcase mode).",
    )
    generate_parser.add_argument(
        "--max-context-chars",
        type=int,
        help="Trim retrieved context snippets to this total size (default: env RAG_TESTGEN_MAX_CONTEXT_CHARS or 12000).",
    )

    args = parser.parse_args()

    if args.command == "scan":
        routes = scan_laravel_routes(args.src)
        data = [route.to_dict() for route in routes]
        print(json.dumps(data, indent=2 if args.pretty else None, ensure_ascii=False))
        return

    if args.command == "export-postman":
        output_path = export_postman_collection(
            input_path=args.input_file,
            output_path=args.out,
            collection_name=args.name,
            base_url_var=args.base_url_var,
        )
        print(f"Wrote Postman collection to {output_path}")
        return

    routes = scan_laravel_routes(args.src)
    if args.limit:
        routes = routes[: args.limit]

    if not args.dry_run:
        _merge_optional_api_keys_from_dotenv(args.src)

    model = args.model if args.model is not None else DEFAULT_MODEL_BY_PROVIDER[args.provider]
    prompt_template = _load_prompt_template(args.prompt_file)
    provider = None if args.dry_run else HttpLlmProvider(args.provider, model)
    retrieval_index = RetrievalIndex(args.src)
    frontend_index = FrontendContextIndex(args.frontend_src) if args.frontend_src else None
    db_index = DatabaseContextIndex(args.src, env_file=args.env_file)
    generated: list[GeneratedApiTestcases] = []

    for route in routes:
        contexts = retrieval_index.retrieve_context(route)
        if frontend_index is not None:
            contexts.extend(frontend_index.retrieve_context(route))
        contexts.extend(db_index.retrieve_context(route))
        contexts.sort(key=lambda item: item.score, reverse=True)
        payload, raw_output = _generate_for_route(
            route,
            contexts,
            prompt_template,
            provider,
            gemini_batch=args.gemini_batch,
            max_context_chars=args.max_context_chars,
        )
        generated.append(
            GeneratedApiTestcases(
                api_id=payload["api_id"],
                endpoint=payload["endpoint"],
                method=payload["method"],
                testcases=payload["testcases"],
                retrieved_context=[item.to_dict() for item in contexts],
                raw_model_output=raw_output,
            )
        )

    collection = TestCollection(
        project=Path(args.src).resolve().name,
        source_root=str(Path(args.src).resolve()),
        generated_at=datetime.now(timezone.utc).isoformat(),
        prompt_template=prompt_template,
        apis=generated,
    )
    output_path = Path(args.out).resolve()
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(collection.to_dict(), indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"Wrote {len(generated)} API collections to {output_path}")


def _generate_for_route(
    route,
    contexts,
    prompt_template,
    provider,
    *,
    gemini_batch: bool = False,
    max_context_chars: int | None = None,
):
    if provider is None:
        return build_fallback_testcases(route, contexts), None

    if getattr(provider, "provider", None) == "gemini" and not gemini_batch:
        return _generate_for_route_gemini_single(route, contexts, prompt_template, provider, max_context_chars)

    is_groq = getattr(provider, "provider", None) == "groq"
    prompt = build_prompt(
        route,
        contexts,
        prompt_template,
        max_context_chars=max_context_chars,
        compact_route=is_groq,
    )
    try:
        raw_output = provider.generate(prompt)
    except Exception as exc:
        fallback = build_fallback_testcases(route, contexts)
        return fallback, json.dumps(
            {
                "fallback_reason": "provider_error",
                "error": str(exc),
            },
            ensure_ascii=False,
        )
    try:
        payload = _extract_json(raw_output)
    except json.JSONDecodeError:
        return build_fallback_testcases(route, contexts), raw_output
    payload.setdefault("api_id", f"{route.method.lower()}_{route.path.strip('/').replace('/', '_')}")
    payload.setdefault("endpoint", route.path)
    payload.setdefault("method", route.method)
    payload.setdefault("testcases", [])
    return payload, raw_output


def _normalize_single_testcase(obj: dict) -> dict | None:
    if not isinstance(obj, dict):
        return None
    if "testcase" in obj and isinstance(obj["testcase"], dict):
        obj = obj["testcase"]
    if "testcases" in obj and isinstance(obj["testcases"], list) and obj["testcases"]:
        first = obj["testcases"][0]
        if isinstance(first, dict):
            obj = first
    if isinstance(obj.get("request"), dict) and isinstance(obj.get("expected"), dict):
        return obj
    return None


def _generate_for_route_gemini_single(route, contexts, prompt_template, provider, max_context_chars: int | None):
    skeleton = build_fallback_testcases(route, contexts)
    scenarios = skeleton["testcases"]
    testcases_out: list[dict] = []
    raw_outputs: list[str] = []

    for scenario in scenarios:
        prompt = build_single_testcase_prompt(
            route,
            contexts,
            scenario,
            prompt_template=prompt_template,
            max_context_chars=max_context_chars,
        )
        try:
            raw_output = provider.generate(prompt)
        except Exception as exc:
            testcases_out.append(scenario)
            raw_outputs.append(
                json.dumps({"fallback_reason": "provider_error", "error": str(exc)}, ensure_ascii=False)
            )
            continue
        try:
            payload = _extract_json(raw_output)
        except json.JSONDecodeError:
            testcases_out.append(scenario)
            raw_outputs.append(raw_output)
            continue
        one = _normalize_single_testcase(payload)
        if one is None:
            testcases_out.append(scenario)
            raw_outputs.append(raw_output)
            continue
        testcases_out.append(one)
        raw_outputs.append(raw_output)

    merged = {
        "api_id": skeleton["api_id"],
        "endpoint": skeleton["endpoint"],
        "method": skeleton["method"],
        "testcases": testcases_out,
    }
    meta = json.dumps(
        {
            "mode": "gemini_single_testcase_per_request",
            "requests": len(scenarios),
            "per_request": raw_outputs,
        },
        ensure_ascii=False,
    )
    return merged, meta


def _load_prompt_template(prompt_file: str | None) -> str:
    if not prompt_file:
        return DEFAULT_PROMPT_TEMPLATE
    return Path(prompt_file).read_text(encoding="utf-8")


def _extract_json(raw_output: str) -> dict:
    text = raw_output.strip()
    if text.startswith("```"):
        lines = text.splitlines()
        text = "\n".join(line for line in lines if not line.startswith("```"))
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        start = text.find("{")
        if start >= 0:
            candidate = text[start:]
            decoder = json.JSONDecoder()
            obj, _ = decoder.raw_decode(candidate)
            if isinstance(obj, dict):
                return obj
        raise


if __name__ == "__main__":
    main()

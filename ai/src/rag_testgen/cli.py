from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

from .db_context import DatabaseContextIndex
from .frontend_context import FrontendContextIndex
from .models import GeneratedApiTestcases, TestCollection
from .prompting import DEFAULT_PROMPT_TEMPLATE, build_fallback_testcases, build_prompt
from .providers.http_llm import HttpLlmProvider
from .retrieval import RetrievalIndex
from .scanner import scan_laravel_routes

DEFAULT_PROVIDER = "ollama"
DEFAULT_MODEL = "qwen2.5-coder:7b"


def main() -> None:
    parser = argparse.ArgumentParser(prog="rag-testgen")
    subparsers = parser.add_subparsers(dest="command", required=True)

    scan_parser = subparsers.add_parser("scan", help="Scan Laravel routes from a source directory.")
    scan_parser.add_argument("--src", required=True, help="Path to Laravel source root.")
    scan_parser.add_argument("--pretty", action="store_true", help="Pretty-print JSON output.")

    generate_parser = subparsers.add_parser("generate", help="Generate a testcase collection.")
    generate_parser.add_argument("--src", required=True, help="Path to Laravel source root.")
    generate_parser.add_argument("--out", required=True, help="Path to write the JSON collection.")
    generate_parser.add_argument("--provider", choices=["ollama", "openai"], default=DEFAULT_PROVIDER)
    generate_parser.add_argument("--model", default=DEFAULT_MODEL)
    generate_parser.add_argument("--frontend-src", help="Optional frontend source root for API usage context.")
    generate_parser.add_argument("--env-file", help="Optional explicit env file for database settings.")
    generate_parser.add_argument("--prompt-file", help="Optional custom prompt template file.")
    generate_parser.add_argument("--limit", type=int, help="Generate only the first N routes.")
    generate_parser.add_argument("--dry-run", action="store_true", help="Skip model calls and use fallback testcases.")

    args = parser.parse_args()

    if args.command == "scan":
        routes = scan_laravel_routes(args.src)
        data = [route.to_dict() for route in routes]
        print(json.dumps(data, indent=2 if args.pretty else None, ensure_ascii=False))
        return

    routes = scan_laravel_routes(args.src)
    if args.limit:
        routes = routes[: args.limit]

    prompt_template = _load_prompt_template(args.prompt_file)
    provider = None if args.dry_run else HttpLlmProvider(args.provider, args.model)
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
        payload, raw_output = _generate_for_route(route, contexts, prompt_template, provider)
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


def _generate_for_route(route, contexts, prompt_template, provider):
    if provider is None:
        return build_fallback_testcases(route, contexts), None

    prompt = build_prompt(route, contexts, prompt_template)
    raw_output = provider.generate(prompt)
    payload = _extract_json(raw_output)
    payload.setdefault("api_id", f"{route.method.lower()}_{route.path.strip('/').replace('/', '_')}")
    payload.setdefault("endpoint", route.path)
    payload.setdefault("method", route.method)
    payload.setdefault("testcases", [])
    return payload, raw_output


def _load_prompt_template(prompt_file: str | None) -> str:
    if not prompt_file:
        return DEFAULT_PROMPT_TEMPLATE
    return Path(prompt_file).read_text(encoding="utf-8")


def _extract_json(raw_output: str) -> dict:
    text = raw_output.strip()
    if text.startswith("```"):
        lines = text.splitlines()
        text = "\n".join(line for line in lines if not line.startswith("```"))
    return json.loads(text)


if __name__ == "__main__":
    main()

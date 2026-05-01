from __future__ import annotations

import json
import os

from .models import RetrievedContext, RouteInfo

DEFAULT_PROMPT_TEMPLATE = """You are an expert QA engineer.

Given the API metadata and retrieved source code context below, generate a JSON object only.

Requirements:
- Focus on HTTP/API test cases.
- Include positive, negative, validation, authorization, and edge cases when applicable.
- Keep request payloads realistic based on the code context.
- Return valid JSON only using this shape:
{
  "api_id": "string",
  "endpoint": "string",
  "method": "GET|POST|PUT|PATCH|DELETE",
  "testcases": [
    {
      "name": "string",
      "description": "string",
      "request": {
        "headers": {},
        "query": {},
        "path_params": {},
        "body": {}
      },
      "expected": {
        "status": 200,
        "body_contains": [],
        "notes": "string"
      }
    }
  ]
}

API metadata:
__API_JSON__

Retrieved context:
__CONTEXT_JSON__
"""

DEFAULT_SINGLE_TESTCASE_PROMPT_TEMPLATE = """You are an expert QA engineer.

Generate exactly ONE complete HTTP/API testcase as a single JSON object (not an array).

Scenario to cover:
- Title: __SCENARIO_TITLE__
- Hints (skeleton from static analysis): __SCENARIO_JSON__

Requirements:
- The testcase must be self-contained: realistic headers, query, path_params, body as applicable.
- Align expected status and assertions with the scenario (positive, 401, 422, etc.).
- Return valid JSON only using this shape:
{
  "name": "string",
  "description": "string",
  "request": {
    "headers": {},
    "query": {},
    "path_params": {},
    "body": {}
  },
  "expected": {
    "status": 200,
    "body_contains": [],
    "notes": "string"
  }
}

API metadata:
__API_JSON__

Retrieved context:
__CONTEXT_JSON__
"""


def truncate_contexts(contexts: list[RetrievedContext], max_chars: int | None = None) -> list[RetrievedContext]:
    """Keep highest-scoring snippets until max_chars total snippet length."""
    if max_chars is None:
        max_chars = int(os.environ.get("RAG_TESTGEN_MAX_CONTEXT_CHARS", "12000"))
    if max_chars <= 0:
        return list(contexts)
    ordered = sorted(contexts, key=lambda c: c.score, reverse=True)
    out: list[RetrievedContext] = []
    used = 0
    for item in ordered:
        chunk = len(item.snippet) + len(item.file_path) + 32
        if used + chunk > max_chars and out:
            break
        out.append(item)
        used += chunk
    return out or ordered[:1]


def build_prompt(
    route: RouteInfo,
    contexts: list[RetrievedContext],
    prompt_template: str = DEFAULT_PROMPT_TEMPLATE,
    max_context_chars: int | None = None,
    compact_route: bool = False,
) -> str:
    route_payload = route.to_dict()
    if compact_route:
        route_payload.pop("source_snippet", None)
    api_json = json.dumps(route_payload, indent=2, ensure_ascii=False)
    trimmed = truncate_contexts(contexts, max_context_chars)
    context_json = json.dumps([item.to_dict() for item in trimmed], indent=2, ensure_ascii=False)
    return (
        prompt_template.replace("__API_JSON__", api_json).replace("__CONTEXT_JSON__", context_json)
    )


def build_single_testcase_prompt(
    route: RouteInfo,
    contexts: list[RetrievedContext],
    scenario: dict,
    prompt_template: str | None = None,
    max_context_chars: int | None = None,
) -> str:
    """One LLM call = one testcase. `scenario` is typically one entry from build_fallback_testcases()."""
    template = prompt_template or DEFAULT_SINGLE_TESTCASE_PROMPT_TEMPLATE
    if "__SCENARIO_TITLE__" not in template:
        template = DEFAULT_SINGLE_TESTCASE_PROMPT_TEMPLATE
    api_json = json.dumps(route.to_dict(), indent=2, ensure_ascii=False)
    trimmed = truncate_contexts(contexts, max_context_chars)
    context_json = json.dumps([item.to_dict() for item in trimmed], indent=2, ensure_ascii=False)
    title = str(scenario.get("name") or "Testcase")
    scenario_json = json.dumps(scenario, indent=2, ensure_ascii=False)
    return (
        template.replace("__API_JSON__", api_json)
        .replace("__CONTEXT_JSON__", context_json)
        .replace("__SCENARIO_TITLE__", title)
        .replace("__SCENARIO_JSON__", scenario_json)
    )


def build_fallback_testcases(route: RouteInfo, contexts: list[RetrievedContext]) -> dict:
    auth_required = any(token.startswith("auth:") for token in route.middleware)
    path_params = {
        token.strip("{}"): "sample-value"
        for token in route.path.split("/")
        if token.startswith("{") and token.endswith("}")
    }

    testcases = [
        {
            "name": f"{route.method} {route.path} returns success",
            "description": "Happy path generated without an external model.",
            "request": {
                "headers": {"Accept": "application/json"},
                "query": {},
                "path_params": path_params,
                "body": {},
            },
            "expected": {
                "status": 200 if route.method == "GET" else 201,
                "body_contains": [],
                "notes": "Update with concrete assertions after connecting an LLM.",
            },
        }
    ]

    if auth_required:
        testcases.append(
            {
                "name": f"{route.method} {route.path} rejects unauthenticated requests",
                "description": "Guard middleware suggests authentication is required.",
                "request": {
                    "headers": {"Accept": "application/json"},
                    "query": {},
                    "path_params": path_params,
                    "body": {},
                },
                "expected": {
                    "status": 401,
                    "body_contains": [],
                    "notes": "Verify exact framework response shape.",
                },
            }
        )

    if any(item for item in contexts if "validation" in item.reason.lower() or "request" in item.file_path.lower()):
        testcases.append(
            {
                "name": f"{route.method} {route.path} validates invalid payload",
                "description": "Generated from retrieved request or validation-related context.",
                "request": {
                    "headers": {"Accept": "application/json"},
                    "query": {},
                    "path_params": path_params,
                    "body": {},
                },
                "expected": {
                    "status": 422,
                    "body_contains": [],
                    "notes": "Replace with concrete invalid fields after refining retrieval.",
                },
            }
        )

    return {
        "api_id": _make_api_id(route),
        "endpoint": route.path,
        "method": route.method,
        "testcases": testcases,
    }


def _make_api_id(route: RouteInfo) -> str:
    normalized = route.path.strip("/").replace("/", "_").replace("{", "").replace("}", "")
    return f"{route.method.lower()}_{normalized or 'root'}"

from __future__ import annotations

import json

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


def build_prompt(
    route: RouteInfo,
    contexts: list[RetrievedContext],
    prompt_template: str = DEFAULT_PROMPT_TEMPLATE,
) -> str:
    api_json = json.dumps(route.to_dict(), indent=2, ensure_ascii=False)
    context_json = json.dumps([item.to_dict() for item in contexts], indent=2, ensure_ascii=False)
    return (
        prompt_template.replace("__API_JSON__", api_json).replace("__CONTEXT_JSON__", context_json)
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

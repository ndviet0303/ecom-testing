from __future__ import annotations

from dataclasses import asdict, dataclass, field
from typing import Any


@dataclass(slots=True)
class RouteInfo:
    method: str
    path: str
    controller: str | None = None
    action: str | None = None
    middleware: list[str] = field(default_factory=list)
    route_name: str | None = None
    source_file: str | None = None
    source_line: int | None = None
    source_snippet: str | None = None
    controller_file: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(slots=True)
class RetrievedContext:
    file_path: str
    reason: str
    score: int
    snippet: str

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(slots=True)
class GeneratedApiTestcases:
    api_id: str
    endpoint: str
    method: str
    testcases: list[dict[str, Any]]
    retrieved_context: list[dict[str, Any]] = field(default_factory=list)
    raw_model_output: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(slots=True)
class TestCollection:
    project: str
    source_root: str
    generated_at: str
    prompt_template: str
    apis: list[GeneratedApiTestcases]

    def to_dict(self) -> dict[str, Any]:
        return {
            "project": self.project,
            "source_root": self.source_root,
            "generated_at": self.generated_at,
            "prompt_template": self.prompt_template,
            "apis": [api.to_dict() for api in self.apis],
        }

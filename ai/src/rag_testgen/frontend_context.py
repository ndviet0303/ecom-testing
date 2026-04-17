from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import re

from .models import RetrievedContext, RouteInfo


@dataclass(slots=True)
class _FrontendFile:
    path: Path
    text: str
    lowered_text: str


class FrontendContextIndex:
    def __init__(self, frontend_root: str | Path) -> None:
        self.frontend_root = Path(frontend_root).resolve()
        self.files = self._load_files()

    def retrieve_context(self, route: RouteInfo, limit: int = 3) -> list[RetrievedContext]:
        normalized_path = route.path
        api_path = f"/api{normalized_path}"
        route_tokens = _route_tokens(route)
        candidates: list[RetrievedContext] = []

        for frontend_file in self.files:
            score = 0
            reasons: list[str] = []

            if api_path in frontend_file.text:
                score += 80
                reasons.append("frontend api path match")
            elif _matches_dynamic_endpoint(frontend_file.text, api_path):
                score += 60
                reasons.append("frontend dynamic api match")

            for token in route_tokens:
                if token in frontend_file.lowered_text:
                    score += 6
                    reasons.append(f"frontend keyword:{token}")

            if score <= 0:
                continue

            candidates.append(
                RetrievedContext(
                    file_path=str(frontend_file.path),
                    reason=", ".join(dict.fromkeys(reasons)),
                    score=score,
                    snippet=_extract_snippet(frontend_file.text, api_path, route_tokens),
                )
            )

        candidates.sort(key=lambda item: item.score, reverse=True)
        return candidates[:limit]

    def _load_files(self) -> list[_FrontendFile]:
        files: list[_FrontendFile] = []
        for pattern in ("*.js", "*.ts", "*.vue"):
            for file_path in self.frontend_root.rglob(pattern):
                if _should_skip_file(file_path, self.frontend_root):
                    continue
                text = file_path.read_text(encoding="utf-8", errors="ignore")
                files.append(_FrontendFile(path=file_path, text=text, lowered_text=text.lower()))
        return files


def _route_tokens(route: RouteInfo) -> list[str]:
    parts = re.split(r"[/{}_-]", route.path.lower())
    tokens = [part for part in parts if part and len(part) > 1]
    if route.action:
        tokens.append(route.action.lower())
    if route.controller:
        tokens.extend(token.lower() for token in re.split(r"[\\/]", route.controller) if token)
    return list(dict.fromkeys(tokens))


def _matches_dynamic_endpoint(text: str, api_path: str) -> bool:
    normalized = re.sub(r"\{[^}]+\}", "", api_path)
    return normalized.rstrip("/") in text


def _extract_snippet(text: str, api_path: str, tokens: list[str]) -> str:
    index = text.find(api_path)
    if index < 0:
        dynamic_prefix = re.sub(r"\{[^}]+\}", "", api_path).rstrip("/")
        index = text.find(dynamic_prefix)

    if index < 0:
        lowered = text.lower()
        for token in tokens:
            index = lowered.find(token)
            if index >= 0:
                break

    if index < 0:
        return _truncate(text)

    start = max(0, index - 180)
    return _truncate(text[start : start + 1200])


def _truncate(text: str, limit: int = 1200) -> str:
    compact = text.strip()
    return compact[:limit] + ("..." if len(compact) > limit else "")


def _should_skip_file(file_path: Path, root: Path) -> bool:
    relative_parts = file_path.relative_to(root).parts
    skipped_roots = {"node_modules", "dist", ".git"}
    return bool(relative_parts and relative_parts[0] in skipped_roots)

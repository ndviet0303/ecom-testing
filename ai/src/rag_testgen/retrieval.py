from __future__ import annotations

from dataclasses import dataclass
import re
from pathlib import Path

from .models import RetrievedContext, RouteInfo


@dataclass(slots=True)
class _IndexedFile:
    path: Path
    text: str
    lowered_text: str
    stem: str


class RetrievalIndex:
    def __init__(self, src_root: str | Path) -> None:
        self.src_root = Path(src_root).resolve()
        self.files = self._load_files()
        self.files_by_path = {str(indexed_file.path): indexed_file for indexed_file in self.files}

    def retrieve_context(self, route: RouteInfo, limit: int = 4) -> list[RetrievedContext]:
        candidates: list[RetrievedContext] = []

        if route.controller_file:
            controller_path = Path(route.controller_file)
            controller_file = self._get_indexed_file(controller_path)
            if controller_file is not None:
                snippet = _extract_method_snippet(controller_file.text, route.action)
                candidates.append(
                    RetrievedContext(
                        file_path=str(controller_path),
                        reason="controller action",
                        score=100,
                        snippet=snippet,
                    )
                )

            controller_name = controller_path.stem if controller_file is not None else None
        else:
            controller_name = None

        keywords = _route_keywords(route)
        action_pattern = (
            re.compile(rf"function\s+{re.escape(route.action)}\s*\(") if route.action else None
        )

        for indexed_file in self.files:
            if route.controller_file and str(indexed_file.path) == route.controller_file:
                continue

            score = 0
            reasons: list[str] = []

            if action_pattern and action_pattern.search(indexed_file.text):
                score += 50
                reasons.append("action name match")

            if controller_name and controller_name in indexed_file.text:
                score += 20
                reasons.append("controller reference")

            for keyword in keywords:
                if keyword and keyword.lower() in indexed_file.lowered_text:
                    score += 5
                    reasons.append(f"keyword:{keyword}")

            if score <= 0:
                continue

            candidates.append(
                RetrievedContext(
                    file_path=str(indexed_file.path),
                    reason=", ".join(dict.fromkeys(reasons)),
                    score=score,
                    snippet=_first_relevant_snippet(indexed_file.text, keywords),
                )
            )

        candidates.sort(key=lambda item: item.score, reverse=True)
        return candidates[:limit]

    def _load_files(self) -> list[_IndexedFile]:
        files: list[_IndexedFile] = []
        for file_path in self.src_root.rglob("*.php"):
            if _should_skip_file(file_path, self.src_root):
                continue
            text = file_path.read_text(encoding="utf-8", errors="ignore")
            files.append(
                _IndexedFile(
                    path=file_path,
                    text=text,
                    lowered_text=text.lower(),
                    stem=file_path.stem,
                )
            )
        return files

    def _get_indexed_file(self, path: Path) -> _IndexedFile | None:
        return self.files_by_path.get(str(path))


def retrieve_context(route: RouteInfo, src_root: str | Path, limit: int = 4) -> list[RetrievedContext]:
    return RetrievalIndex(src_root).retrieve_context(route, limit=limit)


def _route_keywords(route: RouteInfo) -> list[str]:
    tokens = [route.method, route.path]
    if route.controller:
        tokens.extend(re.split(r"[\\/]", route.controller))
    if route.action:
        tokens.append(route.action)
    tokens.extend(part for part in re.split(r"[/{}_-]", route.path) if part)
    return [token for token in tokens if token]


def _extract_method_snippet(text: str, action: str | None) -> str:
    if not action:
        return _truncate(text)

    match = re.search(rf"function\s+{re.escape(action)}\s*\(", text)
    if not match:
        return _truncate(text)

    start = max(0, match.start() - 240)
    return _truncate(text[start : start + 1800])


def _first_relevant_snippet(text: str, keywords: list[str]) -> str:
    lowered = text.lower()
    for keyword in keywords:
        if not keyword:
            continue
        index = lowered.find(keyword.lower())
        if index >= 0:
            start = max(0, index - 160)
            return _truncate(text[start : start + 1200])
    return _truncate(text)


def _truncate(text: str, limit: int = 1200) -> str:
    compact = text.strip()
    return compact[:limit] + ("..." if len(compact) > limit else "")


def _should_skip_file(file_path: Path, src_root: Path) -> bool:
    relative_parts = file_path.relative_to(src_root).parts
    skipped_roots = {"vendor", "node_modules", ".git"}
    return bool(relative_parts and relative_parts[0] in skipped_roots)

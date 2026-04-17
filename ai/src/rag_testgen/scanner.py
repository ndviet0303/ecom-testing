from __future__ import annotations

import re
from pathlib import Path

from .models import RouteInfo

HTTP_METHODS = ("get", "post", "put", "patch", "delete")
RESOURCE_ACTIONS = {
    "index": "GET",
    "store": "POST",
    "show": "GET",
    "update": "PUT",
    "destroy": "DELETE",
}


def scan_laravel_routes(src_root: str | Path) -> list[RouteInfo]:
    src_root = Path(src_root).resolve()
    routes_dir = src_root / "routes"
    api_file = routes_dir / "api.php"
    if not api_file.exists():
        raise FileNotFoundError(f"Cannot find Laravel routes file: {api_file}")

    lines = api_file.read_text(encoding="utf-8").splitlines()
    imports = _collect_imports(lines)
    state = _RouteState()
    routes: list[RouteInfo] = []

    for line_no, raw_line in enumerate(lines, start=1):
        line = raw_line.strip()
        if not line:
            continue

        state.consume_group_open(line)

        resource_route = _parse_api_resource(line, imports, state, api_file, line_no)
        if resource_route:
            routes.extend(resource_route)

        route = _parse_direct_route(line, imports, state, api_file, line_no)
        if route:
            routes.append(route)

        state.consume_group_close(line)

    return routes


class _RouteState:
    def __init__(self) -> None:
        self.prefix_stack: list[str] = []
        self.middleware_stack: list[list[str]] = []
        self.group_frames: list[tuple[str | None, list[str]]] = []

    def consume_group_open(self, line: str) -> None:
        if "->group(function" not in line:
            return

        prefix = _extract_prefix(line)
        middleware = _extract_middleware_from_line(line)

        self.group_frames.append((prefix, middleware))
        if prefix:
            self.prefix_stack.append(prefix)
        if middleware:
            self.middleware_stack.append(middleware)

    def consume_group_close(self, line: str) -> None:
        if line != "});":
            return
        if not self.group_frames:
            return
        prefix, middleware = self.group_frames.pop()
        if prefix and self.prefix_stack:
            self.prefix_stack.pop()
        if middleware and self.middleware_stack:
            self.middleware_stack.pop()

    @property
    def prefix(self) -> str:
        return "/".join(part.strip("/") for part in self.prefix_stack if part)

    @property
    def middleware(self) -> list[str]:
        merged: list[str] = []
        for items in self.middleware_stack:
            merged.extend(items)
        return merged


def _collect_imports(lines: list[str]) -> dict[str, str]:
    imports: dict[str, str] = {}
    for line in lines:
        match = re.match(r"use\s+([^;]+);", line.strip())
        if not match:
            continue
        full_name = match.group(1)
        alias = full_name.split(" as ")[-1].split("\\")[-1]
        imports[alias] = full_name.split(" as ")[0]
    return imports


def _parse_direct_route(
    line: str,
    imports: dict[str, str],
    state: _RouteState,
    api_file: Path,
    line_no: int,
) -> RouteInfo | None:
    for method in HTTP_METHODS:
        pattern = rf"Route::{method}\('([^']+)'\s*,\s*(.+?)\);"
        match = re.search(pattern, line)
        if not match:
            continue

        route_path = _normalize_path(state.prefix, match.group(1))
        target = match.group(2)
        controller, action = _parse_target(target, imports)
        return RouteInfo(
            method=method.upper(),
            path=route_path,
            controller=controller,
            action=action,
            middleware=state.middleware + _extract_inline_middleware(line),
            route_name=_parse_route_name(line),
            source_file=str(api_file),
            source_line=line_no,
            source_snippet=line,
            controller_file=_resolve_controller_file(api_file.parent.parent, controller),
        )
    return None


def _parse_api_resource(
    line: str,
    imports: dict[str, str],
    state: _RouteState,
    api_file: Path,
    line_no: int,
) -> list[RouteInfo]:
    match = re.search(
        r"Route::apiResource\('([^']+)'\s*,\s*([A-Za-z0-9_\\:]+)\)->except\(\[([^\]]*)\]\);",
        line,
    )
    if not match:
        return []

    resource = match.group(1)
    controller_token = match.group(2)
    controller, _ = _parse_target(f"[{controller_token}::class, 'index']", imports)
    excluded = {
        token.strip().strip("'").strip('"')
        for token in match.group(3).split(",")
        if token.strip()
    }
    routes: list[RouteInfo] = []
    for action, method in RESOURCE_ACTIONS.items():
        if action in excluded:
            continue
        suffix = ""
        if action in {"show", "update", "destroy"}:
            param = resource[:-1] if resource.endswith("s") else "id"
            suffix = f"/{{{param}}}"
        routes.append(
            RouteInfo(
                method=method,
                path=_normalize_path(state.prefix, f"{resource}{suffix}"),
                controller=controller,
                action=action,
                middleware=state.middleware,
                source_file=str(api_file),
                source_line=line_no,
                source_snippet=line,
                controller_file=_resolve_controller_file(api_file.parent.parent, controller),
            )
        )
    return routes


def _parse_target(target: str, imports: dict[str, str]) -> tuple[str | None, str | None]:
    class_action = re.search(r"\[([A-Za-z0-9_]+)::class,\s*'([A-Za-z0-9_]+)'\]", target)
    if class_action:
        alias = class_action.group(1)
        return imports.get(alias, alias), class_action.group(2)

    invokable = re.search(r"([A-Za-z0-9_]+)::class", target)
    if invokable:
        alias = invokable.group(1)
        return imports.get(alias, alias), "__invoke"

    return None, None


def _parse_route_name(line: str) -> str | None:
    match = re.search(r"->name\('([^']+)'\)", line)
    return match.group(1) if match else None


def _extract_middleware(expr: str) -> list[str]:
    return re.findall(r"'([^']+)'", expr)


def _extract_prefix(line: str) -> str | None:
    match = re.search(r"(?:Route::|->)prefix\('([^']+)'\)", line)
    return match.group(1) if match else None


def _extract_middleware_from_line(line: str) -> list[str]:
    match = re.search(r"Route::middleware\((\[[^\)]*\]|'[^']+')\)", line)
    if not match:
        return []
    return _extract_middleware(match.group(1))


def _extract_inline_middleware(line: str) -> list[str]:
    matches = re.findall(r"->middleware\((\[[^\)]*\]|'[^']+')\)", line)
    merged: list[str] = []
    for match in matches:
        merged.extend(_extract_middleware(match))
    return merged


def _normalize_path(prefix: str, route_path: str) -> str:
    parts = [prefix.strip("/"), route_path.strip("/")]
    return "/" + "/".join(part for part in parts if part)


def _resolve_controller_file(src_root: Path, controller: str | None) -> str | None:
    if not controller or not controller.startswith("App\\Http\\Controllers\\"):
        return None
    relative = controller.replace("App\\Http\\Controllers\\", "").replace("\\", "/")
    candidate = src_root / "app" / "Http" / "Controllers" / f"{relative}.php"
    return str(candidate) if candidate.exists() else None

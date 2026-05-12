from __future__ import annotations

from dataclasses import dataclass, field
import re
import sqlite3
from pathlib import Path

from .models import RetrievedContext, RouteInfo

_SENSITIVE_KEYS = {"DB_PASSWORD", "DB_USERNAME"}


@dataclass(slots=True)
class ColumnSpec:
    name: str
    col_type: str
    nullable: bool = False
    unique: bool = False
    default: str | None = None
    enum_values: str | None = None
    unsigned: bool = False
    foreign_ref: str | None = None


@dataclass(slots=True)
class TableSchema:
    name: str
    columns: list[ColumnSpec] = field(default_factory=list)
    migration_files: set[str] = field(default_factory=set)
    foreign_keys: set[str] = field(default_factory=set)


class DatabaseContextIndex:
    def __init__(self, src_root: str | Path, env_file: str | None = None) -> None:
        self.src_root = Path(src_root).resolve()
        self.env_path = self._resolve_env_path(env_file)
        self.env_map = _parse_env_file(self.env_path) if self.env_path else {}
        self.schema_map, self.migration_texts = _build_schema_map(self.src_root)
        self.seeder_index = _build_seeder_index(self.src_root)

    def retrieve_context(self, route: RouteInfo, limit: int = 8) -> list[RetrievedContext]:
        contexts: list[RetrievedContext] = []
        contexts.extend(self._env_context())
        contexts.extend(self._database_connection_context())

        target_tables = _infer_target_tables(route, self.schema_map)
        contexts.extend(self._schema_context(route, target_tables))
        contexts.extend(self._validation_context(route, target_tables))
        contexts.extend(self._payload_blueprint_context(route, target_tables))
        contexts.extend(self._dependency_context(target_tables))
        contexts.extend(self._seeder_context(target_tables))

        contexts.sort(key=lambda item: item.score, reverse=True)
        return contexts[:limit]

    def _resolve_env_path(self, explicit: str | None) -> Path | None:
        if explicit:
            candidate = Path(explicit).expanduser().resolve()
            return candidate if candidate.exists() else None
        for name in (".env", ".env.testing", ".env.example"):
            candidate = self.src_root / name
            if candidate.exists():
                return candidate
        return None

    def _env_context(self) -> list[RetrievedContext]:
        if not self.env_path or not self.env_map:
            return []
        keys = ("DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME")
        redacted = {key: _redact_value(key, self.env_map.get(key, "")) for key in keys}
        snippet = "\n".join(f"{key}={value}" for key, value in redacted.items() if value)
        if not snippet:
            snippet = "Database configuration exists in env file, but DB_* values are missing."
        return [
            RetrievedContext(
                file_path=str(self.env_path),
                reason="database env configuration",
                score=95,
                snippet=snippet,
            )
        ]

    def _database_connection_context(self) -> list[RetrievedContext]:
        if not self.env_map:
            return []
        connection = self.env_map.get("DB_CONNECTION", "").lower()
        if connection != "sqlite":
            db_name = self.env_map.get("DB_DATABASE", "")
            return [
                RetrievedContext(
                    file_path=str(self.env_path) if self.env_path else str(self.src_root),
                    reason="database connection metadata",
                    score=70,
                    snippet=(
                        "Configured DB is non-sqlite. Runtime schema inspection is skipped. "
                        f"DB_CONNECTION={connection or 'unknown'} DB_DATABASE={db_name or 'unknown'}"
                    ),
                )
            ]

        sqlite_path = _resolve_sqlite_db_path(self.src_root, self.env_map.get("DB_DATABASE", ""))
        if not sqlite_path.exists():
            return [
                RetrievedContext(
                    file_path=str(sqlite_path),
                    reason="sqlite database not found",
                    score=65,
                    snippet="SQLite is configured but database file does not exist yet.",
                )
            ]

        try:
            with sqlite3.connect(str(sqlite_path)) as conn:
                rows = conn.execute(
                    "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name LIMIT 40"
                ).fetchall()
        except sqlite3.Error as exc:
            return [
                RetrievedContext(
                    file_path=str(sqlite_path),
                    reason="sqlite inspection failed",
                    score=60,
                    snippet=f"Could not inspect sqlite schema: {exc}",
                )
            ]

        table_names = [name for (name,) in rows if name]
        return [
            RetrievedContext(
                file_path=str(sqlite_path),
                reason="runtime sqlite schema",
                score=85,
                snippet="Detected tables: " + (", ".join(table_names) if table_names else "none"),
            )
        ]

    def _schema_context(self, route: RouteInfo, target_tables: list[str]) -> list[RetrievedContext]:
        if not self.schema_map:
            return []
        contexts: list[RetrievedContext] = []

        if target_tables:
            contexts.append(
                RetrievedContext(
                    file_path=str(self.src_root / "database" / "migrations"),
                    reason="route table mapping",
                    score=92,
                    snippet=(
                        f"Route {route.method} {route.path} maps to tables: "
                        + ", ".join(target_tables[:6])
                    ),
                )
            )

        for table in target_tables[:3]:
            schema = self.schema_map.get(table)
            if not schema:
                continue
            snippet = _render_table_constraints(schema)
            for migration_file in sorted(schema.migration_files):
                contexts.append(
                    RetrievedContext(
                        file_path=migration_file,
                        reason=f"table constraints:{table}",
                        score=88,
                        snippet=snippet,
                    )
                )
                text = self.migration_texts.get(migration_file, "")
                if text:
                    contexts.append(
                        RetrievedContext(
                            file_path=migration_file,
                            reason=f"schema snippet:{table}",
                            score=72,
                            snippet=_table_focused_schema_snippet(text, [table]),
                        )
                    )
                    break

        if not contexts and self.migration_texts:
            last_file = sorted(self.migration_texts)[-1]
            contexts.append(
                RetrievedContext(
                    file_path=last_file,
                    reason="migration schema source, fallback",
                    score=20,
                    snippet=_first_schema_snippet(self.migration_texts[last_file]),
                )
            )
        return contexts

    def _dependency_context(self, target_tables: list[str]) -> list[RetrievedContext]:
        if not target_tables:
            return []
        chain = _build_dependency_chain(target_tables, self.schema_map)
        if not chain:
            return []
        return [
            RetrievedContext(
                file_path=str(self.src_root / "database" / "migrations"),
                reason="data setup dependency plan",
                score=86,
                snippet="Create test data in order: " + " -> ".join(chain),
            )
        ]

    def _validation_context(self, route: RouteInfo, target_tables: list[str]) -> list[RetrievedContext]:
        controller_file = Path(route.controller_file) if route.controller_file else None
        if not controller_file or not controller_file.exists() or not route.action:
            return []

        text = controller_file.read_text(encoding="utf-8", errors="ignore")
        action_body = _extract_controller_action_body(text, route.action)
        if not action_body:
            return []

        rules = _extract_inline_validation_rules(action_body)
        if not rules:
            return []

        lines = ["Validation blueprint from controller:"]
        for field, rule_tokens in list(rules.items())[:18]:
            combined = ", ".join(rule_tokens)
            lines.append(f"- {field}: {combined}")

        # Enrich with DB constraints on matching fields.
        db_hints = _validation_db_alignment(rules, target_tables, self.schema_map)
        if db_hints:
            lines.append("DB alignment hints:")
            lines.extend(f"- {hint}" for hint in db_hints[:12])

        return [
            RetrievedContext(
                file_path=str(controller_file),
                reason="request validation blueprint",
                score=90,
                snippet="\n".join(lines),
            )
        ]

    def _seeder_context(self, target_tables: list[str]) -> list[RetrievedContext]:
        contexts: list[RetrievedContext] = []
        if not self.seeder_index:
            return contexts

        for table in target_tables[:4]:
            for seeder_file in self.seeder_index.get(table, [])[:1]:
                text = seeder_file.read_text(encoding="utf-8", errors="ignore")
                contexts.append(
                    RetrievedContext(
                        file_path=str(seeder_file),
                        reason=f"seeder data source, table:{table}",
                        score=84,
                        snippet=_table_focused_seeder_snippet(text, [table]),
                    )
                )

        if contexts:
            return contexts

        for files in self.seeder_index.values():
            for seeder_file in files:
                if seeder_file.name == "DatabaseSeeder.php":
                    text = seeder_file.read_text(encoding="utf-8", errors="ignore")
                    return [
                        RetrievedContext(
                            file_path=str(seeder_file),
                            reason="seeder data source, fallback",
                            score=40,
                            snippet=_first_insert_snippet(text),
                        )
                    ]
        return contexts

    def _payload_blueprint_context(
        self, route: RouteInfo, target_tables: list[str]
    ) -> list[RetrievedContext]:
        controller_file = Path(route.controller_file) if route.controller_file else None
        if not controller_file or not controller_file.exists() or not route.action:
            return []

        text = controller_file.read_text(encoding="utf-8", errors="ignore")
        action_body = _extract_controller_action_body(text, route.action)
        if not action_body:
            return []
        rules = _extract_inline_validation_rules(action_body)
        if not rules:
            return []

        primary_table = target_tables[0] if target_tables else ""
        schema = self.schema_map.get(primary_table) if primary_table else None
        schema_by_field = {col.name: col for col in schema.columns} if schema else {}

        valid_payload: dict[str, str] = {}
        invalid_cases: list[str] = []
        for field, rule_tokens in rules.items():
            col = schema_by_field.get(field)
            valid_payload[field] = _suggest_valid_value(field, rule_tokens, col)
            invalid_cases.extend(_suggest_invalid_cases(field, rule_tokens, col))

        lines = ["Payload blueprint:"]
        lines.append(f"- endpoint: {route.method} {route.path}")
        if primary_table:
            lines.append(f"- primary_table: {primary_table}")
        lines.append("- valid_body:")
        for key, value in valid_payload.items():
            lines.append(f"  - {key}: {value}")
        if invalid_cases:
            lines.append("- invalid_body_cases:")
            for case in invalid_cases[:10]:
                lines.append(f"  - {case}")

        preconditions = _build_precondition_hints(schema)
        if preconditions:
            lines.append("- preconditions:")
            for item in preconditions[:6]:
                lines.append(f"  - {item}")

        return [
            RetrievedContext(
                file_path=str(controller_file),
                reason="payload blueprint",
                score=91,
                snippet="\n".join(lines),
            )
        ]


def _parse_env_file(env_path: Path) -> dict[str, str]:
    result: dict[str, str] = {}
    for raw_line in env_path.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        result[key.strip()] = value.strip().strip("'").strip('"')
    return result


def _redact_value(key: str, value: str) -> str:
    if key in _SENSITIVE_KEYS and value:
        return "***"
    return value


def _resolve_sqlite_db_path(src_root: Path, db_database: str) -> Path:
    if db_database in ("", ":memory:"):
        return src_root / "database" / "database.sqlite"
    path = Path(db_database)
    if path.is_absolute():
        return path
    return (src_root / path).resolve()


def _route_tokens(route: RouteInfo) -> list[str]:
    base = [route.method.lower(), route.path.lower()]
    if route.action:
        base.append(route.action.lower())
    if route.controller:
        base.extend(part.lower() for part in re.split(r"[\\/]", route.controller) if part)
    parts: list[str] = []
    for item in base:
        parts.extend(token for token in re.split(r"[/{}_.-]", item) if token and len(token) > 1)
    return list(dict.fromkeys(parts))


def _domain_terms(route: RouteInfo) -> list[str]:
    terms = _route_tokens(route)
    action = (route.action or "").lower()
    path = route.path.lower()
    controller = (route.controller or "").lower()
    auth_markers = ("auth", "login", "register", "logout", "token")
    if any(marker in path or marker in action or marker in controller for marker in auth_markers):
        terms.extend(["user", "users", "personal_access_token", "personal_access_tokens"])
    expanded: list[str] = []
    for term in terms:
        expanded.append(term)
        if term.endswith("s") and len(term) > 3:
            expanded.append(term[:-1])
        else:
            expanded.append(f"{term}s")
    return list(dict.fromkeys(expanded))


def _infer_target_tables(route: RouteInfo, schema_map: dict[str, TableSchema]) -> list[str]:
    if not schema_map:
        return []
    terms = _domain_terms(route)
    scored: list[tuple[int, str]] = []
    for table, schema in schema_map.items():
        score = 0
        singular = _to_singular_model_guess(table)
        for term in terms:
            if term == table or term == singular:
                score += 18
            elif term in table:
                score += 8
        for col in schema.columns:
            for term in terms:
                if term and term in col.name.lower():
                    score += 2
        if score > 0:
            scored.append((score, table))
    scored.sort(key=lambda item: item[0], reverse=True)
    return [table for _, table in scored[:4]]


def _build_schema_map(src_root: Path) -> tuple[dict[str, TableSchema], dict[str, str]]:
    migrations_dir = src_root / "database" / "migrations"
    if not migrations_dir.exists():
        return {}, {}
    schema_map: dict[str, TableSchema] = {}
    migration_texts: dict[str, str] = {}
    for migration_file in sorted(migrations_dir.glob("*.php")):
        text = migration_file.read_text(encoding="utf-8", errors="ignore")
        migration_texts[str(migration_file)] = text
        for table in _extract_migration_tables(text):
            schema = schema_map.setdefault(table, TableSchema(name=table))
            schema.migration_files.add(str(migration_file))
            body = _extract_schema_callback_body(text, table)
            if not body:
                continue
            for raw_line in body.splitlines():
                line = raw_line.strip()
                if "$table->" not in line:
                    continue
                col = _parse_column_spec(line)
                if col:
                    schema.columns.append(col)
                    if col.foreign_ref:
                        schema.foreign_keys.add(col.foreign_ref)
    for table_name, schema in schema_map.items():
        dedup: dict[str, ColumnSpec] = {col.name: col for col in schema.columns}
        schema.columns = list(dedup.values())
        schema_map[table_name] = schema
    return schema_map, migration_texts


def _build_seeder_index(src_root: Path) -> dict[str, list[Path]]:
    seeders_dir = src_root / "database" / "seeders"
    if not seeders_dir.exists():
        return {}
    index: dict[str, list[Path]] = {}
    for seeder_file in sorted(seeders_dir.glob("*.php")):
        text = seeder_file.read_text(encoding="utf-8", errors="ignore")
        for table in _extract_seeder_tables(text):
            index.setdefault(table, []).append(seeder_file)
    return index


def _first_schema_snippet(text: str) -> str:
    patterns = (r"Schema::create\([^\n]+\)", r"\$table->[a-zA-Z_]+\([^\n]+\)")
    for pattern in patterns:
        match = re.search(pattern, text)
        if match:
            start = max(0, match.start() - 160)
            return _truncate(text[start : start + 1200])
    return _truncate(text)


def _table_focused_schema_snippet(text: str, matched_tables: list[str]) -> str:
    for table in matched_tables:
        pattern = rf"Schema::(?:create|table)\('{re.escape(table)}'"
        match = re.search(pattern, text)
        if match:
            start = max(0, match.start() - 120)
            return _truncate(text[start : start + 1200])
    return _first_schema_snippet(text)


def _first_insert_snippet(text: str) -> str:
    patterns = (r"->create\([^\n]+\)", r"->insert\([^\n]+\)", r"::factory\([^\n]+\)")
    for pattern in patterns:
        match = re.search(pattern, text)
        if match:
            start = max(0, match.start() - 160)
            return _truncate(text[start : start + 1200])
    return _truncate(text)


def _table_focused_seeder_snippet(text: str, matched_tables: list[str]) -> str:
    for table in matched_tables:
        patterns = (
            rf"DB::table\('{re.escape(table)}'\)",
            rf"{re.escape(_to_singular_model_guess(table).capitalize())}::",
        )
        for pattern in patterns:
            match = re.search(pattern, text)
            if match:
                start = max(0, match.start() - 120)
                return _truncate(text[start : start + 1200])
    return _first_insert_snippet(text)


def _extract_migration_tables(text: str) -> list[str]:
    tables = re.findall(r"Schema::(?:create|table)\('([^']+)'", text)
    return list(dict.fromkeys(tables))


def _extract_seeder_tables(text: str) -> list[str]:
    tables = re.findall(r"DB::table\('([^']+)'\)", text)
    model_calls = re.findall(r"([A-Z][A-Za-z0-9_]*)::(?:query|factory|create|updateOrCreate)\(", text)
    guessed_from_models = [_model_to_table_name(model) for model in model_calls]
    merged = [*tables, *guessed_from_models]
    return [item for item in dict.fromkeys(merged) if item]


def _model_to_table_name(model: str) -> str:
    snake = re.sub(r"(?<!^)(?=[A-Z])", "_", model).lower()
    if snake.endswith("s"):
        return snake
    return f"{snake}s"


def _to_singular_model_guess(table: str) -> str:
    if table.endswith("ies"):
        return table[:-3] + "y"
    if table.endswith("s") and len(table) > 1:
        return table[:-1]
    return table


def _truncate(text: str, limit: int = 1200) -> str:
    compact = text.strip()
    return compact[:limit] + ("..." if len(compact) > limit else "")


def _render_table_constraints(schema: TableSchema) -> str:
    if not schema.columns:
        return f"Table constraints summary:\n- {schema.name}: no parsed columns"
    lines = [f"Table constraints summary for `{schema.name}`:"]
    for col in schema.columns[:18]:
        traits = [f"type={col.col_type}", "nullable" if col.nullable else "required"]
        if col.unique:
            traits.append("unique")
        if col.default:
            traits.append(f"default={col.default}")
        if col.enum_values:
            traits.append(f"enum={col.enum_values}")
        if col.unsigned:
            traits.append("unsigned")
        if col.foreign_ref:
            traits.append(f"references={col.foreign_ref}")
        lines.append(f"- {col.name}: " + ", ".join(traits))
    return "\n".join(lines)


def _extract_schema_callback_body(text: str, table: str) -> str:
    pattern = rf"Schema::(?:create|table)\('{re.escape(table)}',\s*function\s*\([^\)]*\)\s*\{{"
    match = re.search(pattern, text)
    if not match:
        return ""
    start = match.end()
    depth = 1
    index = start
    while index < len(text):
        char = text[index]
        if char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return text[start:index]
        index += 1
    return ""


def _parse_column_spec(line: str) -> ColumnSpec | None:
    chain = line.rstrip(";")
    method_match = re.search(r"\$table->([a-zA-Z_][a-zA-Z0-9_]*)\((.*)\)", chain)
    if not method_match:
        return None

    col_type = method_match.group(1)
    args = method_match.group(2)
    if not args:
        return None

    name_match = re.match(r"\s*'([^']+)'", args)
    if name_match:
        col_name = name_match.group(1)
    elif col_type == "foreignIdFor":
        model_match = re.search(r"([A-Z][A-Za-z0-9_]*)::class", args)
        if not model_match:
            return None
        col_name = f"{_model_to_table_name(model_match.group(1))[:-1]}_id"
    elif col_type in {"timestamps", "softDeletes", "id"}:
        return None
    else:
        return None

    default = None
    if "->default(" in chain:
        default_match = re.search(r"->default\(([^)]+)\)", chain)
        if default_match:
            default = default_match.group(1).strip()

    enum_values = None
    if col_type == "enum":
        enum_match = re.search(r"enum\([^,]+,\s*([^)]+)\)", chain)
        if enum_match:
            enum_values = enum_match.group(1).strip()

    foreign_ref = None
    constrained_match = re.search(r"->constrained\('([^']+)'\)", chain)
    if constrained_match:
        foreign_ref = constrained_match.group(1)
    elif "->constrained()" in chain and col_name.endswith("_id"):
        foreign_ref = f"{col_name[:-3]}s"
    elif col_type == "foreignIdFor":
        model_match = re.search(r"([A-Z][A-Za-z0-9_]*)::class", args)
        if model_match:
            foreign_ref = _model_to_table_name(model_match.group(1))

    return ColumnSpec(
        name=col_name,
        col_type=col_type,
        nullable="->nullable()" in chain,
        unique="->unique(" in chain or "->unique()" in chain,
        default=default,
        enum_values=enum_values,
        unsigned="->unsigned" in chain,
        foreign_ref=foreign_ref,
    )


def _build_dependency_chain(target_tables: list[str], schema_map: dict[str, TableSchema]) -> list[str]:
    ordered: list[str] = []
    visiting: set[str] = set()
    visited: set[str] = set()

    def dfs(table: str) -> None:
        if table in visited or table in visiting:
            return
        visiting.add(table)
        schema = schema_map.get(table)
        if schema:
            for parent in sorted(schema.foreign_keys):
                if parent in schema_map:
                    dfs(parent)
        visiting.remove(table)
        visited.add(table)
        ordered.append(table)

    for table in target_tables:
        dfs(table)
    return ordered


def _extract_controller_action_body(controller_text: str, action: str) -> str:
    pattern = rf"function\s+{re.escape(action)}\s*\([^\)]*\)\s*(?::[^\{{]+)?\{{"
    match = re.search(pattern, controller_text)
    if not match:
        return ""
    start = match.end()
    depth = 1
    index = start
    while index < len(controller_text):
        char = controller_text[index]
        if char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return controller_text[start:index]
        index += 1
    return ""


def _extract_inline_validation_rules(action_body: str) -> dict[str, list[str]]:
    match = re.search(r"->validate\(\s*\[(.*?)\]\s*\)", action_body, re.S)
    if not match:
        return {}
    rules_block = match.group(1)
    result: dict[str, list[str]] = {}
    # Rule style: 'email' => ['required', 'email', 'unique:users,email']
    list_style = re.findall(r"'([^']+)'\s*=>\s*\[([^\]]*)\]", rules_block, re.S)
    for field, value in list_style:
        tokens = re.findall(r"'([^']+)'", value)
        if tokens:
            result[field] = tokens
    # Rule style: 'email' => 'required|email|unique:users,email'
    pipe_style = re.findall(r"'([^']+)'\s*=>\s*'([^']+)'", rules_block)
    for field, value in pipe_style:
        if field in result:
            continue
        result[field] = [token.strip() for token in value.split("|") if token.strip()]
    return result


def _validation_db_alignment(
    rules: dict[str, list[str]],
    target_tables: list[str],
    schema_map: dict[str, TableSchema],
) -> list[str]:
    hints: list[str] = []
    tables = [schema_map[table] for table in target_tables if table in schema_map]
    if not tables:
        return hints
    for field, rule_tokens in rules.items():
        for table in tables:
            col = next((item for item in table.columns if item.name == field), None)
            if not col:
                continue
            if not col.nullable and "required" not in rule_tokens:
                hints.append(f"{table.name}.{field} is required at DB level.")
            if col.unique and not any(token.startswith("unique") for token in rule_tokens):
                hints.append(f"{table.name}.{field} is unique but validation lacks unique rule.")
            if col.enum_values and not any(token.startswith("in:") for token in rule_tokens):
                hints.append(f"{table.name}.{field} is enum; consider in:{col.enum_values}.")
            if col.foreign_ref and not any(token.startswith("exists:") for token in rule_tokens):
                hints.append(
                    f"{table.name}.{field} references {col.foreign_ref}; consider exists:{col.foreign_ref},id."
                )
    return list(dict.fromkeys(hints))


def _suggest_valid_value(field: str, rule_tokens: list[str], col: ColumnSpec | None) -> str:
    if any(token == "email" for token in rule_tokens):
        return "valid.user@example.com"
    if any(token.startswith("min:") for token in rule_tokens):
        min_token = next((token for token in rule_tokens if token.startswith("min:")), "min:8")
        min_len = int(min_token.split(":", 1)[1]) if min_token.split(":", 1)[1].isdigit() else 8
        return "x" * max(min_len, 8)
    if any(token == "boolean" for token in rule_tokens):
        return "true"
    if any(token == "integer" for token in rule_tokens) or (col and "int" in col.col_type.lower()):
        return "1"
    if any(token == "numeric" for token in rule_tokens):
        return "100"
    if any(token == "date" for token in rule_tokens):
        return "2026-01-01"
    enum_token = next((token for token in rule_tokens if token.startswith("in:")), None)
    if enum_token:
        values = [item.strip() for item in enum_token.split(":", 1)[1].split(",") if item.strip()]
        if values:
            return values[0]
    if col and col.enum_values:
        values = re.findall(r"'([^']+)'", col.enum_values)
        if values:
            return values[0]
    if "password" in field.lower():
        return "Password123!"
    return "sample-value"


def _suggest_invalid_cases(field: str, rule_tokens: list[str], col: ColumnSpec | None) -> list[str]:
    cases: list[str] = []
    if "required" in rule_tokens:
        cases.append(f"{field}: missing field")
    if "email" in rule_tokens:
        cases.append(f"{field}: invalid email format")
    min_token = next((token for token in rule_tokens if token.startswith("min:")), None)
    if min_token:
        min_len = min_token.split(":", 1)[1]
        cases.append(f"{field}: shorter than min:{min_len}")
    if any(token.startswith("unique:") for token in rule_tokens) or (col and col.unique):
        cases.append(f"{field}: duplicate existing value")
    if any(token.startswith("exists:") for token in rule_tokens) or (col and col.foreign_ref):
        cases.append(f"{field}: references non-existent related record")
    if any(token.startswith("in:") for token in rule_tokens) or (col and col.enum_values):
        cases.append(f"{field}: value outside allowed enum list")
    return cases


def _build_precondition_hints(schema: TableSchema | None) -> list[str]:
    if not schema:
        return []
    hints: list[str] = []
    for col in schema.columns:
        if col.foreign_ref:
            hints.append(f"Create {col.foreign_ref} record before setting {schema.name}.{col.name}")
        if col.unique:
            hints.append(f"Ensure {schema.name}.{col.name} is unique in test setup")
    return list(dict.fromkeys(hints))

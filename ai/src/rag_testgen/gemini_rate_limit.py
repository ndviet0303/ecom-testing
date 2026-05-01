from __future__ import annotations

import os
import threading
import time


def _env_int(name: str, default: int) -> int:
    raw = os.environ.get(name)
    if raw is None or raw.strip() == "":
        return default
    try:
        return int(raw)
    except ValueError:
        return default


class GeminiRateLimiter:
    """
    Sliding 60s window: max GEMINI_RPM requests and max GEMINI_TPM estimated tokens.
    Defaults match typical Gemini free-tier style caps (5 RPM, 250k TPM).
    """

    def __init__(self) -> None:
        self.rpm = max(1, _env_int("GEMINI_RPM", 5))
        self.tpm = max(1000, _env_int("GEMINI_TPM", 250_000))
        self._lock = threading.Lock()
        self._events: list[tuple[float, int]] = []

    def _prune(self, now: float) -> None:
        cutoff = now - 60.0
        self._events = [(t, tok) for t, tok in self._events if t >= cutoff]

    def acquire(self, estimated_tokens: int) -> None:
        est = max(1, estimated_tokens)
        while True:
            with self._lock:
                now = time.monotonic()
                self._prune(now)
                token_sum = sum(tok for _, tok in self._events)
                if len(self._events) < self.rpm and token_sum + est <= int(self.tpm * 0.92):
                    return
                if not self._events:
                    time.sleep(0.05)
                    continue
                oldest_t = self._events[0][0]
            sleep_for = max(0.05, oldest_t + 60.0 - time.monotonic() + 0.05)
            time.sleep(min(sleep_for, 5.0))

    def record(self, tokens: int) -> None:
        with self._lock:
            self._events.append((time.monotonic(), max(1, tokens)))


class RequestsPerMinuteLimiter:
    """Simple sliding-window limiter for APIs constrained by RPM only."""

    def __init__(self, rpm_env_key: str, default_rpm: int) -> None:
        self.rpm = max(1, _env_int(rpm_env_key, default_rpm))
        self._lock = threading.Lock()
        self._events: list[float] = []

    def _prune(self, now: float) -> None:
        cutoff = now - 60.0
        self._events = [t for t in self._events if t >= cutoff]

    def acquire(self) -> None:
        while True:
            with self._lock:
                now = time.monotonic()
                self._prune(now)
                if len(self._events) < self.rpm:
                    return
                oldest_t = self._events[0]
            sleep_for = max(0.05, oldest_t + 60.0 - time.monotonic() + 0.05)
            time.sleep(min(sleep_for, 5.0))

    def record(self) -> None:
        with self._lock:
            self._events.append(time.monotonic())


class GroqRateLimiter:
    """
    Sliding 60s window for Groq with both RPM and TPM caps.
    Defaults target llama-3.1-8b-instant common free limits.
    """

    def __init__(self) -> None:
        self.rpm = max(1, _env_int("GROQ_RPM", 30))
        self.tpm = max(500, _env_int("GROQ_TPM", 6000))
        self._lock = threading.Lock()
        self._events: list[tuple[float, int]] = []

    def _prune(self, now: float) -> None:
        cutoff = now - 60.0
        self._events = [(t, tok) for t, tok in self._events if t >= cutoff]

    def acquire(self, estimated_tokens: int) -> None:
        est = max(1, estimated_tokens)
        budget = int(self.tpm * 0.9)
        while True:
            with self._lock:
                now = time.monotonic()
                self._prune(now)
                token_sum = sum(tok for _, tok in self._events)
                # If one prompt alone exceeds budget, allow it only on a clean window.
                if est > budget and not self._events:
                    return
                # keep a small safety margin to avoid edge-window 429
                if len(self._events) < self.rpm and token_sum + est <= budget:
                    return
                if not self._events:
                    time.sleep(0.05)
                    continue
                oldest_t = self._events[0][0]
            sleep_for = max(0.05, oldest_t + 60.0 - time.monotonic() + 0.05)
            time.sleep(min(sleep_for, 5.0))

    def record(self, tokens: int) -> None:
        with self._lock:
            self._events.append((time.monotonic(), max(1, tokens)))


_gemini_limiter: GeminiRateLimiter | None = None
_gemini_limiter_lock = threading.Lock()
_groq_limiter: GroqRateLimiter | None = None
_groq_limiter_lock = threading.Lock()


def get_gemini_rate_limiter() -> GeminiRateLimiter:
    global _gemini_limiter
    with _gemini_limiter_lock:
        if _gemini_limiter is None:
            _gemini_limiter = GeminiRateLimiter()
        return _gemini_limiter


def get_groq_rate_limiter() -> GroqRateLimiter:
    global _groq_limiter
    with _groq_limiter_lock:
        if _groq_limiter is None:
            _groq_limiter = GroqRateLimiter()
        return _groq_limiter


def estimate_tokens(text: str) -> int:
    """Rough token count (English-heavy text ~4 chars/token)."""
    if not text:
        return 1
    return max(1, len(text) // 4)

from __future__ import annotations

import json
import os
import time
from urllib import request
from urllib.error import HTTPError

from ..gemini_rate_limit import estimate_tokens, get_gemini_rate_limiter, get_groq_rate_limiter


class HttpLlmProvider:
    def __init__(self, provider: str, model: str) -> None:
        self.provider = provider
        self.model = model
        self.timeout_seconds = int(os.environ.get("RAG_TESTGEN_HTTP_TIMEOUT", "600"))

    def generate(self, prompt: str) -> str:
        if self.provider == "ollama":
            return self._generate_ollama(prompt)
        if self.provider == "openai":
            return self._generate_openai_compatible(prompt)
        if self.provider == "gemini":
            return self._generate_gemini(prompt)
        if self.provider == "groq":
            return self._generate_groq(prompt)
        raise ValueError(f"Unsupported provider: {self.provider}")

    def _generate_ollama(self, prompt: str) -> str:
        base_url = os.environ.get("OLLAMA_BASE_URL", "http://localhost:11434").rstrip("/")
        payload = {
            "model": self.model,
            "prompt": prompt,
            "stream": False,
            "format": "json",
        }
        req = request.Request(
            f"{base_url}/api/generate",
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        with request.urlopen(req, timeout=self.timeout_seconds) as response:
            data = json.loads(response.read().decode("utf-8"))
        return data.get("response", "")

    def _generate_openai_compatible(self, prompt: str) -> str:
        base_url = os.environ.get("OPENAI_BASE_URL", "https://api.openai.com/v1").rstrip("/")
        api_key = os.environ.get("OPENAI_API_KEY")
        if not api_key:
            raise RuntimeError("OPENAI_API_KEY is required for the openai provider.")

        payload = {
            "model": self.model,
            "messages": [
                {"role": "system", "content": "Return only valid JSON."},
                {"role": "user", "content": prompt},
            ],
            "temperature": 0.2,
            "response_format": {"type": "json_object"},
        }
        req = request.Request(
            f"{base_url}/chat/completions",
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {api_key}",
                # Groq can reject default Python urllib UA with Cloudflare 1010.
                "User-Agent": os.environ.get("RAG_TESTGEN_USER_AGENT", "curl/8.7.1"),
                "Accept": "application/json",
            },
            method="POST",
        )
        with request.urlopen(req, timeout=self.timeout_seconds) as response:
            data = json.loads(response.read().decode("utf-8"))
        return data["choices"][0]["message"]["content"]

    def _generate_gemini(self, prompt: str) -> str:
        base_url = os.environ.get("GEMINI_BASE_URL", "https://generativelanguage.googleapis.com/v1beta").rstrip("/")
        api_key = os.environ.get("GEMINI_API_KEY")
        if not api_key:
            raise RuntimeError("GEMINI_API_KEY is required for the gemini provider.")
        model = self.model or "gemini-2.5-flash"

        limiter = get_gemini_rate_limiter()
        limiter.acquire(estimate_tokens(prompt))

        payload = {
            "contents": [
                {"role": "user", "parts": [{"text": f"Return only valid JSON.\n\n{prompt}"}]},
            ],
            "generationConfig": {
                "temperature": 0.2,
                "responseMimeType": "application/json",
            },
        }
        req = request.Request(
            f"{base_url}/models/{model}:generateContent",
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "X-goog-api-key": api_key,
            },
            method="POST",
        )
        with request.urlopen(req, timeout=self.timeout_seconds) as response:
            data = json.loads(response.read().decode("utf-8"))
        text = (
            data.get("candidates", [{}])[0]
            .get("content", {})
            .get("parts", [{}])[0]
            .get("text", "")
        )
        limiter.record(estimate_tokens(prompt) + estimate_tokens(text))
        return text

    def _generate_groq(self, prompt: str) -> str:
        base_url = os.environ.get("GROQ_BASE_URL", "https://api.groq.com/openai/v1").rstrip("/")
        api_key = os.environ.get("GROQ_API_KEY")
        if not api_key:
            raise RuntimeError("GROQ_API_KEY is required for the groq provider.")
        model = self.model or "llama-3.1-8b-instant"
        limiter = get_groq_rate_limiter()
        max_completion_tokens = max(128, int(os.environ.get("GROQ_MAX_COMPLETION_TOKENS", "400")))
        payload = {
            "model": model,
            "messages": [
                {"role": "system", "content": "Return only valid JSON."},
                {"role": "user", "content": prompt},
            ],
            "temperature": 0.2,
            "response_format": {"type": "json_object"},
            "max_completion_tokens": max_completion_tokens,
        }
        retry_count = max(0, int(os.environ.get("GROQ_RETRY_429", "2")))
        base_wait = max(1.0, float(os.environ.get("GROQ_RETRY_BASE_SECONDS", "8")))

        for attempt in range(retry_count + 1):
            planned_tokens = estimate_tokens(prompt) + max_completion_tokens
            limiter.acquire(planned_tokens)
            req = request.Request(
                f"{base_url}/chat/completions",
                data=json.dumps(payload).encode("utf-8"),
                headers={
                    "Content-Type": "application/json",
                    "Authorization": f"Bearer {api_key}",
                    # Avoid Cloudflare 1010 blocks on default urllib UA.
                    "User-Agent": os.environ.get("RAG_TESTGEN_USER_AGENT", "curl/8.7.1"),
                    "Accept": "application/json",
                },
                method="POST",
            )
            try:
                with request.urlopen(req, timeout=self.timeout_seconds) as response:
                    data = json.loads(response.read().decode("utf-8"))
                text = data["choices"][0]["message"]["content"]
                limiter.record(planned_tokens)
                return text
            except HTTPError as exc:
                if exc.code != 429 or attempt >= retry_count:
                    raise
                retry_after = exc.headers.get("Retry-After")
                if retry_after:
                    try:
                        wait_seconds = max(base_wait, float(retry_after))
                    except ValueError:
                        wait_seconds = base_wait * (attempt + 1)
                else:
                    wait_seconds = base_wait * (attempt + 1)
                time.sleep(wait_seconds)

        raise RuntimeError("Groq generation failed unexpectedly.")

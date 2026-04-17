from __future__ import annotations

import json
import os
from urllib import request


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
            },
            method="POST",
        )
        with request.urlopen(req, timeout=self.timeout_seconds) as response:
            data = json.loads(response.read().decode("utf-8"))
        return data["choices"][0]["message"]["content"]

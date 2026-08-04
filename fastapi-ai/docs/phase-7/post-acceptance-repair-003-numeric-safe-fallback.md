# Phase 7 Post-Acceptance Repair 003 — Numeric-Safe Explanation Fallback

## Problem

The private local `llama3:8b` model could return a structurally valid explanation that still contained a recalculated, rounded, converted, or otherwise unsupported numeric token. The strict grounding validator correctly rejected that output with `unsupported_numeric_value`. After the bounded retry, the API returned a safe `502` response and Laravel kept the original verified inference result visible.

## Repair

The guarded explanation service now uses a deterministic server-side fallback only when both bounded Ollama attempts fail specifically because of `unsupported_numeric_value`.

The fallback:

- discards the rejected model narrative completely;
- introduces no numeric token of its own;
- preserves the exact server-owned model and prototype limitations;
- includes only mandatory allowlisted fact references;
- remains role-aware and language-aware;
- keeps the verified numeric inference result as the authoritative value shown separately by Laravel;
- does not perform another inference or another Ollama request.

Malformed JSON, prompt injection, unsupported fact paths, root-cause claims, unsafe control instructions, timeouts, unavailable Ollama, and other rejection reasons remain rejected through the existing guarded boundary.

## Operational note

Restart the FastAPI/Uvicorn process after installation so the new Python module is loaded.

# Phase 7 post-acceptance repair 001

## Purpose

This repair improves live `llama3:8b` explanation reliability without weakening the guarded narrative checks.

The local model is still responsible only for the narrative fields. The service now restores two deterministic contract fields from the already validated request:

- required prototype and model limitations;
- mandatory verified fact references.

These values are server-owned metadata and no longer depend on the model copying long strings exactly. Unsupported numeric values, unsupported fact paths, root-cause claims, certainty claims, unsafe control instructions, malformed JSON, and invalid narrative shapes remain rejected.

## Security boundaries preserved

- Browser never connects directly to Ollama.
- Laravel remains the authorization and database boundary.
- FastAPI still has no database access.
- Raw model output and prompts are not logged.
- Only a safe final rejection code is logged and returned in structured error details.
- Numeric inference results remain authoritative and unchanged.
- No second inference is executed.

## Acceptance

Run the package verifier and then repeat one explanation from `/ai-insights`. A successful result must display the guarded narrative while preserving the exact numeric inference result.

# Phase 7 — Step 22C: Guarded Prompts and Grounding

## Scope

This step adds deterministic prompt construction and strict post-generation validation for the three supported explanation types:

- production forecast;
- production anomaly;
- maintenance risk.

It does **not** add an HTTP explanation endpoint and does not perform live Ollama generation. Step 22D will connect these components to the authenticated internal API.

## Prompt boundary

The guarded prompt is built only from the accepted Step 22B contract and allowlisted facts. It contains:

- a static security system prompt;
- role-specific communication guidance;
- explanation-type interpretation rules;
- the required fact references;
- the exact required limitations;
- a canonical JSON fact bundle;
- the strict `ExplanationNarrative` JSON schema.

Values inside the verified JSON block are treated as data, not instructions. Instruction-like source limitation text is rejected before prompt creation.

## Role-aware behavior

- **Production Supervisor:** concise operational language and human checks around the selected line and validated history.
- **Production Manager:** planning-oriented decision support without production commitments.
- **Maintenance Manager:** inspection and prioritization language without failure certainty or automatic action.
- **Administrator:** technical context and supplied model metadata without credentials, URLs, prompts, or internal configuration.

The Operator remains excluded by the Step 22B authorization matrix.

## Output guardrails

The parser accepts one bounded UTF-8 JSON object only. It rejects:

- Markdown fences, prefaces, trailing commentary, and duplicate JSON keys;
- unknown sections or invalid list sizes;
- fact paths outside the explanation-type allowlist;
- omitted mandatory result references;
- omitted prototype or model limitations;
- numeric values and percentages not present in the verified fact bundle;
- calculated percentages or newly derived values;
- invented root causes, certainty claims, claimed Sage/database access, and control commands;
- suggested actions that are not phrased as human-review checks.

The limitation list is bounded at 12 items so the output can preserve the contract's maximum 10 verified model limitations plus the mandatory prototype boundary.

## Security properties

- No database access.
- No browser-to-Ollama path.
- No prompt logging.
- No tokens, URLs, user identity, or unrestricted maps in the prompt contract.
- No calculation or correction of ML results by the LLM.
- No automatic production or maintenance action.
- `simulated_prototype` remains mandatory.

## Next step

Step 22D will add the authenticated internal explanation endpoint, bounded Ollama generation, safe retries/fallbacks, request IDs, rate limiting, and mocked failure-path tests.

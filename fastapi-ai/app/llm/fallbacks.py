from __future__ import annotations

from app.llm.policies import required_fact_keys, required_limitations
from app.schemas.explanation import (
    ExplanationContractRequest,
    ExplanationLanguage,
    ExplanationNarrative,
    ExplanationRole,
    ExplanationType,
)


def build_numeric_safe_fallback(
    request: ExplanationContractRequest,
) -> ExplanationNarrative:
    """Build a deterministic narrative after repeated numeric-grounding rejection.

    The rejected model text is discarded. This fallback intentionally introduces no
    numeric token of its own; the verified numeric result remains visible separately.
    """

    summary, observation = _type_text(request)
    human_check = _role_check(request)

    return ExplanationNarrative(
        summary=summary,
        observations=[observation],
        suggested_human_checks=[human_check],
        limitations=list(required_limitations(request)),
        referenced_fact_keys=sorted(required_fact_keys(request.facts.explanation_type)),
    )


def _type_text(
    request: ExplanationContractRequest,
) -> tuple[str, str]:
    language = request.language
    explanation_type = request.facts.explanation_type

    if language is ExplanationLanguage.FRENCH:
        return _french_type_text(explanation_type)

    return _english_type_text(explanation_type)


def _english_type_text(
    explanation_type: ExplanationType,
) -> tuple[str, str]:
    if explanation_type is ExplanationType.PRODUCTION_FORECAST:
        return (
            "The verified production forecast remains the authoritative result "
            "for the selected line.",
            "This guarded fallback adds no calculated, rounded, or inferred numeric value.",
        )

    if explanation_type is ExplanationType.PRODUCTION_ANOMALY:
        return (
            "The verified anomaly decision remains authoritative for the selected "
            "production record.",
            "This guarded fallback does not reinterpret the supplied score as a "
            "percentage or probability.",
        )

    return (
        "The verified maintenance-risk result remains authoritative for the selected machine.",
        "This guarded fallback adds no new failure estimate, downtime value, or certainty claim.",
    )


def _french_type_text(
    explanation_type: ExplanationType,
) -> tuple[str, str]:
    if explanation_type is ExplanationType.PRODUCTION_FORECAST:
        return (
            "La prévision de production vérifiée reste le résultat de référence "
            "pour la ligne sélectionnée.",
            "Ce repli sécurisé n'ajoute aucune valeur numérique calculée, arrondie ou déduite.",
        )

    if explanation_type is ExplanationType.PRODUCTION_ANOMALY:
        return (
            "La décision d'anomalie vérifiée reste la référence pour "
            "l'enregistrement de production sélectionné.",
            "Ce repli sécurisé ne transforme pas le score fourni en pourcentage ni en probabilité.",
        )

    return (
        "Le résultat vérifié du risque de maintenance reste la référence pour "
        "la machine sélectionnée.",
        "Ce repli sécurisé n'ajoute aucune nouvelle estimation de panne, de durée "
        "d'arrêt ou de certitude.",
    )


def _role_check(request: ExplanationContractRequest) -> str:
    if request.language is ExplanationLanguage.FRENCH:
        return _french_role_check(request.role)

    return _english_role_check(request.role)


def _english_role_check(role: ExplanationRole) -> str:
    if role is ExplanationRole.PRODUCTION_SUPERVISOR:
        return (
            "Review the validated production and downtime records for the selected "
            "line before taking action."
        )
    if role is ExplanationRole.PRODUCTION_MANAGER:
        return (
            "Compare the verified result with the approved production plan before "
            "making a human decision."
        )
    if role is ExplanationRole.MAINTENANCE_MANAGER:
        return (
            "Inspect the verified maintenance and downtime history for the selected "
            "machine before prioritization."
        )
    return "Review the supplied model metadata and request trace before technical follow-up."


def _french_role_check(role: ExplanationRole) -> str:
    if role is ExplanationRole.PRODUCTION_SUPERVISOR:
        return (
            "Examiner les enregistrements validés de production et d'arrêt de la "
            "ligne sélectionnée avant toute action humaine."
        )
    if role is ExplanationRole.PRODUCTION_MANAGER:
        return (
            "Comparer le résultat vérifié au plan de production approuvé avant "
            "toute décision humaine."
        )
    if role is ExplanationRole.MAINTENANCE_MANAGER:
        return (
            "Inspecter l'historique vérifié de maintenance et d'arrêt de la machine "
            "sélectionnée avant la priorisation."
        )
    return (
        "Vérifier les métadonnées du modèle et la trace de requête fournies avant "
        "tout suivi technique."
    )

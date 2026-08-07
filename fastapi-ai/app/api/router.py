from fastapi import APIRouter

from app.api.routes.analytics_contract import router as analytics_contract_router
from app.api.routes.explanations import router as explanations_router
from app.api.routes.health import router as health_router
from app.api.routes.inference import router as inference_router
from app.api.routes.metrics import router as metrics_router
from app.api.routes.model_metrics import router as model_metrics_router
from app.api.routes.ping import router as ping_router
from app.api.routes.version import router as version_router

api_router = APIRouter()
api_router.include_router(analytics_contract_router)
api_router.include_router(explanations_router)
api_router.include_router(health_router)
api_router.include_router(inference_router)
api_router.include_router(metrics_router)
api_router.include_router(model_metrics_router)
api_router.include_router(version_router)
api_router.include_router(ping_router)

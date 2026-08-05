# syntax=docker/dockerfile:1.7

ARG PYTHON_IMAGE=python:3.12.13-slim-bookworm

FROM ${PYTHON_IMAGE} AS wheels

ENV PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /build

COPY fastapi-ai/pyproject.toml ./

RUN python - <<'PY'
import pathlib
import tomllib

data = tomllib.loads(pathlib.Path("pyproject.toml").read_text(encoding="utf-8"))
dependencies = data["project"]["dependencies"]
pathlib.Path("requirements-runtime.txt").write_text(
    "\n".join(dependencies) + "\n",
    encoding="utf-8",
)
PY

RUN pip wheel \
    --wheel-dir /wheels \
    --requirement requirements-runtime.txt

FROM ${PYTHON_IMAGE} AS application

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PIP_NO_CACHE_DIR=1

RUN groupadd --gid 10001 smartfactory \
    && useradd \
        --uid 10001 \
        --gid smartfactory \
        --create-home \
        --home-dir /home/smartfactory \
        --shell /usr/sbin/nologin \
        smartfactory \
    && mkdir -p \
        /srv/fastapi \
        /var/lib/smartfactory-ai/datasets \
        /var/lib/smartfactory-ai/preprocessing \
        /var/lib/smartfactory-ai/features \
        /var/lib/smartfactory-ai/models \
    && chown -R smartfactory:smartfactory \
        /srv/fastapi \
        /var/lib/smartfactory-ai

COPY --from=wheels /wheels /wheels
RUN pip install /wheels/* \
    && rm -rf /wheels

WORKDIR /srv/fastapi

COPY --chown=smartfactory:smartfactory fastapi-ai/app ./app

USER 10001:10001

EXPOSE 8001

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8001/health/live', timeout=3).read()" || exit 1

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001"]

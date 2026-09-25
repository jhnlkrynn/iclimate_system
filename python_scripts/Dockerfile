# iClimate ML service (FastAPI) — serves rice-yield/weather predictions and
# live model-evaluation metrics, decoupled from the PHP web app.
#
# Build context must be the repository root (not python_scripts/), because
# this needs storage/models/*.pkl alongside the service code — see
# docker-compose.yml at the repo root, which sets `context: .` and
# `dockerfile: python_scripts/Dockerfile`.
#
#   docker build -f python_scripts/Dockerfile -t iclimate-ml-service .
#   docker run -p 8010:8010 iclimate-ml-service

FROM python:3.12-slim AS base

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /app

# Install dependencies first so this layer only rebuilds when
# requirements.txt actually changes, not on every code edit.
COPY python_scripts/requirements.txt python_scripts/requirements.txt
RUN pip install -r python_scripts/requirements.txt

# Application code.
COPY python_scripts/ python_scripts/

# Pre-trained model artifacts. If storage/models is later mounted as a
# volume in docker-compose, Docker seeds that volume from these files on
# first run, so the container works out of the box while still allowing
# retrained models to be dropped in without rebuilding the image.
COPY storage/models/ storage/models/

# Training-data export directory — empty until `php artisan
# iclimate:export-yield-data` output is mounted/copied in. /metrics
# reports `ready: false` gracefully until then; see evaluation_service.py.
RUN mkdir -p storage/app/private/ml

RUN useradd --create-home --uid 1000 iclimate && chown -R iclimate:iclimate /app
USER iclimate

WORKDIR /app/python_scripts

EXPOSE 8010

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD python -c "import urllib.request,sys; sys.exit(0 if urllib.request.urlopen('http://127.0.0.1:8010/health', timeout=3).status == 200 else 1)"

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8010"]

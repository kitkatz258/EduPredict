FROM python:3.14-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PIP_NO_CACHE_DIR=1 \
    OMP_NUM_THREADS=1 \
    OPENBLAS_NUM_THREADS=1 \
    MKL_NUM_THREADS=1 \
    NUMEXPR_NUM_THREADS=1 \
    PYTHONPATH=/srv/ml \
    ML_HOST=0.0.0.0 \
    PORT=8000

WORKDIR /srv/ml

RUN if [ -f /etc/apt/sources.list.d/debian.sources ]; then \
        sed -i 's|http://deb.debian.org|https://deb.debian.org|g; s|http://deb.debian.org/debian-security|https://deb.debian.org/debian-security|g' /etc/apt/sources.list.d/debian.sources; \
    fi \
    && apt-get update \
    && apt-get install -y --no-install-recommends libgomp1 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt requirements-api.txt ./
RUN python -m pip install --no-cache-dir -r requirements-api.txt

COPY src ./src
COPY api ./api
COPY models ./models

RUN useradd --create-home --uid 10001 --shell /usr/sbin/nologin mluser \
    && chown -R mluser:mluser /srv/ml

USER mluser

EXPOSE 8000

CMD ["python", "-m", "api.main"]

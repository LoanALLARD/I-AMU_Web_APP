# PostgreSQL 17 with pgTAP installed.
# pgTAP is built from source against the matching PostgreSQL dev headers,
# then exposed to SQL via CREATE EXTENSION pgtap.
FROM postgres:17-alpine

ARG PGTAP_VERSION=1.3.3

RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        build-base \
        perl \
        postgresql17-dev \
        wget \
        ca-certificates \
        unzip; \
    wget -O /tmp/pgtap.zip "https://api.pgxn.org/dist/pgtap/${PGTAP_VERSION}/pgtap-${PGTAP_VERSION}.zip"; \
    unzip -q /tmp/pgtap.zip -d /tmp; \
    cd "/tmp/pgtap-${PGTAP_VERSION}"; \
    make; \
    make install; \
    cd /; \
    rm -rf "/tmp/pgtap-${PGTAP_VERSION}" /tmp/pgtap.zip; \
    apk del .build-deps; \
    apk add --no-cache perl

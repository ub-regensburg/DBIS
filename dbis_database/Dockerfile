FROM bitnami/postgresql:16

USER root

RUN mkdir -p /db
RUN chmod -R 777 /db

RUN apt-get -y update && \
    apt-get install -y --no-install-recommends curl ca-certificates

USER 1000
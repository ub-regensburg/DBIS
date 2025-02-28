FROM python:3.10-slim

WORKDIR /app

RUN apt-get -y update && apt-get install -y gcc && apt-get install -y libmariadb3 libmariadb-dev

COPY app/requirements.txt /app
RUN pip install -r requirements.txt

HEALTHCHECK CMD curl -fsSL "http://elasticsearch:9200/_cat/health?h=status" | grep -E '^green'

ENTRYPOINT ["tail", "-f", "/dev/null"]
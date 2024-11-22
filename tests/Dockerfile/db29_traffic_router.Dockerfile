FROM debian:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt update && apt install netcat-openbsd openssh-server caddy curl -y

CMD service ssh start && caddy start && tail -f /dev/null

FROM debian:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt update && apt install caddy curl netcat-openbsd openssh-server podman podman-compose -y

CMD service ssh start && caddy start && tail -f /dev/null

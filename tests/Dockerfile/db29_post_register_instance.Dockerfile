FROM debian:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt update && apt install caddy curl openssh-server podman podman-compose -y

CMD service ssh start && tail -F /dev/null

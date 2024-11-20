FROM debian:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt update && apt install openssh-server caddy -y

CMD service ssh start && caddy start && tail -f /dev/null

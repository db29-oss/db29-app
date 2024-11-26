FROM debian:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt update && apt install openssh-server podman -y

CMD service ssh start && tail -F /dev/null

FROM debian:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt update && apt install podman openssh-server rsync -y

CMD service ssh start && tail -F /dev/null

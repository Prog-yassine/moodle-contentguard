FROM alpine:3.22
RUN --mount=type=secret,id=proxy_ca,required=true \
    SSL_CERT_FILE=/run/secrets/proxy_ca apk add --no-cache \
    php83 php83-pgsql php83-curl php83-zip php83-intl php83-mbstring \
    php83-soap php83-gd php83-dom php83-simplexml php83-xml \
    php83-xmlreader php83-xmlwriter php83-iconv php83-openssl \
    php83-tokenizer php83-ctype php83-session php83-fileinfo \
    php83-sodium php83-exif php83-opcache php83-zlib
WORKDIR /var/www/html
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8080
CMD ["php83", "-S", "0.0.0.0:8080", "-t", "/var/www/html/public"]

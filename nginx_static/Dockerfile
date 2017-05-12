# Usage:
#     docker build -t myPointOfTest .
#     docker run -dp80:80 myPointOfTest
FROM nginx
MAINTAINER Thiago Santana <thiago.santana@axiros.com> 

COPY conf/point_of_test.conf /etc/nginx/conf.d/

RUN mkdir -p /var/www/download \
    && dd if=/dev/urandom of=/var/www/download/20 bs=1M count=20 \
    && dd if=/dev/urandom of=/var/www/download/5 bs=1M count=5 \
    && rm /etc/nginx/conf.d/default.conf \
    && touch /var/www/upload /var/www/index.html
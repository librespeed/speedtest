# Usage:
#     docker build -t myPointOfTest .
#     docker run -dp80:80 -v$(pwd)/..:/var/www -v$(pwd)/php:/var/www/php myPointOfTest
FROM debian:jessie
MAINTAINER Thiago Santana <thiago.santana@axiros.com> 

COPY conf/speedtest.conf /etc/apache2/sites-available/

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install --no-install-recommends -y apache2 libapache2-mod-php5 \
    && a2enmod rewrite \
    && apt-get clean \
    && a2dissite 000-default \
    && a2ensite speedtest

EXPOSE 80

CMD ["/bin/bash", "-c", "source /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND"]
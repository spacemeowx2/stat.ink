FROM centos:7
MAINTAINER spacemeowx2 <spacemeowx2@gmail.com>

RUN yum update -y \
    && yum install -y @base @core epel-release scl-utils \
    && yum-config-manager --enable epel

RUN yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm \
    && yum-config-manager --enable remi-safe

RUN curl -sL https://rpm.fetus.jp/jp3cki.repo -o /etc/yum.repos.d/jp3cki.repo \
    && yum-config-manager --enable jp3cki jp3cki-h2o-mainline

RUN curl -fsSL 'https://rpm.nodesource.com/setup_16.x' | bash -

RUN yum install -y ImageMagick brotli diff gcc-c++ git h2o jpegoptim make \
    nodejs patch php80-php-cli php80-php-fpm php80-php-gd php80-php-intl \
    php80-php-mbstring php80-php-mcrypt php80-php-opcache php80-php-pdo \
    php80-php-pecl-msgpack php80-php-pecl-zip php80-php-pgsql \
    php80-php-process php80-php-sodium php80-php-xml php80-runtime pngcrush \
    postgresql12 postgresql12-server unzip zopfli

RUN useradd statink \
    && chmod 701 /home/statink \
    && echo 'statink ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers \
    && ln -s /usr/bin/php80 /usr/bin/php

RUN cd /etc/opt/remi/php80/php-fpm.d \
    && perl -i -pe 's/^user = apache/user = statink/' www.conf

ADD docker/h2o/h2o.conf /etc/h2o/h2o.conf

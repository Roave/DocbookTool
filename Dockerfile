FROM composer:2.2.9 AS composer-base-image

FROM node:17.7 AS npm-dependencies

WORKDIR /app

COPY ./package.json \
    ./package-lock.json \
    ./

RUN npm ci

FROM composer-base-image AS production-dependencies

COPY ./composer.json \
    ./composer.lock \
    ./

RUN composer install \
    --ignore-platform-reqs \
    --no-autoloader \
    --no-cache \
    --no-dev \
    --no-plugins \
    --no-scripts

FROM production-dependencies AS development-dependencies

RUN composer install \
    --ignore-platform-reqs \
    --no-autoloader \
    --no-cache \
    --no-plugins \
    --no-scripts

FROM ubuntu:20.04 AS base-dependencies

RUN export DEBIAN_FRONTEND="noninteractive" \
    && mkdir -p /usr/share/man/man1 \
    && apt-get update \
    && apt-get -y upgrade \
    && apt-get install -y software-properties-common gnupg curl \
    && add-apt-repository --yes ppa:ondrej/php \
    && curl --silent https://adoptopenjdk.jfrog.io/adoptopenjdk/api/gpg/key/public | apt-key add - \
    && add-apt-repository --yes https://adoptopenjdk.jfrog.io/adoptopenjdk/deb/ \
    && curl -sL https://deb.nodesource.com/setup_14.x | bash - \
    && apt-get update \
    && apt-get install -y \
      bash \
      binutils \
      graphviz \
      php8.1-cli \
      php8.1-zip \
      php8.1-mbstring \
      php8.1-xml \
      nodejs \
      adoptopenjdk-8-hotspot-jre \
      xfonts-75dpi \
      xfonts-base \
      fontconfig \
      libjpeg-turbo8 \
      wkhtmltopdf \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /docs-package/pdf /app /docs-src/book /docs-src/templates /docs-src/features

WORKDIR /app

COPY ./composer.json \
    ./composer.lock \
    ./package.json \
    ./package-lock.json \
    ./

COPY ./src ./src
COPY ./bin ./bin

ADD https://github.com/plantuml/plantuml/releases/download/v1.2022.2/plantuml-1.2022.2.jar bin/plantuml.jar

COPY --from=production-dependencies /usr/bin/composer /usr/local/bin/composer
COPY --from=npm-dependencies /app/node_modules node_modules

RUN ln -s node_modules/.bin/marked /usr/local/bin/marked \
    && ln -s node_modules/.bin/redoc-cli /usr/local/bin/redoc-cli

ENV DOCBOOK_TOOL_CONTENT_PATH=/docs-src/book \
    DOCBOOK_TOOL_TEMPLATE_PATH=/docs-src/templates \
    DOCBOOK_TOOL_FEATURES_PATH=/docs-src/features \
    DOCBOOK_TOOL_OUTPUT_HTML_FILE=/docs-package/index.html \
    DOCBOOK_TOOL_OUTPUT_PDF_PATH=/docs-package/pdf

ENTRYPOINT ["bin/docbook-tool"]
CMD ["--html", "--pdf"]

FROM base-dependencies AS production

COPY --from=production-dependencies /app/vendor vendor

RUN composer install \
    --classmap-authoritative \
    --no-cache \
    --no-dev

FROM base-dependencies AS development

COPY --from=development-dependencies /app/vendor vendor
COPY ./phpcs.xml.dist \
    ./phpunit.xml.dist \
    ./psalm.xml.dist \
    ./
COPY ./test test

RUN composer install \
    --classmap-authoritative \
    --no-cache

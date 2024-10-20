# syntax=docker/dockerfile:1.6

FROM composer:2.6.6 AS composer-base-image
FROM node:22.4.0 AS npm-base-image
FROM ubuntu:22.04 AS ubuntu-base-image

FROM npm-base-image AS npm-dependencies

WORKDIR /build

RUN \
    --mount=type=cache,target=/root/.npm,id=npm \
    --mount=source=package.json,target=package.json \
    --mount=source=package-lock.json,target=package-lock.json \
    npm ci


FROM ubuntu-base-image AS base-with-dependencies

RUN  \
    --mount=type=cache,target=/var/cache/apt,sharing=private \
    --mount=type=cache,target=/var/lib/apt/lists/,sharing=private \
    export DEBIAN_FRONTEND="noninteractive" \
    && rm /etc/apt/apt.conf.d/docker-clean \
    && mkdir -p /usr/share/man/man1 \
    && apt-get update \
    && apt-get -y upgrade \
    && apt-get install -y --no-install-recommends software-properties-common gnupg curl \
    && add-apt-repository --yes ppa:ondrej/php \
    && curl -sL https://deb.nodesource.com/setup_21.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
      bash \
      binutils \
      graphviz \
      php8.3-cli \
      php8.3-zip \
      php8.3-mbstring \
      php8.3-xml \
      php8.3-curl \
      php8.3-gd \
      nodejs \
      openjdk-18-jre \
      xfonts-75dpi \
      xfonts-base \
      fontconfig \
      libjpeg-turbo8 \
      wkhtmltopdf \
    && mkdir -p /docs-package/pdf /app /docs-src/book /docs-src/templates /docs-src/features

ADD https://github.com/plantuml/plantuml/releases/download/v1.2023.4/plantuml-1.2023.4.jar app/bin/plantuml.jar
ENV XDG_RUNTIME_DIR=/tmp/runtime-root

FROM base-with-dependencies AS production-composer-dependencies

WORKDIR /build

RUN  \
    --mount=source=/usr/bin/composer,target=/usr/bin/composer,from=composer-base-image \
    --mount=type=cache,target=/root/.composer,id=composer \
    --mount=source=composer.json,target=composer.json \
    --mount=source=composer.lock,target=composer.lock \
    composer install \
    --no-autoloader \
    --no-dev \
    --no-plugins


FROM production-composer-dependencies AS development-composer-dependencies

WORKDIR /build

RUN \
    --mount=source=/usr/bin/composer,target=/usr/bin/composer,from=composer-base-image \
    --mount=type=cache,target=/root/.composer,id=composer \
    --mount=source=composer.json,target=composer.json \
    --mount=source=composer.lock,target=composer.lock \
    composer install \
    --no-plugins


FROM base-with-dependencies AS base-with-codebase

WORKDIR /app

COPY --link ./src ./src
COPY --link ./bin ./bin

COPY --link --from=npm-dependencies /build/node_modules node_modules

RUN ln -s /app/node_modules/.bin/marked /usr/local/bin/marked \
    && marked --version \
    && ln -s /app/node_modules/.bin/redoc-cli /usr/local/bin/redoc-cli \
    && redoc-cli --version

ENV DOCBOOK_TOOL_CONTENT_PATH=/docs-src/book \
    DOCBOOK_TOOL_TEMPLATE_PATH=/docs-src/templates \
    DOCBOOK_TOOL_FEATURES_PATH=/docs-src/features \
    DOCBOOK_TOOL_OUTPUT_HTML_FILE=/docs-package/index.html \
    DOCBOOK_TOOL_OUTPUT_PDF_PATH=/docs-package/pdf

ENTRYPOINT ["bin/docbook-tool"]
CMD ["--html", "--pdf"]


FROM base-with-codebase AS development

COPY --link ./phpcs.xml.dist \
    ./phpunit.xml.dist \
    ./psalm.xml.dist \
    ./
COPY --link ./test test

COPY --link ./composer.json \
    ./composer.lock \
    ./package.json \
    ./package-lock.json \
    ./

COPY --link --from=composer-base-image /usr/bin/composer /usr/local/bin/composer
COPY --link --from=development-composer-dependencies /build/vendor vendor

# run the plugins
RUN \
    --mount=type=cache,target=/root/.composer,id=composer \
    composer install

FROM development AS test-output-builder

COPY --link ./test/fixture/templates /docs-src/templates
COPY --link ./test/fixture/docbook /docs-src/book
COPY --link ./test/fixture/feature /docs-src/features
RUN bin/docbook-tool --html --pdf

FROM scratch AS test-output

COPY --from=test-output-builder /docs-package /

FROM development AS tested

RUN vendor/bin/phpunit
RUN vendor/bin/phpcs
RUN vendor/bin/psalm
RUN touch .tested


FROM base-with-codebase AS production

COPY --link --from=production-composer-dependencies /build/vendor vendor

RUN \
    --mount=source=/usr/bin/composer,target=/usr/bin/composer,from=composer-base-image \
    --mount=type=cache,target=/root/.composer,id=composer \
    --mount=source=composer.json,target=composer.json \
    --mount=source=composer.lock,target=composer.lock \
    composer dump-autoload \
    --classmap-authoritative \
    --no-dev

# The tests must have run to build production
COPY --link --from=tested /app/.tested .

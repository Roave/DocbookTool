FROM ubuntu:20.04

RUN mkdir -p /usr/share/man/man1 \
    && apt-get update \
    && apt-get -y upgrade \
    && apt-get install -y software-properties-common gnupg curl \
    && add-apt-repository --yes ppa:ondrej/php \
    && curl --silent https://adoptopenjdk.jfrog.io/adoptopenjdk/api/gpg/key/public | apt-key add - \
    && add-apt-repository --yes https://adoptopenjdk.jfrog.io/adoptopenjdk/deb/ \
    && curl -sL https://deb.nodesource.com/setup_12.x | bash - \
    && apt-get update \
    && apt-get install -y bash binutils php8.0-cli php8.0-zip php8.0-mbstring php8.0-xml nodejs adoptopenjdk-8-hotspot-jre xfonts-75dpi xfonts-base fontconfig libjpeg-turbo8 \
    && curl -L -o /wkhtmltox.deb https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6-1/wkhtmltox_0.12.6-1.focal_amd64.deb \
    && dpkg -i /wkhtmltox.deb \
    && npm install -g redoc-cli marked \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /docs-package/pdf /app /docs-src/book /docs-src/templates /docs-src/features

ADD ./composer.json /app
ADD ./composer.lock /app
ADD ./src /app/src
ADD ./bin /app/bin
ADD ./test/fixture/docbook /docs-src/book
ADD ./test/fixture/templates /docs-src/templates
ADD ./test/fixture/feature /docs-src/features

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

RUN composer install

ENV DOCBOOK_TOOL_CONTENT_PATH=/docs-src/book \
    DOCBOOK_TOOL_TEMPLATE_PATH=/docs-src/templates \
    DOCBOOK_TOOL_FEATURES_PATH=/docs-src/features \
    DOCBOOK_TOOL_OUTPUT_HTML_FILE=/docs-package/index.html \
    DOCBOOK_TOOL_OUTPUT_PDF_PATH=/docs-package/pdf

ENTRYPOINT ["bin/docbook-tool"]
CMD ["--html", "--pdf"]

# PHPのイメージを使用
FROM php:7.4-cli

# 作業ディレクトリを設定
WORKDIR /var/www/html

# 必要なパッケージをインストール
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# アプリケーションのソースコードをコピー
COPY ../ /var/www/html

# Composerのプラグインを有効にし、依存関係をインストール
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install

# PHPのビルトインサーバを起動
CMD ["php", "-S", "0.0.0.0:80"]
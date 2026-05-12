# Imagen base de PHP 8.2 con herramientas de línea de comandos
FROM php:8.2-cli

# Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Directorio de trabajo dentro del contenedor
WORKDIR /var/www/html

# Instalar Composer desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar sólo los archivos de Composer primero (para aprovechar cache de Docker)
COPY composer.json composer.lock ./

# Instalar dependencias de PHP (sin dev) y optimizar autoload
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts

# Copiar el resto del código del backend
COPY . .

# Railway suele exponer la variable de entorno PORT
# Usamos 8000 por defecto si no viene definida
ENV PORT=8000

# Exponer el puerto (Railway leerá esto)
EXPOSE 8000

# Comando de arranque:
# 1) Ejecutar migraciones y optimizaciones básicas de Laravel
# 2) Levantar el servidor embebido de PHP sirviendo la carpeta public
# 3) Ejecutar el trabajo programado en segundo plano
# 4) Ejecutar el servidor web en segundo plano
CMD sh -c "php artisan migrate --force && \
php artisan config:cache && \
php artisan route:cache && \
php artisan view:cache && \
php artisan storage:link || true && \
php artisan schedule:work & \
php -S 0.0.0.0:${PORT} -t public"
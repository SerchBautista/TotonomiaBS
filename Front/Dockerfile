# Stage 1: Build Angular application
FROM node:22-alpine AS build
WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm install

# Copy source code
COPY . .

# Build for production
RUN npm run build -- --configuration=production

# Stage 2: Serve using Nginx
FROM nginx:alpine

# Replace the default config with an SPA-aware server.
RUN rm -rf /etc/nginx/conf.d/*
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN sed -i 's|pid        /run/nginx.pid;|pid        /tmp/nginx.pid;|' /etc/nginx/nginx.conf

# Configurar zona horaria CDMX
RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/America/Mexico_City /etc/localtime \
    && echo "America/Mexico_City" > /etc/timezone \
    && apk del tzdata

# Copy Angular build from Stage 1.
COPY --from=build /app/dist/*/browser /usr/share/nginx/html/

# Non-root user for security hardening
RUN addgroup -S appgroup && adduser -S -G appgroup appuser
RUN chown -R appuser:appgroup /usr/share/nginx/html
RUN chown -R appuser:appgroup /var/cache/nginx /var/log/nginx /var/run

EXPOSE 8080

USER appuser

CMD ["nginx", "-g", "daemon off;"]

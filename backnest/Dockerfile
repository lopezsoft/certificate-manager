# ─── Stage 1: Builder ─────────────────────────────────────────────────────────
FROM node:20-alpine AS builder

WORKDIR /app

COPY package*.json ./
RUN npm ci --legacy-peer-deps

COPY . .
RUN npm run build

# ─── Stage 2: Production ──────────────────────────────────────────────────────
FROM node:20-alpine AS production

WORKDIR /app

ENV NODE_ENV=production

# Instalar solo dependencias de producción
COPY package*.json ./
RUN npm ci --only=production --legacy-peer-deps

# Copiar build
COPY --from=builder /app/dist ./dist

# Copiar templates de email
COPY --from=builder /app/src/modules/mail/templates ./src/modules/mail/templates

# Directorio para archivos subidos
RUN mkdir -p public/attachments public/pdf

EXPOSE 3000

CMD ["node", "dist/main"]

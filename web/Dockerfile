FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY . .
ARG API_BASE=http://localhost:8000/api
ENV NUXT_PUBLIC_API_BASE=$API_BASE
RUN npx nuxt generate

FROM nginx:1.27-alpine
COPY --from=build /app/.output/public /usr/share/nginx/html

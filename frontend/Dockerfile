FROM node:20

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

# (opcional, si no tienes problemas de permisos puedes quitarlo)
RUN chmod -R 755 node_modules/.bin

RUN npm run build

ENV PORT=4173
EXPOSE 4173

# Usamos la variable PORT de Railway y exponemos 0.0.0.0
CMD npm run preview -- --host 0.0.0.0 --port ${PORT}
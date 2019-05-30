FROM node:alpine

WORKDIR /app
COPY package.json .
COPY /src ./src

RUN echo $NODE_VERSION && npm install

CMD [ "node" , "src/SpeedTest.js"]

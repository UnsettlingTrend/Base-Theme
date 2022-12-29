#!/bin/bash

cd /app/web/themes/custom/cf
npm install -y
npm install -g gulp
gulp sass

#!/bin/bash

cd /app/web/themes/custom/cf
npm install
npm install -g gulp
gulp sass

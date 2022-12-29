'use strict';
const { src, dest } = require('gulp');
const gulp_sass = require('gulp-sass')(require('sass'));

function sass(done) {
  src('src/scss/style.scss')
    .pipe(gulp_sass().on('error', gulp_sass.logError))
    .pipe(dest('dist/css'));
  done();
}
exports.sass = sass

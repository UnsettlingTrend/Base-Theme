'use strict';
const { src, dest } = require('gulp');
const gulp_sass = require('gulp-sass')(require('sass'));
const gulp_minify = require('gulp-minify');

function css(done) {
  src('src/scss/style.scss')
    .pipe(gulp_sass().on('error', gulp_sass.logError))
    .pipe(dest('dist/css'));
  done();
}

function js(done) {
  src(['src/js/*.js', 'src/js/*.mjs'])
    .pipe(gulp_minify())
    .pipe(dest('dist/js'));
  done();
}

exports.css = css
exports.js = js

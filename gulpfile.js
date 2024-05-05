const { gulp, src, dest, series, parallel } = require('gulp');
const concat = require('gulp-concat');
const uglify = require('gulp-uglify');
const sass = require('gulp-sass')(require('sass'));
const cleanCSS = require('gulp-clean-css');
const imagemin = import('gulp-imagemin');
const ttf2woff2 = require('gulp-ttf2woff2');

// Paths
const jsSrc = 'src/js/*.js';
const jsDest = 'dist/js/';
const scssSrc = 'src/scss/*.scss';
const cssDest = 'dist/css/';
const fontsSrc = 'src/fonts/*';
const fontsDest = 'dist/fonts/';

// Sass task: compile to CSS and minify
function css() {
  return src(scssSrc)
    .pipe(sass().on('error', sass.logError))
    .pipe(concat('styles.css'))
    .pipe(cleanCSS())
    .pipe(dest(cssDest));
}

// JavaScript task: concatenate and minify
function js() {
  return src(jsSrc)
    .pipe(concat('scripts.js'))
    .pipe(uglify())
    .pipe(dest(jsDest));
}

// Convert TTF to WOFF2
// function convertTTFtoWOFF2() {
//   return src('src/fonts/*.ttf')
//     .pipe(ttf2woff2())
//     .pipe(dest('src/fonts/'));
// }

// Minify font files
function compressFonts() {
  return src('src/fonts/*.{ttf,woff2}')
    .pipe(dest(fontsDest));
}

// Default task
exports.default = series(js, css, compressFonts);


// ----Include gulp----
var gulp = require('gulp');

// ----Include Plugins----
var sass = require('gulp-sass');
var imagemin = require('gulp-imagemin');
var del = require('del');
var cache = require('gulp-cache');
var sequence = require('gulp-sequence');
var uglify = require('gulp-uglify');
var cleanCss = require('gulp-clean-css');
var concat = require('gulp-concat');
var sourcemaps = require('gulp-sourcemaps');
var rename = require('gulp-rename');

var theme_location = 'web/themes/custom/chris/';

// ----Tasks----
// Test and Identify Path
gulp.task('test', function() {
    console.log('****Gulp is running****')
});

// SASS
gulp.task('sass', function () {
    return gulp.src(theme_location + 'scss/*.scss')
        .pipe(sass())
        .pipe(gulp.dest(theme_location + 'dist/css'))
});

// SASS Watch
gulp.task('sass:watch', function () {
    gulp.watch(theme_location + 'scss/*.scss', ['sass'])
});

// CSS Minify
gulp.task('cssmin', function(){
    return gulp.src(theme_location + 'dist/css/style.css')
        .pipe(cleanCss())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest(theme_location + 'dist/css'))
});

// JS Concat
gulp.task('jscon', function(){
    return gulp.src(theme_location + 'js/*.js')
        .pipe(concat('all.js'))
        .pipe(gulp.dest(theme_location + 'dist/js'))
});

// JS Minify
gulp.task('jsmin', function(){
    return gulp.src(theme_location + 'dist/js/all.js')
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest(theme_location + 'dist/js'))
});

// ----Multi Tasks----
// SASS + CSS Minify
gulp.task('sassmin', sequence('sass', 'cssmin'))
// JS Concat + JS Uglify
gulp.task('jsconmin', sequence('jscon', 'jsmin'))

// Default - SASS + CSS Minify + JS Concat + JS Uglify
gulp.task('default', sequence('sass', 'cssmin', 'jscon', 'jsmin'))

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
var sourceMaps = require('gulp-sourcemaps');
var rename = require('gulp-rename');
var clean = require('gulp-clean');

var theme_location = 'web/themes/custom/chris/';

// ----Tasks----
// Test and Identify Path
gulp.task('test', function() {
    console.log('****Gulp is running****')
});

// SASS
gulp.task('sass', function () {
    gulp.src(theme_location + 'dist/css', {read: false})
        .pipe(clean());
    return gulp.src(theme_location + 'src/scss/layout.scss')
        .pipe(sass())                        // Compile SASS
        .pipe(concat('style.css'))
        .pipe(gulp.dest(theme_location + 'dist/css'))
});

// SASS Watch
// gulp.task('sass:watch', function () {
//     gulp.watch(theme_location + 'scss/*.scss', ['sass'])
// });

// CSS Minify
gulp.task('cssmin', function() {
    return gulp.src(theme_location + 'dist/css/style.css')
        .pipe(cleanCss())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest(theme_location + 'dist/css'))
});

// JS Concat
gulp.task('jscon', function(){
    return gulp.src(theme_location + 'src/js/*.js')
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


// SASS
gulp.task('img', function () {
    gulp.src(theme_location + 'dist/images', {read: false})
        .pipe(clean());
    return gulp.src(theme_location + 'src/images/*')
        .pipe(imagemin())
        .pipe(gulp.dest(theme_location + 'dist/images'))
});

// ----Multi Tasks----
// SASS + CSS Minify
gulp.task('sassmin', sequence('sass', 'cssmin'))
// JS Concat + JS Uglify
gulp.task('jsconmin', sequence('jscon', 'jsmin'))
// Imaages
gulp.task('images', sequence('img'))

// Default - SASS + CSS Minify + JS Concat + JS Uglify
gulp.task('default', sequence('sass', 'cssmin', 'jscon', 'jsmin', 'img'))

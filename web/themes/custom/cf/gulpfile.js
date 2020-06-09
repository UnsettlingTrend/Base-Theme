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

// ----Tasks----
// Test and Identify Path
gulp.task('test', function() {
    console.log('****Gulp is running****')
});

// SASS
gulp.task('sass', function () {
    gulp.src('dist/css', {read: false})
        .pipe(clean());
    return gulp.src('src/scss/style.scss')
        .pipe(sass())                        // Compile SASS
        .pipe(concat('style.css'))
        .pipe(gulp.dest('dist/css'))
});

// SASS Watch
gulp.task('sass:watch', function () {
    gulp.watch('src/scss/**/*.scss', ['css'])
});

// CSS Minify
gulp.task('cssmin', function() {
    return gulp.src('dist/css/style.css')
        .pipe(cleanCss())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('dist/css'))
});

// JS Concat
gulp.task('jscon', function(){
    gulp.src('dist/js', {read: false})
        .pipe(clean());
    return gulp.src('src/js/*.js')
        .pipe(concat('all.js'))
        .pipe(gulp.dest('dist/js'))
});

// JS Minify
gulp.task('jsmin', function(){
    return gulp.src('dist/js/all.js')
        .pipe(uglify())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('dist/js'))
});

// IMG minimize
gulp.task('images', function () {
    gulp.src('dist/images', {read: false})
        .pipe(clean());
    return gulp.src('src/images/*')
        .pipe(imagemin())
        .pipe(gulp.dest('dist/images'))
});

// FONTS move
gulp.task('fonts', function() {
    gulp.src('dist/fonts', {read: false})
        .pipe(clean());
    return gulp.src('src/fonts/**.ttf')
        .pipe(gulp.dest('dist/fonts'));
});

// ----Multi Tasks----
// SASS + CSS Minify
gulp.task('css', sequence('sass', 'cssmin'));
// JS Concat + JS Uglify
gulp.task('js', sequence('jscon', 'jsmin'));

// Default - SASS + CSS Minify + JS Concat + JS Uglify + Images
gulp.task('default', sequence('css', 'js', 'images', 'fonts'));

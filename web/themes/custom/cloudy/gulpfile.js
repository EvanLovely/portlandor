var gulp = require('gulp');
var browserSync = require('browser-sync').create();
var sass = require('gulp-sass');
var sourcemaps = require('gulp-sourcemaps');
var pa11y = require('gulp-pa11y');
var runSequence = require('run-sequence');
var concat = require("gulp-concat");
var minifyCss = require("gulp-minify-css");
var uglify = require("gulp-uglify");

// Compile sass into CSS & auto-inject into browsers
gulp.task('style', function() {
  return gulp.src('scss/style.scss')
    .pipe(sourcemaps.init())
    .pipe(sass({
      includePaths: ['node_modules'],
      outputStyle: 'compressed'
    })).on('error', sass.logError)
    .pipe(sourcemaps.write('./maps'))
    .pipe(gulp.dest('./css'))
    .pipe(browserSync.stream());
});

// Move the javascript files into our js folder
gulp.task('js', function() {
    return gulp.src(['node_modules/bootstrap/dist/js/bootstrap.min.js', 'node_modules/jquery/dist/jquery.min.js', 'node_modules/popper.js/dist/umd/popper.min.js'])
        .pipe(gulp.dest("js"))
        .pipe(browserSync.stream());
});

gulp.task('pa11y', pa11y({
  url: 'https://portlandor.lndo.site/',
  failOnError: true, // fail tje build on error
  showFailedOnly: true, // show errors only and override reporter
  reporter: 'console'
}));

// Static Server + watching scss/html files
gulp.task('serve', ['style'], function() {

    browserSync.init({
        proxy: "https://portlandor.lndo.site/",
    });

    gulp.watch(['node_modules/bootstrap/scss/bootstrap.scss', 'scss/**/*.scss'], ['style']);
    //    gulp.watch("src/*.html").on('change', browserSync.reload);
});

gulp.task('build', function(callback) { runSequence('pa11y', callback); });

gulp.task('default', ['js', 'serve']);

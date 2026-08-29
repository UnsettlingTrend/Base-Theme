# UT Base

A neutral starter theme for Trend personal sites — a subtheme of
[Material Base](https://www.drupal.org/project/material_base) with the MDC
(Material Components for the Web) layer enabled.

It ships a plain palette and typography so each site can brand it without
forking. Block placement for the theme is provided by the `trend_personal`
recipe, not by the theme itself.

## Build

Assets are compiled with webpack. From this directory:

```bash
npm install
npm run build       # production
npm run develop     # watch
```

`dist/` is git-ignored; build it per environment. In Lando, `lando build`
compiles `material_base` and then this theme.

## Customising

- Palette / type: override the variables in `scss/theme/variables.scss`.
- Add your own libraries in `ut_base.libraries.yml` and attach them from
  `ut_base.info.yml`.
- For site-specific work, copy this theme into `web/themes/custom/` under a new
  name and set it as the default.

## SDC components

Single-directory components live in `components/`. Templates in
`templates/components/` override the corresponding Drupal templates and embed
the SDC.

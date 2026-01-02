# Build assets for base theme
cd /app/web/themes/contrib/material_base
npm install
npm run build
# Build assets for the sub theme
cd /app/web/themes/custom/ut_material
npm install
npm run build
# Clear the cache
#/app/vendor/bin/drush cr TODO: DOESN'T WORK FOR SOME REASON, LOOK INTO LATER

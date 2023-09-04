# Private Files Directory

This is the private file directory for Drupal. It's ill-advised for this directory to be anywhere inside the Drupal
root directory, lest Apache/nginx serve those files up publicly.

Files in ths directory will be run through Drupal's permission system to determine access.

These files also shouldn't be version controlled; they should exist on some kind of media share.

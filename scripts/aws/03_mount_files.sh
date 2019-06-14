#!/usr/bin/env bash
# Mount an S3 bucket for public and private file systems
# Public files location:  /var/www/html/sites/default/files/
# Private files location: /var/www/private

# Create the directory where the s3 will mount, and delete the private and public directories to later be sym links
sudo mkdir -p /var/www/s3
sudo rm -rf /var/www/private/
sudo rm -rf /var/www/html/sites/default/files

# Mount the s3 bucket
sudo /usr/local/bin/s3fs com-chrisferagotti-unsettlingtrend -o use_cache=/tmp -o allow_other -o uid=1001 -o mp_umask=002 -o multireq_max=5 /var/www/s3
#sudo /usr/local/bin/s3fs com-chrisferagotti-unsettlingtrend -o use_cache=/tmp -o allow_other -o mp_umask=002 -o multireq_max=5 /var/www/s3

# Create symlinks for the private and public file directories
ln -s /var/www/s3/dev/private /var/www/private
ln -s /var/www/s3/dev/public /var/www/html/sites/default/files

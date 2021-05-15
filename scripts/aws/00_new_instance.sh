#!/usr/bin/env bash
# These are commands that will need to be run on brand new instances before codedeploy can work

# Install codedeploy agent
sudo yum update -y
sudo yum install ruby -y
cd /home/ec2-user
wget https://aws-codedeploy-us-east-1.s3.amazonaws.com/latest/install      # Verify region!
chmod +x ./install
sudo ./install auto
sudo service codedeploy-agent start
# sudo service codedeploy-agent status

# Install mySQL
sudo yum install mysql -y
# sudo chkconfig mysql on
sudo yum install php-fpm -y
sudo chkconfig php-fpm on


# Create the webroot directory
sudo mkdir -p /var/www/html

##################### Configure S3 for mounting the file directories ###############################################
# Install the packages necessary for mounting the S3 bucket
sudo yum -y install automake fuse fuse-devel gcc-c++ git libcurl-devel libxml2-devel make open ssl-devel openssl-devel

# Download and compile fuse for mounting the s3 bucket
cd /usr/src/
sudo wget https://github.com/libfuse/libfuse/releases/download/fuse-3.0.0/fuse-3.0.0.tar.gz
sudo tar xzf fuse-3.0.0.tar.gz
cd fuse-3.0.0
sudo ./configure
sudo make && sudo make install
sudo ldconfig
sudo modprobe fuse

# Download and compile sf3s for mounting the s3 bucket
cd /usr/src/
sudo git clone https://github.com/s3fs-fuse/s3fs-fuse.git
cd s3fs-fuse
sudo ./autogen.sh
sudo ./configure
sudo make && sudo make install

# Set the user credentials
sudo touch /etc/passwd-s3fs
sudo su
# TODO Add an IAM user's accesskey:secretkey combo; probably the drupal-service user
sudo echo 'accesskey:secretkey' >> /etc/passwd-s3fs
exit
sudo chmod 640 /etc/passwd-s3fs
##################### END Configure S3 #############################################################################
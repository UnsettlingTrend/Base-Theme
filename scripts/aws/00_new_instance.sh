# These are commands that will need to be run on brand new instances before codedeploy can work

# Install codedeploy agent
sudo yum update
sudo yum install ruby -y
cd /home/ec2-user
wget https://aws-codedeploy-us-east-1.s3.amazonaws.com/latest/install      # Verify region!
chmod +x ./install
sudo ./install auto
sudo service codedeploy-agent start
sudo service codedeploy-agent status

sudo mkdir -p /var/www/html

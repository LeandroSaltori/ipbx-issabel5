Install Issabel 5 - Docker
https://www.issabel.org

yum update
yum -y install wget
yum -y install curl
wget -O - http://repo.issabel.org/issabel4-netinstall.sh | bash
curl http://repo.issabel.org/issabel5-netinstall.sh | bash

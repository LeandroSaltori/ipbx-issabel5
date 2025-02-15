# IPBX-Prisma Telecom

### En - Description: ###
This is a patch and files for installing custom Isabel 5 Rock Linux by Prisma Telecom.

### BR - Descrição: ###
Este é um patch e arquivos para instalação do Issabel 5 no Rock Linux personalizada pela Prisma Telecom.

yum update
yum -y install wget
yum -y install curl
wget -O -http://repo.issabel.org/issabel5-netinstall.sh | bash
curl http://repo.issabel.org/issabel5-netinstall.sh | bash

### Sobre ###}
  - Rock Linux 8  
- Patch Prisma Telecom [https://github.com/LeandroSaltori/ipbx-issabel5/edit/main/README.md]
      - Instalação do Network Manager
      - Instalação do TCP Dump
      - Instalação do SNGREP
      - Atualização do sistema (yum -y update && yum -y upgrade)
      - Instalação do OpenVPN
      - Instalação do subversion
      - Alteração do Favicon.ico
      - Downlod de Tema Prisma Telecom
      - Download das Pastas LANG e MODULES atualizadas para PT-BR   
      - Instalação do GeoIP  
      - Ajustes automaticos de Tempo de Transferencia de Chamadas
      - Ajustes de BIP de transferencia 

#INSTALL NETWORK MANAGER ROCK LINUX
sudo yum install NetworkManager-tui

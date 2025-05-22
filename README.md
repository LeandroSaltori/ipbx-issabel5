# IPBX-Prisma Telecom

### En - Description: ###
This is a patch and files for installing custom Isabel 5 Rock Linux by Prisma Telecom.

### BR - Descrição: ###
Este é um patch e arquivos para instalação do Issabel 5 no Rock Linux personalizada pela Prisma Telecom.

  yum update
  yum -y install wget
  yum -y install curl
  yum -y install net-tools
  dnf -y install NetworkManager NetworkManager-tui
  uym -y install tcpdump
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

# INSTALL NETWORK MANAGER ROCK LINUX

  sudo yum install NetworkManager-tui

# INSTALL CONEXÕES SSH
 *Se não instalar, não consegue se conectar via Putty ou SSH

   dnf install -y openssh-server
   systemctl enable sshd
   systemctl start sshd

# INSTALL EASY-VPN

  sudo yum -y install issabel-easyvpn

# INSTALL SNGREP no Rocky Linux 8

Passos para Instalar o SNGREP no Rocky Linux 8 (arquitetura x86_64):
  1. Atualizar os pacotes do sistema:
      sudo dnf update -y
  2. Instalar dependências essenciais:
     sudo dnf install -y epel-release dnf-utils ncurses-devel libpcap-devel cmake make gcc gcc-c++
  3. Ativar o repositório correto para x86_64:
     sudo dnf config-manager --set-enabled codeready-builder-for-rhel-8-x86_64-rpms
  4. Instalar o SNGREP:
     sudo dnf install -y sngrep

Se o pacote não for encontrado, ative o repositório COPR e tente novamente:
   sudo dnf copr enable irontec/sngrep -y
   sudo dnf install -y sngrep

  5. Verificar a instalação:
     sngrep -V
  6. Testar a captura de pacotes SIP:
     sudo sngrep


## Ajustes Tempo de Transferencia de Chamadas ##

  /etc/asterisk/features_general_custom.conf

  e adicione as linhas:
```

    transferdigittimeout=6
    featuredigittimeout=3000
    atxfernoanswertimeout=35
    pickupsound=beep
```

   - transferdigittimeout - Determina o número de segundos que o sistema aguarda o usuário digitar o número de destino numa transferência
   - featuredigittimeout  - Determina o tempo máximo, em milisegundos, que o usuário tem de tempo para digitar entre um dígito e o outro. 
   - atxfernoanswertimeout - Ajusta o tempo de transferencia das chamadas.
   - pickupsound - Habilita beep ao capturar uma chamada (*8)

https://wiki.asterisk.org/wiki/display/AST/Asterisk+13+Configuration_features





## Como eu posso ajudar? ##
Ajude-nos a entregar um conteúdo de qualidade. Toda ajuda é bem vinda.

## Autor ##
Autor: Leandro Saltori - Prisma Telecom

